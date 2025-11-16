package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.christopheraldoo.aplikasimonitoringkelas.utils.TokenManager
import com.google.gson.GsonBuilder
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

import android.util.Log
import okhttp3.*
import okhttp3.Cache
import okhttp3.ConnectionPool
import okhttp3.Response
import java.io.File
import java.io.IOException
import java.util.concurrent.atomic.AtomicReference
import java.util.concurrent.atomic.AtomicLong
import java.util.concurrent.ConcurrentHashMap
import kotlin.math.min
import kotlin.random.Random

class AuthInterceptor(private val context: Context) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()
        val token = NetworkUtils.getAuthToken(context)

        return if (token != null && token != "Bearer ") {
            val requestBuilder = original.newBuilder()
                .header("Authorization", token)
                .header("Content-Type", "application/json")
                .header("Accept", "application/json")
                .method(original.method, original.body)

            chain.proceed(requestBuilder.build())
        } else {
            chain.proceed(original)
        }
    }
}

class CacheInterceptor : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val originalResponse = chain.proceed(request)

        if (request.method == "GET") {
            val cacheControl = originalResponse.header("Cache-Control")
            if (cacheControl == null || cacheControl.contains("no-store")) {
                return originalResponse.newBuilder()
                    .header("Cache-Control", "public, max-age=60")
                    .build()
            }
        }

        return originalResponse
    }
}

class RateLimitingInterceptor : Interceptor {
    private val lastRequestTime = AtomicLong(0)
    private val minIntervalMs = 200L // Allow 1 request per 200ms (5 requests per second) - stricter rate limiting
    private val requestCount = AtomicLong(0)

    override fun intercept(chain: Interceptor.Chain): Response {
        val currentTime = System.currentTimeMillis()
        val lastTime = lastRequestTime.get()
        val timeSinceLastRequest = currentTime - lastTime
        val count = requestCount.incrementAndGet()

        Log.d("RateLimitingInterceptor", "Request #$count - Time since last: ${timeSinceLastRequest}ms")

        if (timeSinceLastRequest < minIntervalMs) {
            val sleepTime = minIntervalMs - timeSinceLastRequest
            Log.w("RateLimitingInterceptor", "Rate limiting: sleeping ${sleepTime}ms")
            Thread.sleep(sleepTime)
        }

        lastRequestTime.set(System.currentTimeMillis())
        return chain.proceed(chain.request())
    }
}

class RequestDeduplicationInterceptor : Interceptor {
    private val ongoingRequests = ConcurrentHashMap<String, okhttp3.Call>()

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val key = "${request.method}:${request.url}"

        Log.d("RequestDeduplicationInterceptor", "Processing request: $key")

        synchronized(ongoingRequests) {
            val existingCall = ongoingRequests[key]
            if (existingCall != null && !existingCall.isCanceled()) {
                Log.w("RequestDeduplicationInterceptor", "Duplicate request detected for $key, waiting for existing call")
                // Wait for existing request to complete
                try {
                    val response = existingCall.execute()
                    ongoingRequests.remove(key)
                    Log.d("RequestDeduplicationInterceptor", "Returned cached response for $key")
                    return response
                } catch (e: IOException) {
                    Log.e("RequestDeduplicationInterceptor", "Existing call failed for $key, proceeding with new request", e)
                    ongoingRequests.remove(key)
                    // Continue with new request if existing failed
                }
            }

            val call = chain.call()
            ongoingRequests[key] = call
            Log.d("RequestDeduplicationInterceptor", "New request started for $key")
        }

        try {
            val response = chain.proceed(request)
            ongoingRequests.remove(key)
            Log.d("RequestDeduplicationInterceptor", "Request completed for $key")
            return response
        } catch (e: IOException) {
            Log.e("RequestDeduplicationInterceptor", "Request failed for $key", e)
            ongoingRequests.remove(key)
            throw e
        }
    }
}

class ExponentialBackoffInterceptor : Interceptor {
    companion object {
        private const val TAG = "ExponentialBackoff"
        private const val MAX_RETRIES = 1  // Reduced from 3
        private const val INITIAL_DELAY_MS = 100L
        private const val MAX_DELAY_MS = 2000L  // Reduced from 5000L
    }

    override fun intercept(chain: Interceptor.Chain): Response {
        var response: Response? = null
        var lastException: Exception? = null

        for (attempt in 0..MAX_RETRIES) {
            try {
                response?.close()
                response = chain.proceed(chain.request())

                if (response.isSuccessful || !isRetryable(response.code)) {
                    return response
                }

                if (attempt == MAX_RETRIES) {
                    return response
                }

                val retryAfter = response.header("Retry-After")
                val delayMs = if (retryAfter != null) {
                    try {
                        retryAfter.toLong() * 1000
                    } catch (e: NumberFormatException) {
                        calculateBackoffDelay(attempt)
                    }
                } else {
                    calculateBackoffDelay(attempt)
                }

                Log.w(TAG, "HTTP ${response.code} received. Retrying after ${delayMs}ms")
                response.close()
                Thread.sleep(delayMs)

            } catch (e: IOException) {
                lastException = e
                if (attempt == MAX_RETRIES) {
                    throw e
                }
                Thread.sleep(calculateBackoffDelay(attempt))
            }
        }

        return response ?: throw (lastException ?: IOException("Max retries exceeded"))
    }

