# 🎓 IMPLEMENTASI ROLE SISWA - JETPACK COMPOSE

**Tanggal:** 4 November 2025  
**Status:** 🚀 **READY TO IMPLEMENT**

---

## 📋 OVERVIEW

Aplikasi Role Siswa dengan 3 halaman utama:

1. **Jadwal Siswa** - Menampilkan jadwal sesuai kelas
2. **Input Kehadiran** - Lapor kehadiran guru
3. **Riwayat Laporan** - Lihat history laporan

**Tech Stack:**

- ✅ Jetpack Compose (UI Modern)
- ✅ ViewModel + StateFlow (State Management)
- ✅ Retrofit + OkHttp (API Client)
- ✅ Coroutines (Async Operations)
- ✅ Material Design 3 (UI Components)

---

## 🏗️ ARSITEKTUR

```
┌─────────────────┐
│  SiswaActivity  │ ← Main Activity dengan Bottom Navigation
└────────┬────────┘
         │
    ┌────┴─────┬─────────┬──────────┐
    │          │         │          │
┌───▼───┐ ┌───▼───┐ ┌──▼────┐  ┌──▼────┐
│Jadwal │ │Kehadir│ │Riwayat│  │Profile│
│Screen │ │ Screen│ │Screen │  │Screen │
└───┬───┘ └───┬───┘ └───┬───┘  └───┬───┘
    │         │         │          │
┌───▼─────────▼─────────▼──────────▼───┐
│         ViewModels Layer              │
│  (JadwalVM, KehadiranVM, RiwayatVM)  │
└───────────────┬──────────────────────┘
                │
        ┌───────▼────────┐
        │  SiswaRepository│
        └───────┬────────┘
                │
        ┌───────▼────────┐
        │   ApiService    │
        │   (Retrofit)    │
        └───────┬────────┘
                │
        ┌───────▼────────┐
        │ Laravel Backend │
        │   (sekolah-api) │
        └────────────────┘
```

---

## 📊 DATA FLOW

### 1. Jadwal Screen

```
User Opens App
    ↓
SessionManager loads class_id
    ↓
JadwalViewModel.loadJadwal()
    ↓
SiswaRepository.getJadwal(classId)
    ↓
API: GET /api/schedules?class_id={id}
    ↓
Response → StateFlow → UI Update
```

### 2. Input Kehadiran Screen

```
User selects Guru + Status
    ↓
Click "Kirim Laporan"
    ↓
KehadiranViewModel.submitKehadiran()
    ↓
SiswaRepository.submitKehadiran(data)
    ↓
API: POST /api/kehadiran
    ↓
Success → Snackbar → Clear Form
```

### 3. Riwayat Screen

```
Screen Loads
    ↓
RiwayatViewModel.loadRiwayat()
    ↓
SiswaRepository.getRiwayat(siswaId)
    ↓
API: GET /api/kehadiran/riwayat
    ↓
Response → LazyColumn with Cards
```

---

## 🎨 UI/UX DESIGN PRINCIPLES

### Color Scheme (Material 3)

```kotlin
val LightColorScheme = lightColorScheme(
    primary = Color(0xFF2196F3),      // Blue
    onPrimary = Color.White,
    primaryContainer = Color(0xFFBBDEFB),
    secondary = Color(0xFF4CAF50),     // Green
    onSecondary = Color.White,
    surface = Color(0xFFF5F5F5),
    onSurface = Color(0xFF212121),
    error = Color(0xFFE74C3C),
)
```

### Typography

```kotlin
val AppTypography = Typography(
    headlineLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Bold,
        fontSize = 32.sp
    ),
    titleMedium = TextStyle(
        fontWeight = FontWeight.SemiBold,
        fontSize = 18.sp
    ),
    bodyMedium = TextStyle(
        fontSize = 14.sp,
        lineHeight = 20.sp
    )
)
```

### Component Design

**JadwalCard:**

```
┌────────────────────────────────┐
│ 📚 Matematika                  │
│ 👨‍🏫 Pak Budi                     │
│ 🕐 07:00 - 08:30               │
│ 🏫 Lab Komputer 1              │
└────────────────────────────────┘
```

**RiwayatCard:**

```
┌────────────────────────────────┐
│ Pak Ahmad          [HADIR] ✅  │
│ 4 Nov 2025                     │
└────────────────────────────────┘
```

---

## 🔐 SECURITY & STATE MANAGEMENT

### Session Management

```kotlin
// Save on login
sessionManager.createLoginSession(
    id = user.id.toLong(),
    name = user.nama,
    email = user.email,
    role = user.role,
    classId = user.class_id  // ✅ Important!
)

// Read in ViewModel
val classId = sessionManager.getUserClassId()
val token = sessionManager.getAuthToken()
```

### Error Handling

```kotlin
sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
}
```

---

## 🚀 PERFORMANCE OPTIMIZATION

### Backend Laravel

**1. Query Optimization**

