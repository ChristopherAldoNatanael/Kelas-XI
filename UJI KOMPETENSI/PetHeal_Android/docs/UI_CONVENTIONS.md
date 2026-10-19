# UI_CONVENTIONS.md — Konvensi UI/UX PetHeal Android

> Dibaca bersama `AGENTS.md`. Tujuannya: satu perilaku visual di semua layar,
> dan alur yang tidak membingungkan pembaca kode. Semua contoh memakai `path:line`.

## 1. Warna — satu sumber: `ui/theme/Color.kt`

**Dilarang menulis `Color(0xFF...)` baru di layar.** Pakai token semantik:

| Kebutuhan | Token |
|---|---|
| Brand / aksen utama | `Primary`, `PrimaryDark`, `PrimaryLight`, `PrimaryWash` |
| Teks utama / sekunder / tersier | `OnBackground`, `TextSecondary`, `TextTertiary`, `TextMuted` |
| Latar layar / kartu / baris halus | `Background`, `Surface`, `SurfaceVariant`, `SurfaceMuted` |
| Garis & border | `Outline`, `OutlineStrong` |
| Aksi destruktif (hapus, batal, gagal) | `ErrorStrong`, `ErrorSoft`, `Error` |
| Konfirmasi positif (lunas, berhasil) | `Success`, `SuccessSoft`, `SuccessDeep` |
| Info / jadwal / in-progress / bank transfer | `Info`, `InfoStrong`, `InfoSoft` |
| Peringatan / DP / menunggu | `Tertiary`, `WarningSoft`, `WarningDeep`, `WarningText` |
| Netral / tidak diketahui | `Neutral` |
| Rating bintang | `Star` |
| Aksen E-Wallet & follow-up | `AccentViolet` |
| Teks/ikon di atas `Primary` | `OnPrimaryStrong` |
| Status booking | `StatusPending`, `StatusConfirmed`, `StatusCompleted`, `StatusCancelled`, `StatusRejected`, `StatusPaid` |

Alias lokal per layar **boleh** ada (mis. `BkPrimary`, `MrTextPrimary`), tetapi
nilainya wajib menunjuk token di atas — bukan hex. Contoh: `HomeScreen.kt:76`.

> Sisanya masih ada ±35 hex satu-off yang belum ditoken; kalau menyentuh area
> itu, ganti ke token yang paling dekat.

## 2. Dark mode

Aplikasi ini **light-only**: `PetHealTheme` memaksa `darkTheme = false`
(`ui/theme/Theme.kt`). Jangan menambah `val isDark`/cabang `if (isDark) … else …`
atau nilai gelap baru — dulu ada 161 baris seperti itu dan semuanya kode mati
(sudah dihapus).

## 3. Loading / empty / error

Pakai komponen bersama, jangan bikin versi lokal baru:

- `ui/components/SkeletonLoading.kt` → `SkeletonDoctorList()`, `SkeletonBookingList()`, `SkeletonText()`, `SkeletonAvatar()`, `SkeletonCard()`
- `ui/components/EmptyState.kt` → `EmptyState(...)` + preset `EmptyBookingsState`, `EmptyPetsState`, `EmptyNotificationsState`, `EmptySearchState`
- `ui/components/ErrorState.kt` → `ErrorState(title, message, primaryActionText, onPrimaryActionClick, secondaryActionText, onSecondaryActionClick)`

Semua teks error/aksi ditulis lewat `strings.xml`.

## 4. Formatter — satu sumber: `util/Formatters.kt`

| Kebutuhan | Fungsi |
|---|---|
| Uang penuh | `formatRupiah(1500000.0)` → `Rp 1.500.000` |
| Uang ringkas (kartu metrik) | `formatRupiahCompact(...)` → `Rp 1,5 jt` |
| Tanggal ISO → tampilan | `formatDateSafe(value, "dd MMM yyyy")` |
| Label ulasan | `reviewCountLabel(3)` → `3 ulasan` |
| Rating | `formatAvgRating(4.75)` → `4.8` |

**Dilarang** menulis `String.format("%,.0f", …)` atau `"Rp ${...}"` langsung di
Composable — dulu Home dan Rekam Medis menampilkan angka yang sama dengan format
berbeda.

## 5. Navigasi tab

`PetHealNavHost.kt` → `navigateTab` memakai `popUpTo(Home) { saveState = true }`
+ `launchSingleTop` + `restoreState = true`. Artinya filter/pencarian/posisi
scroll tiap tab bertahan saat berpindah tab. Jangan hapus `saveState`/
`restoreState` tanpa alasan: tanpa keduanya, tiap pindah tab me-reset state dan
memuat ulang data dari server.

## 6. Jaringan

Satu sumber kebenaran: `data/remote/NetworkMonitor.kt`
(`isOnline` Flow + `isOnlineNow()`). Dipakai oleh splash dan oleh interceptor
cache di `di/AppModule.kt`. Jangan membuat pengecekan konektivitas baru di layar
lain.

## 7. Insets (edge-to-edge)

`MainActivity` memakai `enableEdgeToEdge()` + `setDecorFitsSystemWindows(false)`,
jadi setiap layar wajib menangani insets sendiri:

```kotlin
Modifier.statusBarsPadding().imePadding().navigationBarsPadding()
```

Urutan itu penting: `imePadding()` lebih dulu agar IME dan navigation bar tidak
dihitung dua kali. Contoh: `ui/screens/auth/LoginScreen.kt` dan `RegisterScreen.kt`.
Layar yang memakai `Scaffold + TopAppBar` sudah otomatis (M3 mengurusnya).

## 8. Teks

Salinan baru untuk pengguna masuk ke `res/values/strings.xml`; literal panjang di
Composable hanya untuk konten dari API (nama dokter, klinik, hewan).
