package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.EmailPasswordRequest
import com.christopheraldoo.petheal.data.model.RegisterOtpRequest
import com.christopheraldoo.petheal.data.remote.ApiService
import kotlinx.coroutines.runBlocking
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Assume
import org.junit.BeforeClass
import org.junit.Test
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * PHASE 7: real-backend integration (JVM).
 *
 * Requires the VCMS backend serving at 127.0.0.1:8000 with seeded demo
 * data (`php artisan serve --port=8000`, ClinicSeeder clinics+users).
 * Override with -Dbackend.url=https://.../api/ when needed.
 *
 * Tenant pair: user.pusat@petheal.com (petheal-pusat) vs
 * user@happypaws.id (happy-paws), both password `user123`.
 */
class BackendIntegrationTest {

    companion object {
        val BASE_URL: String =
            System.getProperty("backend.url") ?: "http://127.0.0.1:8000/api/"

        lateinit var api: ApiService
        lateinit var httpClient: OkHttpClient

        @BeforeClass
        @JvmStatic
        fun setup() {
            httpClient = OkHttpClient.Builder()
                .connectTimeout(15, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .build()

            // Tes ini butuh backend NYATA di BASE_URL. Kalau server tidak
            // hidup, lewati seluruh kelas dengan pesan jelas alih-alih
            // membuat `./gradlew test` merah karena connection refused.
            Assume.assumeTrue(
                "Backend tidak terjangkau di $BASE_URL — jalankan " +
                    "`php artisan serve --port=8000` lalu ulangi.",
                backendReachable()
            );
            api = Retrofit.Builder()
                .baseUrl(BASE_URL)
                .client(httpClient)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(ApiService::class.java)
        }

        /** Cek cepat (2 detik) apakah BASE_URL melayani HTTP. */
        private fun backendReachable(): Boolean = try {
            val probe = OkHttpClient.Builder()
                .connectTimeout(2, TimeUnit.SECONDS)
                .readTimeout(2, TimeUnit.SECONDS)
                .build()
            probe.newCall(Request.Builder().url(BASE_URL).build())
                .execute()
                .use { true }
        } catch (e: Exception) {
            false
        }

        fun authHeader(token: String) = "Bearer $token"
    }

    private fun authedApi(token: String): ApiService {
        val client = OkHttpClient.Builder()
            .addInterceptor { chain ->
                chain.proceed(
                    chain.request().newBuilder()
                        .header("Authorization", authHeader(token))
                        .build()
                )
            }
            .build()
        return Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    private fun login(email: String): String = runBlocking {
        val res = api.login(EmailPasswordRequest(email, "user123"))
        assertTrue("login $email HTTP ${res.code()}", res.isSuccessful)
        val body = res.body()!!
        assertTrue(body.success)
        assertNotNull(body.data?.token)
        assertNotNull("user must be clinic-bound", body.data?.user?.clinic?.slug)
        body.data!!.token
    }

    @Test
    fun `public clinics list active tenants`() = runBlocking {
        val res = api.getPublicClinics()
        assertTrue(res.isSuccessful)
        val clinics = res.body()!!.data!!
        assertTrue(clinics.isNotEmpty())
        assertTrue(clinics.any { it.slug == "petheal-pusat" })
        assertTrue(clinics.all { !it.slug.isNullOrBlank() && !it.name.isNullOrBlank() })
    }

    @Test
    fun `login binds clinic from server`() = runBlocking {
        val res = api.login(EmailPasswordRequest("user.pusat@petheal.com", "user123"))
        assertTrue(res.isSuccessful)
        val user = res.body()!!.data!!.user
        assertEquals("user", user.role)
        assertEquals("petheal-pusat", user.clinic?.slug)
    }

    @Test
    fun `tenant scoped reads per user`() = runBlocking {
        val tokenA = login("user.pusat@petheal.com")
        val a = authedApi(tokenA)

        val doctors = a.getDoctors()
        assertTrue(doctors.isSuccessful)
        assertTrue(doctors.body()!!.success)

        val services = a.getServices(null)
        assertTrue(services.isSuccessful)
        assertTrue(services.body()!!.success)

        val pets = a.getPets()
        assertTrue(pets.isSuccessful)

        val profile = a.getProfile()
        assertTrue(profile.isSuccessful)
        assertEquals("petheal-pusat", profile.body()!!.data!!.clinic?.slug)
    }

    @Test
    fun `anonymous services require clinic slug`() = runBlocking {
        val missing = api.getServices(null)
        assertEquals(422, missing.code())

        val scoped = api.getServices("petheal-pusat")
        assertTrue(scoped.isSuccessful)
        assertTrue(scoped.body()!!.success)
    }

    @Test
    fun `unauthenticated profile is canonical 401`() = runBlocking {
        val res = api.getProfile()
        assertEquals(401, res.code())
    }

    @Test
    fun `cross tenant booking is rejected`() = runBlocking {
        val tokenA = login("user.pusat@petheal.com")
        val tokenB = login("user@happypaws.id")
        val a = authedApi(tokenA)
        val b = authedApi(tokenB)

        // Doctor of clinic A...
        val doctorsA = a.getDoctors().body()!!.data!!
        assertTrue(doctorsA.isNotEmpty())
        val doctorA = doctorsA.first().id!!

        // ...cannot be booked by a clinic-B owner (needs own pet first).
        val pet = b.createPet(
            com.christopheraldoo.petheal.data.model.PetRequest(
                name = "IntB", species = "Cat"
            )
        )
        assertTrue(pet.isSuccessful)
        val petId = pet.body()!!.data!!.id!!

        val servicesB = b.getServices(null).body()!!.data!!
        assertTrue(servicesB.isNotEmpty())

        val booking = b.createBooking(
            com.christopheraldoo.petheal.data.model.BookingRequest(
                petId = petId,
                doctorId = doctorA,
                serviceId = servicesB.first().id!!,
                bookingDate = "2099-01-01",
                bookingTime = "09:00"
            )
        )
        // Tenant-aware validation rejects cross-clinic resource IDs.
        assertEquals(422, booking.code())

        // Cleanup: remove the probe pet (owner-scoped, allowed).
        val del = b.deletePet(petId)
        assertTrue(del.isSuccessful)
    }

    @Test
    fun `retired direct register answers 410 use_otp_flow`() = runBlocking {
        val request = Request.Builder()
            .url("${BASE_URL}auth/register-direct")
            .post(
                """{"name":"X","email":"x@y.id","password":"password123"}"""
                    .toRequestBody("application/json".toMediaType())
            )
            .addHeader("Accept", "application/json")
            .build()

        httpClient.newCall(request).execute().use { response ->
            assertEquals(410, response.code)
            assertTrue(response.body!!.string().contains("use_otp_flow"))
        }
    }

    @Test
    fun `register asks for an OTP instead of creating the account`() = runBlocking {
        val res = api.requestRegisterOtp(
            RegisterOtpRequest(
                name = "Phase7 Probe",
                email = "phase7probe+${System.currentTimeMillis()}@int.test",
                password = "password123",
                clinicSlug = "meowcare"
            )
        )
        // Akun BELUM dibuat di langkah ini — backend hanya mengirim kode OTP.
        assertTrue("request-otp HTTP ${res.code()}", res.isSuccessful)
        assertTrue(res.body()!!.success)

        // Token + clinic binding baru muncul setelah kode diverifikasi; langkah
        // itu perlu membaca inbox, jadi diuji manual (lihat backend docs/PROGRESS.md).
    }

    @Test
    fun `validation errors carry frozen envelope`() = runBlocking {
        val res = api.requestRegisterOtp(
            RegisterOtpRequest(name = "", email = "not-an-email", password = "short")
        )
        assertEquals(422, res.code())
        val raw = res.errorBody()!!.string()
        assertTrue(raw.contains("\"success\":false"))
        assertTrue(raw.contains("\"errors\""))
    }
}
