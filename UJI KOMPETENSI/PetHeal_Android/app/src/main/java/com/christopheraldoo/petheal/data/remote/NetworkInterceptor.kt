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

    @Volatile private var cachedToken: String? = null

    fun updateToken(token: String?) {
        cachedToken = token
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()

        val token = cachedToken ?: runBlocking { preferencesManager.authToken.first() }
            .also { cachedToken = it }

        val request = if (!token.isNullOrBlank()) {
            originalRequest.newBuilder()
                .header("Authorization", "Bearer $token")
                .build()
        } else {
            originalRequest
        }

        val response = chain.proceed(request)

        // If server rejects the token, invalidate cache so next request
        // re-reads from DataStore or triggers re-login flow
        if (response.code == 401 && !token.isNullOrBlank()) {
            cachedToken = null
        }

        return response
    }
}
