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

**Nama warna yang dipakai layar adalah `Brand.*`** dari `ui/theme/Brand.kt`
(`Brand.primary`, `Brand.card`, `Brand.textSecondary`, `Brand.danger`, …).
Objek itu hanya memetakan peran ke token di atas, jadi satu warna bisa diubah
dari satu tempat.

Alias per layar (`MrPrimary`, `BkSurface`, `PetPrimary`, `SrSurface`, `BsBorder`,
`TextPrimary`, `SurfaceDark`, …) **sudah dihapus** — jangan dibuat lagi. Dulu
satu layar punya 6–16 alias sendiri, sehingga mengganti satu warna berarti
menyunting 4–8 file dan mudah ada layar yang tertinggal.

**Tanpa gradien dekoratif.** Header, kartu, dan tombol memakai satu warna solid
(`Brand.primaryDeep` untuk header). Gradien hanya boleh sebagai *scrim* di atas
foto agar teks terbaca, dan di asset brand pada `ui/screens/auth/`.

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

Urutan interceptor di `di/AppModule.kt`: auth (`NetworkInterceptor`) → cache →
`RetryInterceptor` → timeout → logging. `RetryInterceptor` hanya mengulang
**GET** sekali pada 408/429/5xx (POST/PUT/DELETE tidak pernah diulang agar
transaksi tidak ganda).

Token & slug klinik dibaca dari memori, bukan DataStore: `NetworkInterceptor`
diisi sekali di awal oleh `data/remote/SessionCacheBootstrap.kt`
(dipanggil `PetHealApplication`). Jangan menambahkan `runBlocking` di interceptor.

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

Pola penamaan: `<layar>_<bagian>_<arti>` (mis. `petshop_receipt_label_order`)
dan `common_*` untuk aksi yang dipakai dua layar atau lebih (`common_retry`,
`common_save_png`, `common_check_status`). Contoh migrasi lengkap:
`ui/screens/petshop/PetShopReceiptScreen.kt`.

Layar lama masih memakai literal inline — saat menyentuh sebuah layar, pindahkan
string yang terlihat pengguna ke `strings.xml`, bukan menambah literal baru.

## 9. Area sentuh minimum 48dp

Setiap elemen yang bisa disentuh minimal 48×48dp, walau bentuk visualnya lebih
kecil. Caranya:

- Tombol aksi: `Modifier.heightIn(min = Dimens.MinTouchTarget)` — jangan
  `.height(44.dp)` tetap, karena teks terpotong saat pengaturan font HP besar.
- Tombol ikon bulat di hero: lingkaran visual boleh 42dp, tetapi `Box` yang
  memegang `clickable` berukuran `Dimens.MinTouchTarget` (lihat
  `ShopRoundButton` di `ui/screens/petshop/PetShopComponents.kt`).
- Chip: bungkus pil ramping di dalam `Box` 48dp yang memegang `clickable`
  (lihat `ShopFilterChip`, `InvoiceFilterChip`, `SelectChip`, `DateChip`).
- `Dimens.MinTouchTarget`/`Dimens.ButtonHeight` ada di
  `ui/components/CommonComponents.kt`; `Modifier.minTapTarget()` setara
  `minimumInteractiveComponentSize()`.

## 10. Gerak (motion) — satu sistem

Semua transisi halaman diatur satu tempat: `ui/navigation/PetHealTransitions.kt`
(`PetHealMotion`: `PushMs 340`, `PopMs 300`, `TabMs 260`, `SheetMs 330`,
`BrandMs 420`). Jenis animasi dipilih dari nama route (`TabRoots`, `SheetScreens`,
`BrandScreens`) — layar tidak perlu (dan tidak boleh) menambahkan animasi masuk
sendiri. Mengubah “rasa” aplikasi = mengubah 5 konstanta itu saja.

Anti ketuk ganda ditangani global oleh `ui/navigation/NavGate.kt` (400ms untuk
navigasi, 300ms untuk kembali) di dalam `goTo()`/`goBack()` pada `PetHealNavHost`.
Alur 401 (`PetHealNavHost.kt`, logout paksa) sengaja melewati gate.

## 11. State Flow di Composable

Pakai `collectAsStateLifecycleAware()` dari `ui/util/LifecycleAwareState.kt`,
bukan `collectAsState()`. Versi lifecycle-aware berhenti mengoleksi saat aplikasi
di background (hemat CPU/bateri) dan mempertahankan nilai terakhir sehingga layar
tidak berkedip saat kembali.

```kotlin
val uiState by viewModel.uiState.collectAsStateLifecycleAware()
```

## 12. Build & rilis

- Versi ada di `app/build.gradle.kts` (`versionCode`, `versionName`). Naikkan
  keduanya setiap kali APK dibagikan.
- Tanda tangan rilis dibaca dari `keystore.properties` (tidak di-commit; lihat
  `keystore/README.md`). Tanpa berkas itu build tetap jalan, tetapi keluarannya
  `app-release-unsigned.apk` yang tidak bisa dipasang.
- Cleartext HTTP hanya diizinkan pada build **debug** untuk `10.0.2.2`,
  `localhost`, `127.0.0.1` (`app/src/debug/res/xml/network_security_config.xml`).
  Build rilis memakai `app/src/main/res/xml/network_security_config.xml` yang
  menolak semua cleartext.
- `isShrinkResources = true` pada rilis; jangan matikan tanpa mengukur ukuran.
