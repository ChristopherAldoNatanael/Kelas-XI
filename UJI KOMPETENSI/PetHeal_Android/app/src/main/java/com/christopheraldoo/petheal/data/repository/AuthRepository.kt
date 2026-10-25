package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.NetworkInterceptor
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.tasks.await
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.OkHttpClient
import okhttp3.RequestBody.Companion.asRequestBody
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton

sealed class Result<out T> {
    data class Success<T>(val data: T) : Result<T>()

    /**
     * @param code `code` stabil dari backend (mis. firebase_token_expired)
     *             atau fallback `http_<status>` — dipakai penentuan apakah
     *             login Google boleh dicoba ulang dengan token baru.
     */
    data class Error(val message: String, val code: String? = null) : Result<Nothing>()
    object Loading : Result<Nothing>()
}

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager,
    private val firebaseAuth: FirebaseAuth,
    private val networkInterceptor: NetworkInterceptor,
    private val okHttpClient: OkHttpClient,
    private val deviceTokenRepository: DeviceTokenRepository,
    private val notificationRepository: NotificationRepository,
    private val medicalRecordRepository: MedicalRecordRepository
) {
    init {
        // Pre-load token ke cache NetworkInterceptor saat app start
        // agar request pertama tidak perlu baca DataStore (blocking)
    }

    /** Simpan token ke DataStore DAN update cache NetworkInterceptor sekaligus */
    private suspend fun saveTokenAndCache(token: String) {
        preferencesManager.saveAuthToken(token)
        networkInterceptor.updateToken(token)
    }

    /**
     * Buang seluruh cache HTTP (respons API + gambar) milik akun sebelumnya.
     *
     * Cache OkHttp bersifat global per URL, bukan per user. Tanpa pembersihan,
     * respons GET /pets atau /bookings milik akun lama masih bisa tersaji saat
     * perangkat offline / koneksi ngrok putus (interceptor memakai
     * `only-if-cached, max-stale=604800`), sehingga akun baru seolah punya
     * hewan dan riwayat booking akun lama.
     */
    private suspend fun clearHttpCache() {
        withContext(Dispatchers.IO) {
            runCatching { okHttpClient.cache?.evictAll() }
                .onFailure { android.util.Log.w("AuthRepository", "Gagal membersihkan cache HTTP: ${it.message}") }
        }
    }

    /**
     * Hapus identitas Firebase/Google, BUKAN hanya signOut.
     *
     * Firebase mewajibkan login yang masih "recent" untuk delete(). Kalau
     * ditolak (mis. sesi sudah lama), kita tetap signOut supaya token lokal
     * tidak dipakai lagi; baris akun di backend sudah dihapus oleh server.
     * Akibatnya login berikutnya membuat akun baru yang kosong, bukan
     * membuka kembali data lama.
     */
    private suspend fun deleteFirebaseAccountBestEffort() {
        val current = firebaseAuth.currentUser
        if (current != null) {
            try {
                current.delete().await()
            } catch (e: Exception) {
                android.util.Log.w(
                    "AuthRepository",
                    "Firebase user delete gagal (mungkin perlu login ulang): ${e.message}"
                )
            }
        }
        runCatching { firebaseAuth.signOut() }
    }

    /**
     * PHASE 7: persist the backend-authoritative tenant binding from
     * `user.clinic` (id/name/slug/logo/color) and keep the X-Clinic-Slug
     * header in sync. App is user/pet-owner only: non-user roles are
     * rejected client-side (server remains the enforcer).
     */
    private suspend fun persistClinicBinding(user: User) {
        val clinic = user.clinic
        preferencesManager.saveClinic(
            id = clinic?.id,
            name = clinic?.name,
            slug = clinic?.slug,
            logoUrl = clinic?.logoUrl,
            color = clinic?.primaryColor,
            address = null
        )
        networkInterceptor.updateClinicSlug(clinic?.slug)
    }

    private suspend fun rejectNonUserRole(role: String?): Result<AuthData> {
        networkInterceptor.updateToken(null)
        return Result.Error(
            "Akun ini terdaftar sebagai \"$role\". Aplikasi ini hanya untuk pemilik hewan."
        )
    }

    private suspend fun saveAuthProvider(provider: String) {
        preferencesManager.saveAuthProvider(provider)
    }

    /**
     * Simpan sesi + profil setelah server mengembalikan token. Dipakai SEMUA
     * jalur masuk (login email, login/daftar Google, verifikasi OTP) supaya
     * urutan penyimpanannya satu dan tidak berbeda-beda per layar.
     */
    private suspend fun completeSession(
        authData: AuthData,
        provider: String,
        nameFallback: String = ""
    ): Result<AuthData> {
        if (authData.user.role != null && authData.user.role != "user") {
            return rejectNonUserRole(authData.user.role)
        }
        saveTokenAndCache(authData.token)
        persistClinicBinding(authData.user)
        saveAuthProvider(provider)
        preferencesManager.saveUserInfo(
            userId = authData.user.id ?: 0,
            email = authData.user.email ?: "",
            name = authData.user.name ?: nameFallback,
            photo = authData.user.photo
        )
        return Result.Success(authData)
    }

    private data class ApiErrorDetails(val message: String, val code: String?)

    /**
     * Parse errorBody SEKALI (stream hanya bisa dibaca sekali) menjadi
     * message + code. Code diambil dari field `code` backend; bila tidak
     * ada, fallback `http_<status>` agar caller tetap tahu kelas error-nya.
     */
    private fun errorDetailsFrom(response: retrofit2.Response<*>?, fallback: String): ApiErrorDetails {
        val rawErrorBody = runCatching { response?.errorBody()?.string() }.getOrNull()?.trim().orEmpty()
        var message: String? = null
        var jsonCode: String? = null

        if (rawErrorBody.isNotBlank()) {
            val parsedMessage = runCatching {
                val json = JSONObject(rawErrorBody)
                jsonCode = json.optString("code").takeIf { it.isNotBlank() }
                when {
                    json.has("message") -> json.optString("message")
                    json.has("error") -> json.optString("error")
                    json.has("errors") -> json.optJSONObject("errors")
                        ?.keys()
                        ?.asSequence()
                        ?.mapNotNull { key ->
                            json.optJSONObject("errors")
                                ?.optJSONArray(key)
                                ?.optString(0)
                        }
                        ?.firstOrNull()
                    else -> null
                }
            }.getOrNull()

            message = if (!parsedMessage.isNullOrBlank()) parsedMessage else rawErrorBody
        }

        if (message.isNullOrBlank()) {
            message = runCatching {
                when (val body = response?.body()) {
                    is AuthResponse -> body.message
                    is MessageResponse -> body.message
                    else -> null
                }
            }.getOrNull()?.takeIf { it.isNotBlank() }
        }

        if (message.isNullOrBlank()) message = fallback

        // Throttle Laravel (429) memakai teks framework → ganti pesan ramah.
        // Pesan 429 yang spesifik dari backend (mis. "sisa percobaan OTP" atau
        // "minta kode baru") DIPERTAHANKAN supaya user tahu langkah berikutnya;
        // menunggu saja tidak selalu menyelesaikan masalah.
        val isGenericThrottle = rawErrorBody.isBlank() ||
            message.orEmpty().startsWith("Too Many Attempts", ignoreCase = true)
        if (response?.code() == 429 && isGenericThrottle) {
            message = "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."
        }

        val code = jsonCode ?: response?.code()
            ?.takeIf { it in 400..599 }
            ?.let { "http_$it" }

        return ApiErrorDetails(message, code)
    }

    private fun errorMessageFrom(response: retrofit2.Response<*>?, fallback: String): String =
        errorDetailsFrom(response, fallback).message

    private fun errorResultFrom(response: retrofit2.Response<*>?, fallback: String): Result.Error =
        errorDetailsFrom(response, fallback).let { Result.Error(it.message, it.code) }

    private fun networkError(e: Exception): Result.Error {
        // Detail teknis hanya ke log; UI dapat pesan terpusat yang
        // membedakan DNS / timeout / server tidak terjangkau.
        android.util.Log.e("AuthRepository", "Network failure: ${e.message}", e)
        return Result.Error(
            userFriendlyExceptionMessage(e),
            "network_error"
        )
    }

    /** Backend menolak token Firebase → boleh retry sekali dengan token baru. */
    private fun Result.Error.isFirebaseTokenRejection(): Boolean =
        code?.startsWith("firebase_token") == true || code == "http_401"

    /** Ambil ID token Firebase SEGAR (force refresh) dari sesi Google aktif. */
    private suspend fun freshFirebaseIdToken(): String? {
        val user = firebaseAuth.currentUser ?: return null
        return try {
            user.getIdToken(true).await()?.token
        } catch (e: Exception) {
            android.util.Log.w("AuthRepository", "Gagal refresh ID token: ${e.message}")
            null
        }
    }

    /**
     * Jalankan login/register Google; kalau server menolak token-nya,
     * ulangi SEKALI dengan token hasil force-refresh (menutup kasus token
     * basi / selisih jam perangkat vs server).
     */
    private suspend fun withFreshTokenRetry(
        idToken: String,
        attempt: suspend (String) -> Result<AuthData>
    ): Result<AuthData> {
        val first = attempt(idToken)
        if (first !is Result.Error || !first.isFirebaseTokenRejection()) return first

        val freshToken = freshFirebaseIdToken()
        if (freshToken == null || freshToken == idToken) return first
        return attempt(freshToken)
    }

    suspend fun loginWithEmailPassword(
        email: String,
        password: String,
        fcmToken: String?,
        clinicSlug: String? = null
    ): Result<AuthData> {
        return try {
            val response = apiService.login(
                EmailPasswordRequest(
                    email = email,
                    password = password,
                    fcmToken = fcmToken,
                    deviceType = "android",
                    clinicSlug = clinicSlug
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    completeSession(authData, PreferencesManager.AUTH_PROVIDER_EMAIL_PASSWORD)
                } else {
                    Result.Error("Respons server tidak lengkap. Coba lagi.", "invalid_response")
                }
            } else {
                errorResultFrom(response, "Login gagal")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Pendaftaran email langkah 1: minta kode OTP ke email pendaftar.
     * Akun BELUM dibuat di sini — backend baru membuatnya di
     * [verifyRegisterOtp] setelah kode diverifikasi.
     */
    suspend fun requestRegisterOtp(
        name: String,
        email: String,
        password: String,
        clinicSlug: String? = null
    ): Result<OtpSentData> {
        return try {
            val response = apiService.requestRegisterOtp(
                RegisterOtpRequest(
                    name = name,
                    email = email,
                    password = password,
                    clinicSlug = clinicSlug
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: OtpSentData(email = email))
            } else {
                errorResultFrom(response, "Gagal mengirim kode OTP")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /** Kirim ulang kode pendaftaran (payload form masih tersimpan server). */
    suspend fun resendRegisterOtp(email: String): Result<Unit> {
        return try {
            val response = apiService.resendRegisterOtp(ResendOtpRequest(email = email))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                errorResultFrom(response, "Gagal mengirim ulang kode")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Pendaftaran email langkah 2: verifikasi kode → akun dibuat server dan
     * token dikembalikan. Sesi disimpan lewat jalur yang sama dengan login.
     */
    suspend fun verifyRegisterOtp(
        email: String,
        code: String,
        fcmToken: String?
    ): Result<AuthData> {
        return try {
            val response = apiService.verifyRegisterOtp(
                VerifyRegisterOtpRequest(
                    email = email,
                    code = code,
                    fcmToken = fcmToken,
                    deviceType = "android"
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    completeSession(authData, PreferencesManager.AUTH_PROVIDER_EMAIL_PASSWORD)
                } else {
                    Result.Error("Respons server tidak lengkap. Coba lagi.", "invalid_response")
                }
            } else {
                errorResultFrom(response, "Kode OTP tidak valid")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Login Google. Bila server menolak ID token (401 / kedaluwarsa /
     * selisih jam), token dipaksa-refresh lalu dicoba ulang SEKALI.
     */
    suspend fun loginWithGoogle(idToken: String, fcmToken: String?, clinicSlug: String? = null): Result<AuthData> =
        withFreshTokenRetry(idToken) { token -> attemptFirebaseLogin(token, fcmToken, clinicSlug) }

    private suspend fun attemptFirebaseLogin(idToken: String, fcmToken: String?, clinicSlug: String?): Result<AuthData> {
        return try {
            val response = apiService.firebaseLogin(
                LoginRequest(
                    idToken = idToken,
                    fcmToken = fcmToken,
                    deviceType = "android",
                    clinicSlug = clinicSlug
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    completeSession(authData, PreferencesManager.AUTH_PROVIDER_GOOGLE)
                } else {
                    Result.Error("Respons server tidak lengkap. Coba lagi.", "invalid_response")
                }
            } else {
                errorResultFrom(response, "Login gagal")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Register Google — retry token-baru yang sama dengan [loginWithGoogle].
     */
    suspend fun registerWithGoogle(idToken: String, name: String, phone: String?, fcmToken: String?, clinicSlug: String? = null): Result<AuthData> =
        withFreshTokenRetry(idToken) { token -> attemptFirebaseRegister(token, name, phone, fcmToken, clinicSlug) }

    private suspend fun attemptFirebaseRegister(idToken: String, name: String, phone: String?, fcmToken: String?, clinicSlug: String?): Result<AuthData> {
        return try {
            val response = apiService.firebaseRegister(
                FirebaseRegisterRequest(
                    idToken = idToken,
                    name = name,
                    phone = phone,
                    fcmToken = fcmToken,
                    deviceType = "android",
                    clinicSlug = clinicSlug
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    completeSession(authData, PreferencesManager.AUTH_PROVIDER_GOOGLE, name)
                } else {
                    Result.Error("Respons server tidak lengkap. Coba lagi.", "invalid_response")
                }
            } else {
                errorResultFrom(response, "Pendaftaran gagal")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * PHASE 7: permanently delete the current account (Profile → Hapus Akun).
     * Backend cascades the user's pets/bookings/records/tokens server-side.
     * Local session is cleared only AFTER the server confirms, so a failed
     * request never logs the user out by accident.
     *
     * PHASE 7 fix: the stored clinic choice is cleared too. The binding
     * belonged to the deleted account — keeping it made the next Google
     * login silently re-bind to the OLD clinic (auto-provisioned with the
     * stale slug) and skip the setup flow. Normal logout keeps the clinic.
     */
    suspend fun deleteAccountAndLogout(): Result<Unit> {
        return try {
            val response = apiService.deleteAccount()
            if (response.isSuccessful && response.body()?.success == true) {
                // Hapus akun Firebase/Google juga (best effort) supaya ini
                // bukan sekadar "sign out". Kalau Firebase menolak karena
                // sesi lama, fallback signOut tetap dilakukan.
                deleteFirebaseAccountBestEffort()
                networkInterceptor.updateToken(null)
                networkInterceptor.updateClinicSlug(null)
                runCatching { notificationRepository.clearLocal() }
                medicalRecordRepository.clearCaches()
                // Respons API akun lama tidak boleh tersaji ke akun berikutnya.
                clearHttpCache()
                preferencesManager.clearSession()
                preferencesManager.clearClinic()
                Result.Success(Unit)
            } else {
                Result.Error(errorMessageFrom(response, "Gagal menghapus akun"))
            }
        } catch (e: Exception) {
            android.util.Log.e("AuthRepository", "Delete account failed: ${e.message}", e)
            Result.Error("Gagal menghapus akun: ${e.message ?: "Unknown error"}")
        }
    }

    /**
     * Kirim kode konfirmasi hapus akun ke email pemilik akun.
     * Akun BELUM dihapus di sini — lihat [verifyDeleteAccountOtp].
     */
    suspend fun requestDeleteAccountOtp(): Result<OtpSentResponse> {
        return try {
            val response = apiService.requestDeleteAccountOtp()
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body)
            } else {
                errorResultFrom(response, "Gagal mengirim kode konfirmasi")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Verifikasi kode hapus akun. Sukses berarti server membuka jendela
     * 15 menit untuk DELETE /auth/account; pemanggilan hapus tetap terpisah
     * agar kegagalan jaringan tidak menghapus data setengah jalan.
     */
    suspend fun verifyDeleteAccountOtp(code: String): Result<Unit> {
        return try {
            val response = apiService.verifyDeleteAccountOtp(VerifyOtpCodeRequest(code))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                errorResultFrom(response, "Kode konfirmasi tidak valid")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /**
     * Kirim kode konfirmasi ke ALAMAT BARU. Email baru baru disimpan setelah
     * kode diverifikasi, jadi salah ketik alamat tidak mengubah akun.
     */
    suspend fun requestEmailChangeOtp(email: String): Result<OtpSentResponse> {
        return try {
            val response = apiService.requestEmailChangeOtp(EmailChangeOtpRequest(email))
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body)
            } else {
                errorResultFrom(response, "Gagal mengirim kode konfirmasi")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    /** Verifikasi kode ganti email; sukses mengembalikan user dengan email baru. */
    suspend fun verifyEmailChangeOtp(email: String, code: String): Result<User> {
        return try {
            val response = apiService.verifyEmailChangeOtp(
                EmailChangeVerifyOtpRequest(email = email, code = code)
            )
            val body = response.body()
            val user = body?.data
            if (response.isSuccessful && body?.success == true && user != null) {
                Result.Success(user)
            } else {
                errorResultFrom(response, "Kode konfirmasi tidak valid")
            }
        } catch (e: Exception) {
            networkError(e)
        }
    }

    suspend fun logout(fcmToken: String?): Result<Unit> {
        return try {
            val deviceToken = fcmToken ?: preferencesManager.fcmToken.first()
            val token = preferencesManager.authToken.first()
            if (!deviceToken.isNullOrBlank()) {
                deviceTokenRepository.removeDeviceToken(deviceToken)
            }
            if (token != null) {
                apiService.logout()
            }
            // Clear cached token di NetworkInterceptor
            networkInterceptor.updateToken(null)
            // Sign out from Firebase (logout TIDAK menghapus akun Google)
            firebaseAuth.signOut()
            // Clear locally stored notifications for the current account
            notificationRepository.clearLocal()
            // PHASE 7: user-scoped caches must not leak into the next account.
            medicalRecordRepository.clearCaches()
            // Cache HTTP juga milik akun ini — jangan dipakai akun berikutnya.
            clearHttpCache()
            // Clear only the session data; keep auth provider for login UX
            preferencesManager.clearSession()

            Result.Success(Unit)
        } catch (e: Exception) {
            android.util.Log.e("AuthRepository", "Logout API call failed: ${e.message}", e)
            val deviceToken = runCatching { preferencesManager.fcmToken.first() }.getOrNull()
            if (!deviceToken.isNullOrBlank()) {
                runCatching { deviceTokenRepository.removeDeviceToken(deviceToken) }
            }
            preferencesManager.clearSession()
            networkInterceptor.updateToken(null)
            firebaseAuth.signOut()
            runCatching { notificationRepository.clearLocal() }
            medicalRecordRepository.clearCaches()
            clearHttpCache()

            Result.Error("Failed to logout from server: ${e.message ?: "Unknown error"}")
        }
    }

    suspend fun getProfile(): Result<User> {
        return try {
            val response = apiService.getProfile()

            if (response.isSuccessful && response.body()?.success == true) {
                val user = response.body()?.data
                if (user != null) {
                    Result.Success(user)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(response.body()?.message ?: "Failed to get profile")
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
    
    suspend fun updateProfile(name: String, phone: String?): Result<User> {
        return try {
            val response = apiService.updateProfile(
                mapOf("name" to name, "phone" to (phone ?: ""))
            )

            if (response.isSuccessful && response.body()?.success == true) {
                val user = response.body()?.data
                if (user != null) {
                    Result.Success(user)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(response.body()?.message ?: "Failed to update profile")
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    /**
     * Upload foto profil (dipakai badge kamera di ProfileScreen).
     * File HARUS sudah dikompres di sisi UI (≤4096KB, lihat ImageCompressor)
     * karena backend menolak field `photo` di atas batas tersebut.
     * Sukses → cache DataStore ikut diperbarui agar Home/Profile langsung
     * menampilkan foto baru tanpa reload manual.
     */
    suspend fun uploadProfilePhoto(photoFile: java.io.File): Result<User> {
        return try {
            if (!photoFile.exists()) return Result.Error("File foto tidak ditemukan")
            val photoPart = okhttp3.MultipartBody.Part.createFormData(
                "photo", photoFile.name,
                photoFile.asRequestBody("image/*".toMediaTypeOrNull())
            )
            val response = apiService.uploadProfilePhoto(photoPart)
            if (response.isSuccessful && response.body()?.success == true) {
                val user = response.body()?.data
                if (user != null) {
                    preferencesManager.saveUserInfo(
                        userId = user.id ?: preferencesManager.userId.first()?.toIntOrNull() ?: 0,
                        email = user.email ?: preferencesManager.userEmail.first().orEmpty(),
                        name = user.name ?: preferencesManager.userName.first().orEmpty(),
                        photo = user.photo
                    )
                    Result.Success(user)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(errorMessageFrom(response, "Gagal mengunggah foto profil"))
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
    
    suspend fun isLoggedIn(): Boolean {
        return preferencesManager.authToken.first() != null
    }
    
    suspend fun getCurrentUserId(): Int? {
        return preferencesManager.userId.first()?.toIntOrNull()
    }

    /** PHASE 7: remove the current account (used to discard a clinic-less
     * account before re-registering it bound to a clinic). */
    suspend fun deleteCurrentAccount(): Result<Unit> {
        return try {
            val response = apiService.deleteAccount()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                Result.Error(errorMessageFrom(response, "Failed to remove account"))
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    /**
     * PHASE 7: bind a clinic-less Google account to [slug].
     *
     * The backend has no "attach clinic later" endpoint, and a null-clinic
     * account gets 403 on everything — so the repair is: delete the empty
     * account, then register again with the slug (nothing of value can exist
     * on it yet). Returns the fresh bound [AuthData] and persists it.
     */
    suspend fun reregisterGoogleAccount(slug: String): Result<AuthData> {
        // 1. Drop the unusable account (server revokes its tokens).
        when (val dropped = deleteCurrentAccount()) {
            is Result.Error -> return Result.Error(dropped.message)
            else -> Unit
        }
        networkInterceptor.updateToken(null)
        clearHttpCache()
        medicalRecordRepository.clearCaches()
        runCatching { notificationRepository.clearLocal() }

        // 2. Fresh Firebase ID token (user is still signed in to Google).
        val firebaseUser = firebaseAuth.currentUser
            ?: return Result.Error("Sesi Google berakhir. Silakan masuk kembali.")
        val idToken = try {
            firebaseUser.getIdToken(true).await()?.token
        } catch (e: Exception) { null }
            ?: return Result.Error("Gagal mengambil token Google. Coba lagi.")

        // 3. Register bound from the start.
        val name = runCatching { preferencesManager.userName.first() }.getOrNull()
            ?.takeIf { it.isNotBlank() }
            ?: firebaseUser.displayName
            ?: "Pengguna"
        val fcmToken = runCatching { preferencesManager.fcmToken.first() }.getOrNull()
        return when (val result = registerWithGoogle(idToken, name, null, fcmToken, slug)) {
            is Result.Success -> result
            is Result.Error -> Result.Error(result.message)
            else -> Result.Error("Pendaftaran ulang gagal. Coba lagi.")
        }
    }

    suspend fun requestForgotPassword(email: String): Result<Unit> {        return try {
            val response = apiService.forgotPassword(ForgotPasswordRequest(email = email))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                Result.Error(errorMessageFrom(response, "Failed to send reset code"))
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    /** PHASE 7: complete the reset flow (endpoint was declared but unwired). */
    suspend fun verifyResetCode(email: String, code: String): Result<Unit> {
        return try {
            val response = apiService.verifyResetCode(VerifyResetCodeRequest(email, code))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                Result.Error(errorMessageFrom(response, "Invalid reset code"))
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun resetPassword(email: String, code: String, password: String): Result<Unit> {
        return try {
            val response = apiService.resetPassword(
                ResetPasswordRequest(email, code, password, password)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(Unit)
            } else {
                Result.Error(errorMessageFrom(response, "Failed to reset password"))
            }
        } catch (e: Exception) {
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
}
