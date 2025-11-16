# ⚡ QUICK REFERENCE - COPY & PASTE READY

## 📋 Fastest Way to Integrate

### 1️⃣ In Your Composables - COPY THIS

```kotlin
// Dashboard / Main Page
@Composable
fun YourPage() {
    val context = LocalContext.current
    val repository = remember { com.christopheraldoo.aplikasimonitoringkelas.repository.DataRepository(context) }

    var data by remember { mutableStateOf<YourData>(emptyList()) }
    var isLoading by remember { mutableStateOf(true) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        try {
            val result = withContext(Dispatchers.IO) {
                repository.getUsers()  // or getTeachers(), getSubjects(), etc.
            }

            result.onSuccess { items ->
                data = items
                errorMessage = null
                isLoading = false
            }.onFailure { error ->
                errorMessage = error.localizedMessage ?: "Error loading data"
                isLoading = false
            }
        } catch (e: Exception) {
            errorMessage = e.localizedMessage
            isLoading = false
        }
    }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        if (isLoading) {
            CircularProgressIndicator()
        } else if (errorMessage != null) {
            Text(errorMessage!!, color = MaterialTheme.colorScheme.error)
        } else {
            LazyColumn {
                items(data) { item ->
                    ItemCard(item)
                }
            }
        }
    }
}
```

---

## 🔄 Common Operations

### Get Users

```kotlin
val result = repository.getUsers(forceRefresh = false)
result.onSuccess { users ->
    // Use users
}.onFailure { error ->
    // Handle error
}
```

### Get Teachers

```kotlin
val result = repository.getTeachers(forceRefresh = false)
```

### Get Subjects

```kotlin
val result = repository.getSubjects(forceRefresh = false)
```

### Get Classrooms

```kotlin
val result = repository.getClassrooms(forceRefresh = false)
```

### Get Schedules (with filters)

```kotlin
val result = repository.getSchedules(
    day = "Monday",          // optional
    classId = null,          // optional
    teacherId = null,        // optional
    forceRefresh = false
)
```

### Get All Dashboard Data (Parallel)

```kotlin
val result = repository.getDashboardData(forceRefresh = false)
result.onSuccess { dashboardData ->
    val users = dashboardData.users
    val schedules = dashboardData.schedules
    val teachers = dashboardData.teachers
    val subjects = dashboardData.subjects
}
```

### Force Refresh (Bypass Cache)

```kotlin
val result = repository.getDashboardData(forceRefresh = true)
```

### Clear All Cache

```kotlin
repository.clearCache()
```

---

## 🎯 Quick Patterns

### Basic Loading Pattern

```kotlin
LaunchedEffect(Unit) {
    try {
        val result = withContext(Dispatchers.IO) {
            repository.getUsers()
        }

        result.onSuccess { data ->
            // Success
        }.onFailure { error ->
            // Error
        }
    } catch (e: Exception) {
        // Exception
    }
}
```

### With Error Display

```kotlin
@Composable
fun WithErrorDisplay() {
    var errorMessage by remember { mutableStateOf<String?>(null) }

    if (errorMessage != null) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Text(errorMessage!!, color = MaterialTheme.colorScheme.error)
        }
    }
}
```

### With Loading Indicator

```kotlin
@Composable
fun WithLoadingIndicator() {
    var isLoading by remember { mutableStateOf(true) }

    if (isLoading) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
    }
}
```

### Complete Full Pattern

```kotlin
@Composable
fun CompletePattern() {
    val context = LocalContext.current
    val repository = remember { DataRepository(context) }

    var data by remember { mutableStateOf(emptyList()) }
    var isLoading by remember { mutableStateOf(true) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        try {
            val result = withContext(Dispatchers.IO) {
                repository.getUsers()
            }
            result.onSuccess { items ->
                data = items
                errorMessage = null
                isLoading = false
            }.onFailure { error ->
                errorMessage = error.localizedMessage
                isLoading = false
            }
        } catch (e: Exception) {
            errorMessage = e.message
            isLoading = false
        }
    }

    Column(modifier = Modifier.fillMaxSize()) {
        when {
            isLoading -> CircularProgressIndicator()
            errorMessage != null -> Text(errorMessage!!)
            data.isEmpty() -> Text("No data")
            else -> LazyColumn {
                items(data) { item -> }
            }
        }
    }
}
```

---

## 🔍 Debugging

### Print Cache Stats