    private fun isRetryable(code: Int): Boolean {
        return code == 429 || code == 503 || code == 500
    }

    private fun calculateBackoffDelay(attempt: Int): Long {
        val exponentialDelay = INITIAL_DELAY_MS * (1L shl attempt)
        val jitter = Random.nextLong(0, exponentialDelay / 2)
        return min(exponentialDelay + jitter, MAX_DELAY_MS)
    }
}

class RetryInterceptor : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        var response: Response? = null
        var exception: Exception? = null

        for (i in 0..NetworkConfig.RetryPolicy.MAX_RETRIES) {
            try {
                response?.close()
                response = chain.proceed(chain.request())
                
                if (response.isSuccessful) {
                    return response
                }
                
                if (i == NetworkConfig.RetryPolicy.MAX_RETRIES) {
                    return response
                }
            } catch (e: Exception) {
                exception = e
                if (i == NetworkConfig.RetryPolicy.MAX_RETRIES) {
                    throw e
                }
            }
            
            if (i < NetworkConfig.RetryPolicy.MAX_RETRIES) {
                val delay = min(
                    NetworkConfig.RetryPolicy.INITIAL_DELAY_MS * (1L shl i),
                    NetworkConfig.RetryPolicy.MAX_DELAY_MS
                )
                Thread.sleep(delay)
            }
        }
        
        return response ?: throw (exception ?: Exception("Network request failed"))
    }
}

object RetrofitClient {
    private const val TAG = "RetrofitClient"
    private const val BASE_URL = "http://192.168.1.7:8000/api/"
    private val selectedBaseUrlRef = AtomicReference<String?>(null)

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = HttpLoggingInterceptor.Level.BODY
    }

    private val probeClient: OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(2, TimeUnit.SECONDS)
        .readTimeout(2, TimeUnit.SECONDS)
        .writeTimeout(2, TimeUnit.SECONDS)
        .build()

    private fun isAlive(baseUrl: String): Boolean {
        val url = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
        val request = Request.Builder().url(url + "v1/test").get().build()
        return try {
            probeClient.newCall(request).execute().use { resp ->
                resp.isSuccessful
            }
        } catch (_: IOException) {
            false
        }
    }

    private fun ensureResolvedBaseUrl(context: Context): String {
        selectedBaseUrlRef.get()?.let { return it }

        val defaultUrl = NetworkConfig.BaseUrls.getDefault(context)
        val altUrl = if (defaultUrl.startsWith("http://10.0.2.2"))
            NetworkConfig.BaseUrls.getDeviceLanUrl() else NetworkConfig.BaseUrls.getEmulatorUrl()

        val resolved = when {
            isAlive(defaultUrl) -> defaultUrl
            isAlive(altUrl) -> altUrl
            else -> defaultUrl
        }
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

        val authInterceptor = Interceptor { chain ->
            val sessionManager = SessionManager(context)
            var token: String? = sessionManager.getAuthToken()
            if (token.isNullOrEmpty()) {
                token = TokenManager.getToken(context)
            }

            val requestBuilder = chain.request().newBuilder()
                .addHeader("Accept", "application/json")

            if (!token.isNullOrEmpty()) {
                requestBuilder.addHeader("Authorization", "Bearer $token")
            }

            chain.proceed(requestBuilder.build())
        }

        return OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .addInterceptor(authInterceptor)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()
    }

    fun getAuthenticatedInstance(context: Context): ApiService {
        return Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(getOkHttpClient(context))
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    fun getUnauthenticatedInstance(): ApiService {
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
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    fun createOkHttpClient(context: Context): OkHttpClient {
        val cacheDir = File(context.cacheDir, "http_cache")
        val cache = Cache(cacheDir, NetworkConfig.Cache.MAX_CACHE_SIZE)

        return OkHttpClient.Builder()
            .cache(cache)
            .addInterceptor(AuthInterceptor(context))
            .addInterceptor(RateLimitingInterceptor())
            .addInterceptor(RequestDeduplicationInterceptor())
            .addInterceptor(CacheInterceptor())
            .addInterceptor(ExponentialBackoffInterceptor())
            .addInterceptor(loggingInterceptor)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .connectionPool(ConnectionPool(1, 1, TimeUnit.MINUTES))  // Further reduced to 1 connection to minimize server load
            .protocols(listOf(Protocol.HTTP_2, Protocol.HTTP_1_1))
            .build()
    }

    private val gson = GsonBuilder().setLenient().create()

    fun createApiService(context: Context): ApiService {
        val baseUrl = ensureResolvedBaseUrl(context)
        val okHttpClient = createOkHttpClient(context)

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
            .create(ApiService::class.java)
    }
}
