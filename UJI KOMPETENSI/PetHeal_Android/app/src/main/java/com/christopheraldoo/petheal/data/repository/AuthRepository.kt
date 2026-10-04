package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.NetworkInterceptor
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.tasks.await
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton

sealed class Result<out T> {
    data class Success<T>(val data: T) : Result<T>()
    data class Error(val message: String) : Result<Nothing>()
    object Loading : Result<Nothing>()
}

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager,
    private val firebaseAuth: FirebaseAuth,
    private val networkInterceptor: NetworkInterceptor,
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

    private fun errorMessageFrom(response: retrofit2.Response<*>?, fallback: String): String {
        val rawErrorBody = runCatching { response?.errorBody()?.string() }.getOrNull()?.trim().orEmpty()
        if (rawErrorBody.isNotBlank()) {
            val parsedMessage = runCatching {
                val json = JSONObject(rawErrorBody)
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

            if (!parsedMessage.isNullOrBlank()) return parsedMessage
            return rawErrorBody
        }

        val bodyMessage = runCatching {
            when (val body = response?.body()) {
                is AuthResponse -> body.message
                is MessageResponse -> body.message
                else -> null
            }
        }.getOrNull()

        return bodyMessage?.takeIf { it.isNotBlank() } ?: fallback
    }

    suspend fun loginWithEmailPassword(email: String, password: String, fcmToken: String?): Result<AuthData> {
        return try {
            val response = apiService.login(
                EmailPasswordRequest(
                    email = email,
                    password = password,
                    fcmToken = fcmToken,
                    deviceType = "android"
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    if (authData.user.role != null && authData.user.role != "user") {
                        return rejectNonUserRole(authData.user.role)
                    }
                    saveTokenAndCache(authData.token)
                    persistClinicBinding(authData.user)
                    saveAuthProvider(PreferencesManager.AUTH_PROVIDER_EMAIL_PASSWORD)
                    preferencesManager.saveUserInfo(
                        userId = authData.user.id ?: 0,
                        email = authData.user.email ?: "",
                        name = authData.user.name ?: "",
                        photo = authData.user.photo
                    )
                    Result.Success(authData)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(errorMessageFrom(response, "Login failed"))
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }

    suspend fun registerWithEmailPassword(
        email: String,
        password: String,
        name: String,
        fcmToken: String?,
        clinicSlug: String? = null
    ): Result<AuthData> {
        return try {
            val response = apiService.register(
                EmailRegisterRequest(
                    name = name,
                    email = email,
                    password = password,
                    phone = null,
                    fcmToken = fcmToken,
                    deviceType = "android",
                    clinicSlug = clinicSlug
                )
            )

            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    if (authData.user.role != null && authData.user.role != "user") {
                        return rejectNonUserRole(authData.user.role)
                    }
                    saveTokenAndCache(authData.token)
                    persistClinicBinding(authData.user)
                    saveAuthProvider(PreferencesManager.AUTH_PROVIDER_EMAIL_PASSWORD)
                    preferencesManager.saveUserInfo(
                        userId = authData.user.id ?: 0,
                        email = authData.user.email ?: "",
                        name = authData.user.name ?: name,
                        photo = authData.user.photo
                    )
                    Result.Success(authData)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(errorMessageFrom(response, "Registration failed"))
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }

    suspend fun loginWithGoogle(idToken: String, fcmToken: String?, clinicSlug: String? = null): Result<AuthData> {
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
                    if (authData.user.role != null && authData.user.role != "user") {
                        return rejectNonUserRole(authData.user.role)
                    }
                    saveTokenAndCache(authData.token)
                    persistClinicBinding(authData.user)
                    saveAuthProvider(PreferencesManager.AUTH_PROVIDER_GOOGLE)
                    preferencesManager.saveUserInfo(
                        userId = authData.user.id ?: 0,
                        email = authData.user.email ?: "",
                        name = authData.user.name ?: "",
                        photo = authData.user.photo
                    )
                    Result.Success(authData)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(errorMessageFrom(response, "Login failed"))
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }
      suspend fun registerWithGoogle(idToken: String, name: String, phone: String?, fcmToken: String?, clinicSlug: String? = null): Result<AuthData> {
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
                    if (authData.user.role != null && authData.user.role != "user") {
                        return rejectNonUserRole(authData.user.role)
                    }
                    saveTokenAndCache(authData.token)
                    persistClinicBinding(authData.user)
                    saveAuthProvider(PreferencesManager.AUTH_PROVIDER_GOOGLE)
                    preferencesManager.saveUserInfo(
                        userId = authData.user.id ?: 0,
                        email = authData.user.email ?: "",
                        name = authData.user.name ?: name,
                        photo = authData.user.photo
                    )
                    Result.Success(authData)
                } else {
                    Result.Error("Invalid response")
                }
            } else {
                Result.Error(errorMessageFrom(response, "Registration failed"))
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
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
                networkInterceptor.updateToken(null)
                networkInterceptor.updateClinicSlug(null)
                firebaseAuth.signOut()
                runCatching { notificationRepository.clearLocal() }
                medicalRecordRepository.clearCaches()
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
            // Sign out from Firebase
            firebaseAuth.signOut()
            // Clear locally stored notifications for the current account
            notificationRepository.clearLocal()
            // PHASE 7: user-scoped caches must not leak into the next account.
            medicalRecordRepository.clearCaches()
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
            Result.Error(e.message ?: "Network error")
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
            Result.Error(e.message ?: "Network error")
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
            Result.Error(e.message ?: "Network error")
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
            Result.Error(e.message ?: "Network error")
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
            Result.Error(e.message ?: "Network error")
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
            Result.Error(e.message ?: "Network error")
        }
    }
}
