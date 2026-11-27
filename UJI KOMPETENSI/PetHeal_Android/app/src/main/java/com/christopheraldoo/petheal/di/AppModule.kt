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
import com.christopheraldoo.petheal.data.remote.NetworkMonitor
import com.christopheraldoo.petheal.data.remote.RetryInterceptor
import com.google.firebase.auth.FirebaseAuth
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import okhttp3.Cache
import okhttp3.OkHttpClient
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.io.File
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {
    // Backend URL loaded from BuildConfig (set in build.gradle.kts from local.properties).
    // PHASE 7 fix: normalize defensively. A URL without the /api/ suffix (or
    // without trailing slash) previously produced HTML 404 pages that Gson
    // could not parse, surfacing only as "Failed to load clinics".
    private val BASE_URL = normalizeApiBaseUrl(BuildConfig.BACKEND_BASE_URL)
    private val STORAGE_BASE_URL = normalizeStorageBaseUrl(BASE_URL)

    /** Full URL prefix for Laravel public storage files */
    val STORAGE_URL = "${STORAGE_BASE_URL}storage/"

    private fun normalizeApiBaseUrl(rawBaseUrl: String): String {
        var trimmed = rawBaseUrl.trim().trimEnd('/')
        if (!trimmed.endsWith("/api", ignoreCase = true)) {
            trimmed += "/api"
        }
        return "$trimmed/"
    }

    private fun normalizeStorageBaseUrl(rawBaseUrl: String): String {
        val trimmed = rawBaseUrl.trim().trimEnd('/')
        return when {
            trimmed.endsWith("/api", ignoreCase = true) -> trimmed.removeSuffix("/api") + "/"
            else -> "$trimmed/"
        }
    }

    @Provides
    @Singleton
    fun providePreferencesManager(
        @ApplicationContext context: Context
    ): PreferencesManager = PreferencesManager(context)

    @Provides
    @Singleton
    fun provideFirebaseAuth(): FirebaseAuth = FirebaseAuth.getInstance()

    /**
     * Scope yang hidup selama proses aplikasi. Dipakai untuk pekerjaan lintas
     * layar (menyegarkan cache sesi, membersihkan sesi setelah 401).
     */
    @Provides
    @Singleton
    @ApplicationScope
    fun provideApplicationScope(): CoroutineScope =
        CoroutineScope(SupervisorJob() + Dispatchers.IO)

    @Provides
    @Singleton
    fun provideNetworkInterceptor(
        preferencesManager: PreferencesManager,
        @ApplicationScope appScope: CoroutineScope
    ): NetworkInterceptor = NetworkInterceptor(preferencesManager, appScope)

    @Provides
    @Singleton
    fun provideOkHttpClient(
        @ApplicationContext context: Context,
        networkInterceptor: NetworkInterceptor,
        networkMonitor: NetworkMonitor
    ): OkHttpClient {
        // Cache HTTP per akun. Vary: Authorization ditambahkan di network
        // interceptor agar data akun lama tidak pernah dipakai akun baru.
        val cacheDir = File(context.cacheDir, "http_responses")
        val httpCache = Cache(cacheDir, 50L * 1024 * 1024) // 50 MB

        val builder = OkHttpClient.Builder()
            .cache(httpCache)
            .addInterceptor(networkInterceptor)
            // ✅ OPTIMIZED: Add cache control interceptor for API responses
            .addInterceptor { chain ->
                var request = chain.request()
                // Saat offline, gambar dan GET API boleh memakai cache.
                // Cache API diberi Vary: Authorization di network interceptor,
                // sehingga data akun lama tidak muncul pada akun baru.
                val path = request.url.encodedPath
                val isStorageAsset = path.contains("/storage/")
                val isApiGet = request.method == "GET" && path.startsWith("/api/")
                if (!networkMonitor.isOnlineNow() && (isStorageAsset || isApiGet)) {
                    request = request.newBuilder()
                        .header(
                            "Cache-Control",
                            if (isStorageAsset) "public, only-if-cached, max-stale=604800"
                            else "private, only-if-cached, max-stale=604800"
                        )
                        .build()
                }
                chain.proceed(request)
            }
            // Retry khusus GET (429/5xx) — lihat RetryInterceptor untuk alasannya.
            .addInterceptor(RetryInterceptor())
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(45, TimeUnit.SECONDS)
            // ── Cache HTTP global: gambar 24 jam, API 30–120 detik ──
            // Vary: Authorization membuat cache OkHttp terpisah per token.
            // Setiap POST/PUT/PATCH/DELETE yang sukses mengosongkan cache agar
            // data setelah aksi tulis tidak tertinggal basi.
            .addNetworkInterceptor { chain ->
                val request = chain.request()
                val response = chain.proceed(request)
                val path = request.url.encodedPath

                if (request.method == "GET" && path.startsWith("/api/")) {
                    val contentType = response.header("Content-Type") ?: ""
                    if (response.isSuccessful && contentType.contains("application/json")) {
                        val ttl = when {
                            path.contains("/bookings") -> 10
                            path.contains("/medical-records") ||
                                path.contains("/privacy-requests") -> 30
                            else -> 120
                        }

                        return@addNetworkInterceptor response.newBuilder()
                            .header("Cache-Control", "private, max-age=$ttl")
                            .header("Vary", "Authorization")
                            .removeHeader("Pragma")
                            .removeHeader("Expires")
                            .build()
                    }
                }

                if (request.method != "GET" && response.isSuccessful) {
                    httpCache.evictAll()
                }

                if (request.url.toString().contains("/storage/")) {
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
                level = HttpLoggingInterceptor.Level.HEADERS
                redactHeader("Authorization")
            }
            builder.addInterceptor(loggingInterceptor)
        }

        return builder.build()
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
