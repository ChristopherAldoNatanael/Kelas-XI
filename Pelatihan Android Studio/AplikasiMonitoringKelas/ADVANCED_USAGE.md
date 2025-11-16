# 🎓 ADVANCED USAGE GUIDE - BONUS TIPS & TRICKS

## 🎯 Advanced Scenarios

### 1. Custom Cache TTL per Endpoint

```kotlin
// Scenario: Jadwal butuh refresh lebih sering (2 menit)
val result = repository.getSchedules(
    day = "Monday",
    forceRefresh = false
)

// Tapi di CacheManager kita bisa custom:
cacheManager.saveData(
    key = "schedules_monday",
    data = scheduleList,
    ttlMs = 2 * 60 * 1000L  // 2 menit saja
)

// Get dengan TTL custom:
val cached = cacheManager.getData<List<ScheduleApi>>(
    key = "schedules_monday",
    ttlMs = 2 * 60 * 1000L
)
```

### 2. Pre-load Data saat App Start

```kotlin
// Di MainActivity atau Application class
class MonitoringApp : Application() {
    override fun onCreate() {
        super.onCreate()

        // Pre-load dashboard data saat app start
        GlobalScope.launch(Dispatchers.IO) {
            val repository = DataRepository(this@MonitoringApp)
            repository.getDashboardData(forceRefresh = false)
            // Data sudah di-cache sebelum user buka dashboard
        }
    }
}
```

### 3. Background Sync di 30 Menit

```kotlin
// Refresh cache setiap 30 menit secara otomatis
import androidx.work.PeriodicWorkRequest
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

// Di Application class atau MainActivity
fun scheduleBackgroundSync(context: Context) {
    val syncRequest = PeriodicWorkRequest.Builder(
        DataSyncWorker::class.java,
        30,
        TimeUnit.MINUTES
    ).build()

    WorkManager.getInstance(context).enqueueUniquePeriodicWork(
        "dashboard_sync",
        ExistingPeriodicWorkPolicy.KEEP,
        syncRequest
    )
}

// WorkManager class
class DataSyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        return try {
            val repository = DataRepository(applicationContext)
            repository.getDashboardData(forceRefresh = true)
            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }
}
```

### 4. Pull-to-Refresh Implementation

```kotlin
@Composable
fun ManageUsersPageWithRefresh() {
    val context = LocalContext.current
    val repository = remember { DataRepository(context) }

    var users by remember { mutableStateOf<List<UserApi>>(emptyList()) }
    var isLoading by remember { mutableStateOf(false) }
    var isRefreshing by remember { mutableStateOf(false) }

    // Swipe refresh state
    val pullRefreshState = rememberPullRefreshState(
        refreshing = isRefreshing,
        onRefresh = {
            // Force refresh saat user swipe
            isRefreshing = true
        }
    )

    LaunchedEffect(isRefreshing) {
        if (isRefreshing) {
            val result = withContext(Dispatchers.IO) {
                repository.getUsers(forceRefresh = true)
            }

            result.onSuccess { userList ->
                users = userList
            }.onFailure { error ->
                // Show error toast
            }

            isRefreshing = false
        }
    }

    Box(modifier = Modifier.pullRefresh(pullRefreshState)) {
        LazyColumn {
            items(users) { user ->
                UserCard(user = user)
            }
        }

        // Pull refresh indicator
        PullRefreshIndicator(
            refreshing = isRefreshing,
            state = pullRefreshState,
            modifier = Modifier.align(Alignment.TopCenter)
        )
    }
}
```

### 5. Offline Mode dengan Cache Fallback

```kotlin
// Create a wrapper untuk safe data access
class OfflineAwareRepository(private val repository: DataRepository) {
    suspend fun getUsersSafe(): Result<List<UserApi>> {
        return try {
            repository.getUsers(forceRefresh = false)
        } catch (e: Exception) {
            // Jika offline, fallback ke cache
            // Cache manager akan return cached data jika ada
            Log.e("OfflineAware", "Offline or error, using cache if available")
            repository.getUsers(forceRefresh = false)
        }
    }
}

// Atau lebih sederhana, check internet connectivity:
fun isNetworkAvailable(context: Context): Boolean {
    val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
    val network = connectivityManager.activeNetwork ?: return false
    val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
    return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
}

// Usage:
val repository = DataRepository(context)
val result = if (isNetworkAvailable(context)) {
    repository.getUsers(forceRefresh = true)
} else {
    repository.getUsers(forceRefresh = false)  // Will use cache
}
```

### 6. Search & Filter dengan Cache

