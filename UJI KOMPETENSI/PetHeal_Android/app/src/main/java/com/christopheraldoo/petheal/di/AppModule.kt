package com.christopheraldoo.petheal.di

import android.content.Context
import coil.ImageLoader
import coil.disk.DiskCache
import coil.memory.MemoryCache
import coil.request.CachePolicy
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.NetworkInterceptor
import com.google.firebase.auth.FirebaseAuth
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import okhttp3.Cache
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.io.File
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {
    // Backend URL loaded from BuildConfig (set in build.gradle.kts from local.properties)
    private val BASE_URL = BuildConfig.BACKEND_BASE_URL

    /** Full URL prefix for Laravel public storage files */
    val STORAGE_URL = "${BASE_URL}storage/"

    @Provides
    @Singleton
    fun providePreferencesManager(
        @ApplicationContext context: Context
    ): PreferencesManager = PreferencesManager(context)

    @Provides
    @Singleton
    fun provideFirebaseAuth(): FirebaseAuth = FirebaseAuth.getInstance()

    @Provides
    @Singleton
    fun provideNetworkInterceptor(
        preferencesManager: PreferencesManager
    ): NetworkInterceptor = NetworkInterceptor(preferencesManager)

    @Provides
    @Singleton
    fun provideOkHttpClient(
        @ApplicationContext context: Context,
        networkInterceptor: NetworkInterceptor
    ): OkHttpClient {
        // ✅ OPTIMIZED: Add HTTP response cache (10MB)
        // Enables offline viewing of previously loaded pages
        val cacheDir = File(context.cacheDir, "http_responses")
        val httpCache = Cache(cacheDir, 10L * 1024 * 1024) // 10 MB

        val builder = OkHttpClient.Builder()
            .cache(httpCache)
            .addInterceptor(networkInterceptor)
            // ✅ OPTIMIZED: Add cache control interceptor for API responses
            .addInterceptor { chain ->
                var request = chain.request()
                // Force cache for 60 seconds if offline
                if (!isNetworkAvailable(context)) {
                    request = request.newBuilder()
                        .header("Cache-Control", "public, only-if-cached, max-stale=604800")
                        .build()
                }
                chain.proceed(request)
            }
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(45, TimeUnit.SECONDS)
            // ── Image caching: force 24h cache even if server says no-cache ──
            .addNetworkInterceptor { chain ->
                val response = chain.proceed(chain.request())
                if (chain.request().url.toString().contains("/storage/")) {
                    response.newBuilder()
                        .header("Cache-Control", "public, max-age=86400")
                        .removeHeader("Pragma")
                        .build()
                } else {
                    response
                }
            }
            // ── Shared pool: Retrofit + Coil reuse the SAME TCP connections ──
            // This eliminates the extra TLS handshake per image (~2-5s on ngrok)
            .connectionPool(okhttp3.ConnectionPool(10, 5, TimeUnit.MINUTES))
            .retryOnConnectionFailure(true)

        // Only enable HTTP logging in debug builds
        if (BuildConfig.ENABLE_HTTP_LOGGING) {
            val loggingInterceptor = HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BODY
            }
            builder.addInterceptor(loggingInterceptor)
        }

        return builder.build()
    }

    /**
     * Check if network is available
     */
    private fun isNetworkAvailable(context: Context): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? android.net.ConnectivityManager
        val network = connectivityManager?.activeNetwork
        val capabilities = connectivityManager?.getNetworkCapabilities(network)
        return capabilities != null && (
            capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_WIFI) ||
            capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_CELLULAR) ||
            capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_ETHERNET)
        )
    }

    /**
     * Provide a Coil ImageLoader that SHARES the same OkHttpClient (and its
     * connection pool) as Retrofit.  This means the TLS handshake to ngrok is
     * done ONCE on first API call; every subsequent image load reuses the open
     * connection → images appear in < 1 second instead of minutes.
     */
    @Provides
    @Singleton
    fun provideImageLoader(
        @ApplicationContext context: Context,
        okHttpClient: OkHttpClient
    ): ImageLoader {
        // Add an HTTP-level disk cache on top of the shared client
        val httpCacheDir = File(context.cacheDir, "image_http_cache")
        val cachedClient = okHttpClient.newBuilder()
            .cache(Cache(httpCacheDir, 50L * 1024 * 1024))
            .build()

        return ImageLoader.Builder(context)
            .okHttpClient(cachedClient)
            .memoryCache {
                MemoryCache.Builder(context)
                    .maxSizePercent(0.25)   // 25% of RAM for decoded bitmaps
                    .build()
            }
            .diskCache {
                DiskCache.Builder()
                    .directory(context.cacheDir.resolve("coil_image_cache"))
                    .maxSizeBytes(100L * 1024 * 1024)   // 100 MB on disk
                    .build()
            }
            .memoryCachePolicy(CachePolicy.ENABLED)
            .diskCachePolicy(CachePolicy.ENABLED)
            .respectCacheHeaders(false)   // ignore server no-cache, always use our cache
            .crossfade(200)
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(okHttpClient: OkHttpClient): Retrofit {
        return Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }

    @Provides
    @Singleton
    fun provideApiService(retrofit: Retrofit): ApiService {
        return retrofit.create(ApiService::class.java)
    }
}
