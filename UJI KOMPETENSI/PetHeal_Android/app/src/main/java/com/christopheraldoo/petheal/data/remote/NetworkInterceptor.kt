package com.christopheraldoo.petheal.data.remote

import com.christopheraldoo.petheal.data.local.PreferencesManager
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response
import javax.inject.Inject

class NetworkInterceptor @Inject constructor(
    private val preferencesManager: PreferencesManager
) : Interceptor {

    // Cache token di memory agar tidak baca DataStore setiap request
    @Volatile private var cachedToken: String? = null

    fun updateToken(token: String?) {
        cachedToken = token
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()

        // Gunakan cached token dahulu; fallback ke DataStore hanya jika belum ada
        val token = cachedToken ?: runBlocking { preferencesManager.authToken.first() }
            .also { cachedToken = it }

        return if (!token.isNullOrBlank()) {
            val newRequest = originalRequest.newBuilder()
                .header("Authorization", "Bearer $token")
                .build()
            chain.proceed(newRequest)
        } else {
            chain.proceed(originalRequest)
        }
    }
}
