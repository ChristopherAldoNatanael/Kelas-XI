package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.NetworkInterceptor
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.tasks.await
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
    private val networkInterceptor: NetworkInterceptor
) {
    init {
        // Pre-load token ke cache NetworkInterceptor saat app start
        // agar request pertama tidak perlu baca DataStore (blocking)
    }

    /** Simpan token ke DataStore DAN update cache NetworkInterceptor sekaligus */
    private suspend fun saveTokenAndCache(token: String) {
        preferencesManager.saveAuthToken(token)
        networkInterceptor.updateToken(token)
    }    suspend fun loginWithEmailPassword(email: String, password: String, fcmToken: String?): Result<AuthData> {
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
                    saveTokenAndCache(authData.token)
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
                Result.Error(response.body()?.message ?: "Login failed")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }

    suspend fun registerWithEmailPassword(email: String, password: String, name: String, fcmToken: String?): Result<AuthData> {
        return try {
            val response = apiService.register(
                EmailRegisterRequest(
                    name = name,
                    email = email,
                    password = password,
                    phone = null,
                    fcmToken = fcmToken,
                    deviceType = "android"
                )
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    saveTokenAndCache(authData.token)
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
                Result.Error(response.body()?.message ?: "Registration failed")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }

    suspend fun loginWithGoogle(idToken: String, fcmToken: String?): Result<AuthData> {
        return try {
            val response = apiService.firebaseLogin(
                LoginRequest(
                    idToken = idToken,
                    fcmToken = fcmToken,
                    deviceType = "android"
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    saveTokenAndCache(authData.token)
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
                Result.Error(response.body()?.message ?: "Login failed")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }
      suspend fun registerWithGoogle(idToken: String, name: String, phone: String?, fcmToken: String?): Result<AuthData> {
        return try {
            val response = apiService.firebaseRegister(
                FirebaseRegisterRequest(
                    idToken = idToken,
                    name = name,
                    phone = phone,
                    fcmToken = fcmToken,
                    deviceType = "android"
                )
            )
            if (response.isSuccessful && response.body()?.success == true) {
                val authData = response.body()?.data
                if (authData != null) {
                    saveTokenAndCache(authData.token)
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
                Result.Error(response.body()?.message ?: "Registration failed")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Network error")
        }
    }

    suspend fun logout(fcmToken: String?): Result<Unit> {
        return try {
            val token = preferencesManager.authToken.first()
            if (token != null) {
                apiService.logout()
            }
            // Clear cached token di NetworkInterceptor
            networkInterceptor.updateToken(null)
            // Sign out from Firebase
            firebaseAuth.signOut()
            // Clear local data
            preferencesManager.clearAll()

            Result.Success(Unit)
        } catch (e: Exception) {
            android.util.Log.e("AuthRepository", "Logout API call failed: ${e.message}", e)
            preferencesManager.clearAll()
            networkInterceptor.updateToken(null)
            firebaseAuth.signOut()

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
}
