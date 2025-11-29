package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import android.util.Log
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.christopheraldoo.aplikasimonitoringkelas.utils.TokenManager
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.Response
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicReference

// Simplified Auth Interceptor
class AuthInterceptor(private val context: Context) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()
        val token = NetworkUtils.getAuthToken(context)

        val requestBuilder = original.newBuilder()
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")

        if (token != null && token != "Bearer ") {
            requestBuilder.header("Authorization", token)
        }

        return chain.proceed(requestBuilder.build())
    }
}

object RetrofitClient {
    private const val TAG = "RetrofitClient"
    private val selectedBaseUrlRef = AtomicReference<String?>(null)

    private fun ensureResolvedBaseUrl(context: Context): String {
        selectedBaseUrlRef.get()?.let { return it }

        val resolved = NetworkConfig.BaseUrls.getDefault(context)
        selectedBaseUrlRef.set(resolved)
        Log.i(TAG, "Base URL resolved to: $resolved")
        return resolved
    }

    fun markConnectionFailureAndFlipBaseUrl(context: Context) {
        val current = selectedBaseUrlRef.get() ?: NetworkConfig.BaseUrls.getDefault(context)
        val alternative = if (current.startsWith("http://10.0.2.2"))
            NetworkConfig.BaseUrls.getDeviceLanUrl() else NetworkConfig.BaseUrls.getEmulatorUrl()
        selectedBaseUrlRef.set(alternative)
        Log.w(TAG, "Base URL flipped to: $alternative due to connection failure")
    }

    private fun getOkHttpClient(context: Context): OkHttpClient {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        return OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .addInterceptor(AuthInterceptor(context))
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()
    }

    fun getAuthenticatedInstance(context: Context): ApiService {
        val baseUrl = ensureResolvedBaseUrl(context)
        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(getOkHttpClient(context))
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    fun getUnauthenticatedInstance(context: Context): ApiService {
        val baseUrl = ensureResolvedBaseUrl(context)
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    fun createApiService(context: Context): ApiService {
        val baseUrl = ensureResolvedBaseUrl(context)
        val okHttpClient = getOkHttpClient(context)

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }
}
