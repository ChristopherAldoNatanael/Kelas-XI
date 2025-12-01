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
import java.io.EOFException
import java.io.IOException
import java.net.SocketTimeoutException
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

/**
 * Retry Interceptor - Automatically retries failed requests
 * Handles: EOFException, SocketTimeoutException, and other IO errors
 */
class RetryInterceptor(private val maxRetries: Int = 3) : Interceptor {
    companion object {
        private const val TAG = "RetryInterceptor"
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        var lastException: IOException? = null
        
        for (attempt in 1..maxRetries) {
            try {
                val response = chain.proceed(request)
                
                // If successful, return immediately
                if (response.isSuccessful) {
                    return response
                }
                
                // For server errors (5xx), retry
                if (response.code in 500..599 && attempt < maxRetries) {
                    Log.w(TAG, "Server error ${response.code}, attempt $attempt/$maxRetries, retrying...")
                    response.close()
                    Thread.sleep(1000L * attempt) // Exponential backoff
                    continue
                }
                
                return response
            } catch (e: EOFException) {
                Log.w(TAG, "EOFException on attempt $attempt/$maxRetries: ${e.message}")
                lastException = IOException("EOF Error", e)
                if (attempt < maxRetries) {
                    Thread.sleep(1000L * attempt)
                }
            } catch (e: SocketTimeoutException) {
                Log.w(TAG, "Timeout on attempt $attempt/$maxRetries: ${e.message}")
                lastException = e
                if (attempt < maxRetries) {
                    Thread.sleep(500L * attempt)
                }
            } catch (e: IOException) {
                Log.w(TAG, "IOException on attempt $attempt/$maxRetries: ${e.message}")
                lastException = e
                if (attempt < maxRetries) {
                    Thread.sleep(1000L * attempt)
                }
            }
        }
        
        throw lastException ?: IOException("Request failed after $maxRetries attempts")
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
            level = HttpLoggingInterceptor.Level.BASIC // Reduced from BODY to prevent memory issues
        }

        return OkHttpClient.Builder()
            .addInterceptor(RetryInterceptor(maxRetries = 3)) // Auto-retry on EOF/timeout
            .addInterceptor(loggingInterceptor)
            .addInterceptor(AuthInterceptor(context))
            .connectTimeout(45, TimeUnit.SECONDS)  // Increased for slow connections
            .readTimeout(90, TimeUnit.SECONDS)     // Significantly increased for large JSON responses
            .writeTimeout(45, TimeUnit.SECONDS)    // Increased for large uploads
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
