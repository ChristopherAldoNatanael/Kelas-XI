package com.christopheraldoo.petheal.data.remote

import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.local.SessionEvents
import com.christopheraldoo.petheal.di.ApplicationScope
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import okhttp3.Interceptor
import okhttp3.Response
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import javax.inject.Inject

/**
 * Menambahkan identitas permintaan (token + tenant) dan menutup sesi saat 401.
 *
 * **Kenapa tanpa `runBlocking` di setiap permintaan.** Sebelumnya interceptor
 * membaca DataStore lewat `runBlocking` setiap kali cache di memori kosong —
 * artinya thread jaringan ikut menunggu pembacaan disk, dan pada permintaan
 * pertama setelah proses dibunuh ini bisa terasa sebagai aplikasi menggantung.
 * Sekarang nilai disimpan di memori dan diisi sekali oleh
 * `PetHealApplication` (lihat `SessionCacheBootstrap`), sehingga interceptor
 * hanya membaca variabel.
 *
 * Bila permintaan datang sebelum cache siap (mis. cold start sangat cepat),
 * interceptor menunggu **maksimal 1,5 detik** — bukan selamanya — lalu lanjut
 * tanpa header. Permintaan publik tetap berhasil, dan permintaan yang memang
 * butuh token akan diulang pengguna setelah sesi selesai dipulihkan.
 */
class NetworkInterceptor @Inject constructor(
    private val preferencesManager: PreferencesManager,
    @ApplicationScope private val appScope: CoroutineScope
) : Interceptor {

    @Volatile private var cachedToken: String? = null
    @Volatile private var cachedClinicSlug: String? = null
    @Volatile private var cacheReady = false

    private val cacheReadyLatch = CountDownLatch(1)

    /** Menjaga agar alur "sesi berakhir" hanya berjalan sekali per sesi. */
    private val sessionExpiredEmitted = AtomicBoolean(false)

    fun updateToken(token: String?) {
        cachedToken = token
        // Token baru berarti sesi baru: izinkan notifikasi kedaluwarsa lagi.
        if (!token.isNullOrBlank()) sessionExpiredEmitted.set(false)
        markCacheReady()
    }

    /** Dipanggil setelah pilih klinik / login agar header tenant ikut berubah. */
    fun updateClinicSlug(slug: String?) {
        cachedClinicSlug = slug
        markCacheReady()
    }

    private fun markCacheReady() {
        if (cacheReady) return
        cacheReady = true
        cacheReadyLatch.countDown()
    }

    private fun awaitCacheReady() {
        if (cacheReady) return
        try {
            cacheReadyLatch.await(CACHE_WAIT_TIMEOUT_MS, TimeUnit.MILLISECONDS)
        } catch (_: InterruptedException) {
            Thread.currentThread().interrupt()
        }
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        awaitCacheReady()

        val originalRequest = chain.request()
        val token = cachedToken
        val path = originalRequest.url.encodedPath

        val builder = originalRequest.newBuilder()
        if (!token.isNullOrBlank()) {
            builder.header("Authorization", "Bearer $token")
        }

        // PHASE 7: tenant hint. Backend memvalidasi bila ada (403 saat tidak
        // cocok); katalog anonim juga mengirim ?clinic_slug=.
        val skipSlugHeader = NO_SLUG_HEADER_PATHS.any { path.contains(it) }
        val clinicSlug = cachedClinicSlug
        if (!skipSlugHeader && !clinicSlug.isNullOrBlank()) {
            builder.header("X-Clinic-Slug", clinicSlug)
        }

        val response = chain.proceed(builder.build())

        // PHASE 7 (§21): token kedaluwarsa mengakhiri sesi tepat satu kali.
        // Cache di memori dikosongkan seketika (permintaan berikutnya tidak
        // membawa token basi), lalu penyimpanan dibersihkan di background.
        val isAuthPath = AUTH_PATHS.any { path.contains(it) }
        if (response.code == 401 && !token.isNullOrBlank()) {
            cachedToken = null
            if (!isAuthPath && sessionExpiredEmitted.compareAndSet(false, true)) {
                SessionEvents.emitSessionExpired()
                appScope.launch { preferencesManager.clearSession() }
            }
        }

        return response
    }

    private companion object {
        const val CACHE_WAIT_TIMEOUT_MS = 1_500L

        // 401 tidak boleh menghapus percobaan login/register/reset itu sendiri
        // (tidak ada sesi untuk dihapus, dan error field akan tertutup alur
        // "sesi berakhir"). Hanya endpoint auth asli yang terdaftar: permintaan
        // lain yang membawa token lalu dijawab 401 berarti token tidak valid.
        // Register OTP juga terdaftar karena ketiganya membawa tenant di BODY.
        val AUTH_PATHS = listOf(
            "auth/login", "auth/register", "auth/firebase-login",
            "auth/register/request-otp", "auth/register/verify-otp",
            "auth/register/resend-otp",
            "auth/forgot-password", "auth/verify-reset-code", "auth/reset-password",
            "public/clinics"
        )

        // Login/register membawa atau membentuk ikatan tenant itu sendiri —
        // slug lama tidak boleh ikut terkirim (backend menjawab 403).
        val NO_SLUG_HEADER_PATHS = listOf(
            "auth/login", "auth/register", "auth/firebase-login",
            "auth/register/request-otp", "auth/register/verify-otp",
            "auth/register/resend-otp"
        )
    }
}