```php
// ✅ GOOD - Efficient query
$schedules = Schedule::with(['subject:id,name', 'teacher.user:id,nama', 'classroom:id,name'])
    ->where('class_id', $classId)
    ->where('status', 'active')
    ->select('id', 'class_id', 'subject_id', 'teacher_id', 'classroom_id',
             'day_of_week', 'period_number', 'start_time', 'end_time')
    ->orderBy('day_of_week')
    ->orderBy('period_number')
    ->get();

// ❌ BAD - Load all data
$schedules = Schedule::all(); // Don't do this!
```

**2. Response Caching**

```php
return Cache::remember("schedule_class_{$classId}", 300, function () use ($classId) {
    return Schedule::with(...)->where('class_id', $classId)->get();
});
```

**3. Pagination for Riwayat**

```php
return Attendance::where('student_id', $studentId)
    ->with('teacher.user')
    ->orderBy('created_at', 'desc')
    ->paginate(20);
```

### Android App

**1. State Hoisting**

```kotlin
// ✅ GOOD - State in ViewModel
class JadwalViewModel : ViewModel() {
    private val _state = MutableStateFlow(JadwalState())
    val state: StateFlow<JadwalState> = _state.asStateFlow()
}

// Composable just reads state
@Composable
fun JadwalScreen(viewModel: JadwalViewModel) {
    val state by viewModel.state.collectAsState()
    // UI renders based on state
}
```

**2. LaunchedEffect for One-time Load**

```kotlin
LaunchedEffect(Unit) {
    viewModel.loadJadwal()
}
```

**3. remember for Expensive Computations**

```kotlin
val groupedSchedules = remember(schedules) {
    schedules.groupBy { it.day_of_week }
}
```

---

## 🧪 TESTING CHECKLIST

### Unit Tests

- [ ] JadwalViewModel - loadJadwal() success
- [ ] JadwalViewModel - loadJadwal() error
- [ ] KehadiranViewModel - submitKehadiran() success
- [ ] KehadiranViewModel - validation
- [ ] RiwayatViewModel - loadRiwayat() pagination

### Integration Tests

- [ ] API call with valid token
- [ ] API call with invalid token (401)
- [ ] API call with network error
- [ ] Session expiry handling

### UI Tests (Compose)

- [ ] Bottom navigation switches screens
- [ ] JadwalScreen displays cards correctly
- [ ] KehadiranScreen form validation
- [ ] RiwayatScreen empty state
- [ ] Logout button works

---

## 📦 DEPENDENCIES

Add to `build.gradle.kts (app)`:

```kotlin
dependencies {
    // Jetpack Compose (sudah ada)
    implementation("androidx.compose.ui:ui:1.5.4")
    implementation("androidx.compose.material3:material3:1.1.2")

    // ViewModel & Lifecycle
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.6.2")
    implementation("androidx.lifecycle:lifecycle-runtime-compose:2.6.2")

    // Navigation Compose
    implementation("androidx.navigation:navigation-compose:2.7.5")

    // Retrofit (sudah ada)
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")

    // Coroutines (sudah ada)
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3")

    // Coil for Image Loading
    implementation("io.coil-kt:coil-compose:2.5.0")
}
```

---

## 🎯 IMPLEMENTATION ROADMAP

### Phase 1: Foundation (30 min)

- [x] Create SiswaRepository
- [x] Update ApiService with new endpoints
- [x] Create base ViewModels

### Phase 2: UI Screens (45 min)

- [x] JadwalScreen with LazyColumn
- [x] KehadiranScreen with Form
- [x] RiwayatScreen with Cards

### Phase 3: Navigation (15 min)

- [x] Bottom Navigation Setup
- [x] Screen Routing

### Phase 4: Testing & Polish (30 min)

- [x] Test all API calls
- [x] Error handling
- [x] Loading states
- [x] Empty states

**Total Time: ~2 hours**

---

## 🔍 DEBUGGING TIPS

### Common Issues

**1. API Returns 401 Unauthorized**

```kotlin
// Check token format
val token = "Bearer ${sessionManager.getAuthToken()}"
Log.d("API", "Token: $token")
```

**2. Class ID is null**

```kotlin
// Make sure saved on login
if (user.class_id == null) {
    Log.e("Login", "User has no class_id!")
    // Show error: "Hubungi admin untuk assign kelas"
}
```

**3. Compose Recomposition Loop**

```kotlin
// ❌ BAD - Creates new instance every recomposition
@Composable
fun Screen() {
    val viewModel = JadwalViewModel() // Don't!
}

// ✅ GOOD - Use hiltViewModel() or viewModel()
@Composable
fun Screen(viewModel: JadwalViewModel = viewModel()) {
    // ...
}
```

---

## 📚 RESOURCES

- [Jetpack Compose Docs](https://developer.android.com/jetpack/compose)
- [Material Design 3](https://m3.material.io/)
- [Kotlin Coroutines Guide](https://kotlinlang.org/docs/coroutines-guide.html)
- [Retrofit Documentation](https://square.github.io/retrofit/)

---

_Dokumentasi dibuat: 4 November 2025_
_Developer: Android + Laravel Team_