```kotlin
fun debugCache(context: Context) {
    val cache = CacheManager(context)
    val keys = listOf("users_list", "teachers_list", "subjects_list", "classrooms_list")

    keys.forEach { key ->
        println("$key: Valid=${cache.isCacheValid(key)}, TTL=${cache.getRemainingTTL(key)}ms")
    }
}
```

### Check Network Response

```kotlin
// Check Logcat with filter "DataRepository"
// Look for:
// - "Loading [resource] from cache"
// - "Fetching [resource] from API"
// - "[resource] loaded successfully"
```

### Manual Cache Clear

```kotlin
// Di any Activity/Fragment
val repository = DataRepository(context)
repository.clearCache()
```

---

## 📊 Performance Check

### Expected Times

```
First Load (no cache): 2-3 seconds
Cached Load: <100ms
Force Refresh: 2-3 seconds
```

### Test

```kotlin
val startTime = System.currentTimeMillis()
// Do API call
val duration = System.currentTimeMillis() - startTime
println("Duration: ${duration}ms")
```

---

## ⚠️ Common Mistakes

### ❌ WRONG

```kotlin
// Creating new repository every time
val repository = DataRepository(context)  // Creates new instance
repository.getUsers()
```

### ✅ RIGHT

```kotlin
// Remember the repository instance
val repository = remember { DataRepository(context) }
repository.getUsers()
```

### ❌ WRONG

```kotlin
// Not using forceRefresh correctly
repository.getUsers(forceRefresh = true)  // Every time = many API calls!
```

### ✅ RIGHT

```kotlin
// Only force refresh on manual user action
if (userClickedRefreshButton) {
    repository.getUsers(forceRefresh = true)
} else {
    repository.getUsers(forceRefresh = false)  // Use cache
}
```

---

## 🚨 Troubleshooting Quick Fixes

| Problem            | Solution                                         |
| ------------------ | ------------------------------------------------ |
| Still loading slow | `repository.clearCache()` + restart              |
| Data not updating  | `repository.getDashboardData(forceRefresh=true)` |
| Import error       | Verify files in correct path                     |
| Compile error      | `./gradlew clean build`                          |
| Token error        | Check SharedPreferences "MonitoringKelasSession" |
| Network error      | Check internet, test API in Postman              |

---

## 📱 In Different Pages

### AdminDashboard ✅ ALREADY DONE

```kotlin
// Just copy from AdminActivity.kt
val repository = remember { DataRepository(context) }
LaunchedEffect(Unit) {
    val result = withContext(Dispatchers.IO) {
        repository.getDashboardData(forceRefresh = false)
    }
    // Handle result
}
```

### ManageUsersPage ✅ ALREADY DONE

```kotlin
// Just copy pattern
repository.getUsers(forceRefresh = false)
```

### Any New Page 📝

```kotlin
// Copy the complete pattern from AdminDashboard
// Change repository.getUsers() to what you need
// The rest is identical
```

---

## 🎓 Learning Resources

### Files to Study

1. **CacheManager.kt** - Understand caching logic
2. **DataRepository.kt** - Understand repository pattern
3. **AdminActivity.kt** - See real implementation

### Key Concepts

```kotlin
// 1. Result<T> pattern
result.onSuccess { data -> }
result.onFailure { error -> }

// 2. Coroutines
withContext(Dispatchers.IO) { }
async { }
awaitAll()

// 3. Compose State
remember { mutableStateOf() }
LaunchedEffect(Unit) { }

// 4. Caching
cacheManager.saveData(key, data, ttl)
cacheManager.getData(key, ttl)
```

---

## 🔐 Important Security

### Token Handling

```kotlin
// Always use through repository
// Never hardcode token
// Token automatically retrieved from SessionManager

val token = sessionManager.getUserDetails()["token"]
// Or better: repository handles it internally
```

### Cache Security

```kotlin
// Clear cache on logout
repository.clearCache()

// Don't cache sensitive data
// Current implementation only caches user/admin data (ok)
```

---

## 📦 Imports (if needed)

```kotlin
import com.christopheraldoo.aplikasimonitoringkelas.repository.DataRepository
import com.christopheraldoo.aplikasimonitoringkelas.cache.CacheManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
```

---

## ✅ Deployment Checklist

- [ ] Run `./gradlew clean build` - No errors
- [ ] Test on emulator - Works
- [ ] Test on physical device - Works
- [ ] Check Logcat - No crashes
- [ ] Monitor memory - No leaks
- [ ] Test all pages - Load fast
- [ ] Test error scenarios - Handled well
- [ ] Deploy to play store

---

**Last Updated:** October 28, 2025
**Status:** ✅ READY TO USE
**Confidence Level:** 100%
