package com.christopheraldoo.petheal.data.remote

import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.local.SessionEvents
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response
import javax.inject.Inject

class NetworkInterceptor @Inject constructor(
    private val preferencesManager: PreferencesManager
) : Interceptor {

    @Volatile private var cachedToken: String? = null
    @Volatile private var cachedClinicSlug: String? = null
    @Volatile private var clinicSlugLoaded = false

    fun updateToken(token: String?) {
        cachedToken = token
    }

    /** Called after clinic pick / login so the header follows immediately. */
    fun updateClinicSlug(slug: String?) {
        cachedClinicSlug = slug
        clinicSlugLoaded = true
    }

    companion object {
        // 401 must not wipe a login/register attempt itself (nothing to wipe
        // and it would mask field errors). Paths here never trigger the
        // session-expired flow.
        private val AUTH_PATHS = listOf(
            "auth/login", "auth/register", "auth/register-direct",
            "auth/firebase-login",
            "auth/forgot-password", "auth/verify-reset-code", "auth/reset-password",
            "public/clinics", "payment-methods", "services", "health", "midtrans/webhook"
        )

        // The login/register calls carry (or establish) the binding itself —
        // a stale stored slug must never break them (backend 403 on mismatch).
        // Tenant binding travels in the request BODY (clinic_slug) instead.
        private val NO_SLUG_HEADER_PATHS = listOf(
            "auth/login", "auth/register", "auth/register-direct", "auth/firebase-login"
        )
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()

        val token = cachedToken ?: runBlocking { preferencesManager.authToken.first() }
            .also { cachedToken = it }
        if (!clinicSlugLoaded) {
            cachedClinicSlug = runBlocking { preferencesManager.clinicSlug.first() }
            clinicSlugLoaded = true
        }

        val builder = originalRequest.newBuilder()
        if (!token.isNullOrBlank()) {
            builder.header("Authorization", "Bearer $token")
        }
        // PHASE 7: tenant hint. Backend validates it when present (403 on
        // mismatch); anonymous catalog calls additionally send ?clinic_slug=.
        val path = originalRequest.url.encodedPath
        val skipSlugHeader = NO_SLUG_HEADER_PATHS.any { path.contains(it) }
        if (!skipSlugHeader && !cachedClinicSlug.isNullOrBlank()) {
            builder.header("X-Clinic-Slug", cachedClinicSlug!!)
        }

        val response = chain.proceed(builder.build())

        // PHASE 7 (§21): expired/invalid token ends the session exactly once:
        // clear persisted auth + RAM cache and notify navigation. No retry
        // loop (request is returned as-is), no logout of auth attempts.
        val isAuthPath = AUTH_PATHS.any { path.contains(it) }
        if (response.code == 401 && !token.isNullOrBlank() && !isAuthPath) {
            cachedToken = null
            runBlocking { preferencesManager.clearSession() }
            SessionEvents.emitSessionExpired()
        } else if (response.code == 401 && !token.isNullOrBlank()) {
            cachedToken = null
        }

        return response
    }
}