```kotlin
@Composable
fun ManageUsersWithSearch() {
    val context = LocalContext.current
    val repository = remember { DataRepository(context) }

    var searchQuery by remember { mutableStateOf("") }
    var allUsers by remember { mutableStateOf<List<UserApi>>(emptyList()) }
    var filteredUsers by remember { mutableStateOf<List<UserApi>>(emptyList()) }

    // Load once dari cache
    LaunchedEffect(Unit) {
        val result = withContext(Dispatchers.IO) {
            repository.getUsers(forceRefresh = false)
        }

        result.onSuccess { users ->
            allUsers = users
            filteredUsers = users
        }
    }

    // Filter saat search query berubah (local, tidak ke API!)
    LaunchedEffect(searchQuery) {
        filteredUsers = if (searchQuery.isEmpty()) {
            allUsers
        } else {
            allUsers.filter { user ->
                user.nama.contains(searchQuery, ignoreCase = true) ||
                user.email.contains(searchQuery, ignoreCase = true)
            }
        }
    }

    Column {
        // Search box
        TextField(
            value = searchQuery,
            onValueChange = { searchQuery = it },
            modifier = Modifier.fillMaxWidth(),
            placeholder = { Text("Search users...") }
        )

        // Results
        LazyColumn {
            items(filteredUsers) { user ->
                UserCard(user = user)
            }
        }
    }
}
```

### 7. Pagination untuk Data Besar

```kotlin
// Extend repository dengan pagination support
suspend fun getUsersPaginated(
    page: Int = 1,
    perPage: Int = 20,
    forceRefresh: Boolean = false
): Result<PaginatedUsers> {
    // Di API service tambah:
    // @GET("users?page={page}&per_page={perPage}")
    // suspend fun getUsersPaginated(
    //     @Header("Authorization") token: String,
    //     @Query("page") page: Int,
    //     @Query("per_page") perPage: Int
    // )

    // Implementation
    return try {
        val cacheKey = "users_page_$page"

        if (!forceRefresh && cacheManager.isCacheValid(cacheKey)) {
            val cached = cacheManager.getData<PaginatedUsers>(cacheKey)
            return Result.success(cached ?: error("No cache"))
        }

        val token = getBearerToken() ?: return Result.failure(Exception("No token"))
        val response = apiService.getUsersPaginated(token, page, perPage)

        if (response.isSuccessful && response.body()?.success == true) {
            val data = response.body()?.data ?: error("No data")
            cacheManager.saveData(cacheKey, data, CacheManager.TTL_LONG)
            Result.success(data)
        } else {
            Result.failure(Exception(response.body()?.message ?: "Error"))
        }
    } catch (e: Exception) {
        Result.failure(e)
    }
}

// UI dengan pagination
@Composable
fun ManageUsersWithPagination() {
    val context = LocalContext.current
    val repository = remember { DataRepository(context) }

    var currentPage by remember { mutableStateOf(1) }
    var users by remember { mutableStateOf<List<UserApi>>(emptyList()) }
    var totalPages by remember { mutableStateOf(1) }
    var isLoading by remember { mutableStateOf(false) }

    fun loadPage(page: Int) {
        isLoading = true
        // Implementation untuk load specific page
    }

    Column {
        // Content
        LazyColumn {
            items(users) { user ->
                UserCard(user = user)
            }
        }

        // Pagination controls
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.Center
        ) {
            Button(
                onClick = { if (currentPage > 1) loadPage(currentPage - 1) },
                enabled = currentPage > 1
            ) {
                Text("Previous")
            }

            Text("Page $currentPage of $totalPages")

            Button(
                onClick = { if (currentPage < totalPages) loadPage(currentPage + 1) },
                enabled = currentPage < totalPages
            ) {
                Text("Next")
            }
        }
    }
}
```

### 8. Analytics - Track Cache Performance

```kotlin
// Create analytics wrapper
class AnalyticsRepository(
    private val repository: DataRepository,
    private val analytics: Analytics
) {
    suspend fun getUsers(forceRefresh: Boolean = false): Result<List<UserApi>> {
        val startTime = System.currentTimeMillis()
        val cacheHit = !forceRefresh // Simple heuristic

        val result = repository.getUsers(forceRefresh)

        val duration = System.currentTimeMillis() - startTime

        // Track to analytics
        analytics.logEvent("data_fetch_users", mapOf(
            "duration_ms" to duration,
            "cache_hit" to cacheHit,
            "success" to result.isSuccess
        ))

        return result
    }
}

// Bonus: Log cache statistics
fun printCacheStats(context: Context) {
    val cacheManager = CacheManager(context)
    val cacheKeys = listOf(
        "users_list",
        "teachers_list",
        "subjects_list",
        "classrooms_list",
        "schedules_list"
    )

    println("=== CACHE STATISTICS ===")
    cacheKeys.forEach { key ->
        val valid = cacheManager.isCacheValid(key)
        val ttl = if (valid) cacheManager.getRemainingTTL(key) else 0
        println("$key: ${if (valid) "VALID" else "INVALID"} (${ttl}ms remaining)")
    }
}
```

### 9. Unit Testing dengan Mock Repository

```kotlin
// Test file
@RunWith(AndroidJUnit4::class)
class DataRepositoryTest {

    @get:Rule
    val instantExecutorRule = InstantTaskExecutorRule()

    private lateinit var repository: DataRepository
    private val mockContext = mock(Context::class.java)

    @Before
    fun setUp() {
        repository = DataRepository(mockContext)
    }

    @Test
    fun testCachingWorks() = runTest {
        // First call - cache miss
        val result1 = repository.getUsers(forceRefresh = false)
        assertTrue(result1.isSuccess)

        val time1 = System.currentTimeMillis()

        // Second call - cache hit (should be instant)
        val result2 = repository.getUsers(forceRefresh = false)

        val time2 = System.currentTimeMillis()
        val duration = time2 - time1

        // Cached call should be < 10ms
        assertTrue(duration < 10)
        assertEquals(result1.getOrNull(), result2.getOrNull())
    }

    @Test
    fun testForceRefresh() = runTest {
        val result1 = repository.getUsers(forceRefresh = false)
        val data1 = result1.getOrNull()

        // Force refresh
        val result2 = repository.getUsers(forceRefresh = true)
        val data2 = result2.getOrNull()

        assertEquals(data1, data2)  // Same endpoint
    }
}
```

### 10. Debugging Tips

```kotlin
// 1. Enable detailed logging
fun setupLogging(repository: DataRepository) {
    val logInterceptor = HttpLoggingInterceptor { message ->
        Log.d("HttpClient", message)
    }.apply {
        level = HttpLoggingInterceptor.Level.BODY
    }
}

// 2. Monitor cache operations
fun debugCache(context: Context) {
    val cache = CacheManager(context)
    val keys = listOf("users_list", "teachers_list", "subjects_list", "classrooms_list")

    keys.forEach { key ->
        Log.d("CacheDebug", """
            Key: $key
            Valid: ${cache.isCacheValid(key)}
            TTL Remaining: ${cache.getRemainingTTL(key)}ms
        """.trimIndent())
    }
}

// 3. Monitor network requests
fun monitorNetworkRequests() {
    // Check OkHttp interceptors
    // Check Retrofit call adapter
    // Monitor response times
}

// 4. Memory profiling
fun profileMemory(context: Context) {
    val runtime = Runtime.getRuntime()
    val usedMemory = runtime.totalMemory() - runtime.freeMemory()
    Log.d("MemoryProfile", "Used: ${usedMemory / 1024 / 1024}MB")
}
```

---

## 🎯 Best Practices Summary

### ✅ DO:

```kotlin
✅ Use forceRefresh = true saat user manually refresh
✅ Use forceRefresh = false untuk normal loads (cache akan digunakan)
✅ Call clearCache() saat logout
✅ Monitor cache TTL expiry time
✅ Use Result<T> pattern untuk error handling
✅ Log important operations dengan TAG
✅ Test cache behavior secara menyeluruh
✅ Monitor memory usage di devices low-end
```

### ❌ DON'T:

```kotlin
❌ Don't mix callback dan async/await patterns
❌ Don't clear cache terlalu sering (akan banyak API calls)
❌ Don't set TTL terlalu pendek (defeat purpose of caching)
❌ Don't set TTL terlalu panjang (stale data)
❌ Don't block UI thread dengan network calls
❌ Don't create multiple Repository instances (use remember)
❌ Don't hardcode URLs atau API keys
❌ Don't ignore error messages dari API
```

---

## 📞 ADVANCED TROUBLESHOOTING

### Issue: Cache tidak ter-clear saat logout

**Solution:**

```kotlin
// Di logout handler
val repository = DataRepository(context)
repository.clearCache()

// Juga clear SharedPreferences
context.getSharedPreferences("MonitoringKelasSession", Context.MODE_PRIVATE).edit().clear().apply()
```

### Issue: Data stale karena TTL terlalu lama

**Solution:**

```kotlin
// Reduce TTL
const val TTL_LONG = 10 * 60 * 1000L  // 10 menit instead of 30
```

### Issue: Aplikasi lambat meski sudah pakai cache

**Solution:**

1. Check network connectivity
2. Check API response time
3. Check device RAM
4. Monitor Logcat untuk errors
5. Profile dengan Android Studio Profiler

---

## 🚀 PERFORMANCE OPTIMIZATION TIPS

1. **Lazy Loading**

   - Load data saat needed, not all at once
   - Use pagination untuk data besar

2. **Efficient Caching**

   - Adjust TTL based on data volatility
   - Use targeted cache clear (tidak clear all)

3. **Network Optimization**

   - Use appropriate timeouts
   - Enable connection pooling
   - Compress payloads

4. **UI Optimization**
   - Use LazyColumn/LazyRow
   - Avoid recomposition
   - Use remember untuk expensive operations

---

**Happy Coding! 🎉**

For more advanced topics, refer to:

- Official Kotlin Coroutines docs
- Jetpack Compose docs
- Retrofit documentation
- Android Best Practices
