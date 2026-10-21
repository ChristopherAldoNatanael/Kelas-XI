# PHASE 1 — Trust & Polish

**Tanggal:** 2026-10-20
**Status:** Selesai
**Roadmap:** `docs/UIUX_ROADMAP.md`

## 1. Tujuan

Menghilangkan sinyal "demo" dari aplikasi dan membuat shell admin terasa
seperti produk produksi: responsif, cepat, jujur, dan punya error state yang
layak. Fase ini **tidak** menambah modul bisnis baru.

## 2. Yang diubah

### 2.1 Admin shell responsif

File: `resources/views/layouts/admin.blade.php`

- Sidebar menjadi drawer di layar < 1024px (`layouts/admin.blade.php:44`),
  dengan backdrop, tombol buka (`:230`), tombol tutup, dan penutupan saat
  Escape/klik link.
- Preferensi collapse sidebar disimpan di `localStorage`; collapse hanya
  berlaku di desktop, jadi drawer mobile tidak ikut mengecil.
- Skip link (`layouts/admin.blade.php:38`), `aria-current="page"` pada menu
  aktif, `aria-expanded` pada tombol, dan focus ring global.
- Header dirapikan: judul `truncate`, kontrol tanggal/clinic switcher
  disembunyikan di mobile, language switcher mempertahankan query filter.
- Toast ditulis ulang memakai `textContent` (bukan `innerHTML`) agar pesan
  session tidak bisa menjadi HTML injection (`layouts/admin.blade.php:361`).

File: `resources/css/app.css`

- Design system pindah dari inline `<style type="text/tailwindcss">` ke CSS
  build. Token `--color-primary`, `--color-secondary`, `--color-background-*`
  didefinisikan di `@theme` (`app.css:8`).
- `@custom-variant dark` agar `dark:` mengikuti class `.dark`, bukan hanya
  `prefers-color-scheme`.
- Style sidebar mobile/desktop di `app.css:143-230`, toast di `:265`.
- `prefers-reduced-motion` dihormati.

### 2.2 Build produksi (tanpa CDN)

- `resources/views/layouts/admin.blade.php:31` memakai
  `@vite(['resources/css/app.css', 'resources/js/app.js'])`.
- `resources/js/app.js` meng-import `chart.js/auto` dan mengekspos
  `window.Chart`, sehingga tidak ada lagi `cdn.jsdelivr.net`.
- CDN Tailwind dihapus. Build menghasilkan
  `public/build/assets/app-*.css` dan `app-*.js` (lihat verifikasi).
- **Konsekuensi operasional:** setelah clone/ubah CSS, jalankan
  `npm install` lalu `npm run build`. `public/build` memang di-ignore Git,
  jadi jangan mengandalkan artefak lama.

### 2.3 Dashboard jujur

File: `app/Http/Controllers/Admin/DashboardController.php`

- Perbandingan MTD memakai periode yang sama bulan lalu, bukan bulan penuh
  (`DashboardController.php:76-91`). Jika baseline 0, growth = `null`.
- Perbandingan revenue hari ini memakai kemarin (`:89-93`).
- `totalBilled` dan `collectionRate` dihitung dari `paid_amount` +
  `remaining_amount` (`:122-125`), bukan progress bar hardcoded.
- Cache key dinaikkan ke `dashboard_stats_v2_*` agar payload lama tidak
  menyebabkan `Undefined array key`.

File: `resources/views/admin/dashboard.blade.php`

- Badge `+12.4%`, `+5.1%`, progress `75%`, `0.42ms`, dan storage `65.2%`
  dihapus.
- Kartu revenue menampilkan `No baseline` jika belum ada pembanding
  (`dashboard.blade.php:80`), plus tooltip periode pembanding.
- Kartu ketiga menjadi **Collection Rate** nyata (`:147`).
- Kartu **Infrastructure** diganti **Action Center** (`:493`) berisi pending
  confirmation, payment processing, failed payments, dan outstanding balance.
  Semua link memakai filter yang benar-benar ada di controller.
- Chart.js dijalankan setelah `load` (`:560`) supaya bundle module sudah siap,
  memakai warna primary klinik, dan menyesuaikan warna grid di dark mode.

### 2.4 Tabel booking

File: `resources/views/admin/bookings/index.blade.php`

- Header dan sel sekarang sama-sama 9 kolom; sebelumnya header 8, sel 10
  (tabel bergeser). Kolom `Service` menampung nama + harga
  (`bookings/index.blade.php:59`), kolom harga terpisah dihapus.
- Foto memakai `photo_url` accessor, bukan `asset('storage/...')`
  (`:72`, `:87`). Ini memperbaiki foto yang tidak tampil saat path storage
  berbeda.
- Tabel diberi `min-w-[1040px]` (`:52`) agar bisa di-scroll horizontal tanpa
  memaksa kolom mengecil.

### 2.5 Error page branded

File: `resources/views/errors/layout.blade.php` + `404/419/500/503.blade.php`.

- Tanpa dependensi Vite/DB, jadi tetap tampil saat asset atau database gagal.
- Copy menjelaskan langkah berikutnya, bukan sekadar "Whoops".
- 419 mengarah ke login ulang; 404/500/503 menyediakan jalan kembali.

### 2.6 Android: notifikasi & deep link

File: `service/PetHealFirebaseService.kt`

- Channel per kategori: booking, reminder, payment, general
  (`PetHealFirebaseService.kt:172`). Nama/deskripsi dari `strings.xml`,
  bukan literal "PetHeal Notifications".
- ID notifikasi stabil per entity (`:135`) supaya update status mengganti
  kartu lama, bukan menumpuk duplikat.
- Reminder memakai `CATEGORY_REMINDER` (`:158`).

File: `MainActivity.kt`, `AndroidManifest.xml`

- `launchMode="singleTop"` + intent-filter `petheal://open/...`.
- Handler deep link (`MainActivity.kt:117`) mendukung booking, record,
  pet, doctor, notifications, bookings, profile.
- `clearNotificationIntent` juga menghapus `intent.data` (`:157`) agar deep
  link tidak diproses dua kali saat activity di-resume.

File: `res/values/strings.xml`

- 8 string channel notifikasi (nama + deskripsi).

## 3. Verifikasi yang sudah dijalankan

| Cek | Hasil |
| --- | --- |
| `npm install` + `npm run build` | Sukses; CSS 96.83 kB (gzip 15.36 kB), JS 258.46 kB (gzip 89.86 kB) |
| `@vite` di layout | Asset build termuat, tidak ada CDN Tailwind/Chart.js di HTML |
| Dashboard admin | `200`; Action Center, Collection Rate, `No baseline` tampil |
| Angka palsu | `+12.4%`, `width: 75%`, `65.2%`, `0.42ms` tidak ada di HTML |
| Smoke test 13 route admin | Semua `200`, tidak ada `Undefined variable`/`SQLSTATE` |
| Tabel booking | 9 header, `Service`, `min-w-[1040px]`, `photo_url` |
| Error 404 | `404`, copy custom tampil, bukan Ignition |
| `.\gradlew.bat assembleDebug` | `BUILD SUCCESSFUL` |

Catatan verifikasi: MySQL lokal sempat mati; pengujian backend dilakukan
setelah `mysqld` Laragon dinyalakan. Ini masalah environment, bukan kode.

## 4. Keputusan penting

- **Tailwind v4 + Vite**, bukan Tailwind CDN. Alasan: build produksi, tidak
  bergantung pada CDN, dan utility `dark:` bisa dikontrol.
- **Chart.js dibundel** agar dashboard tetap satu origin dan tidak kena
  blokir jaringan saat presentasi.
- **Tidak memasang pull-to-refresh** di fase ini karena versi Material3
  existing belum tentu punya API stabil; dipindah ke backlog agar tidak
  menambah dependency/implementasi setengah jadi.
- **Tidak mengubah skema database** di fase ini.
- **Deep link memakai custom scheme** (`petheal://`), belum HTTPS app link,
  karena app link butuh `assetlinks.json` dan domain tetap.

## 5. Next step (jangan bikin interpretasi baru)

1. **Fase 2 sudah selesai** untuk scope health (lihat
   `docs/phases/PHASE_2_CORE_LOOP.md`). Jangan menambah booking slot/invoice
   di Fase 2.
2. Lanjut ke **Fase 3** dengan urutan:
   booking slot picker → invoice center → schedule board → command palette.
3. **Jangan** membuat mock UI membership/SaaS/chat. Itu Fase 4 dan butuh
   persetujuan user.

## 6. File yang disentuh

### Backend

- `resources/views/layouts/admin.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/bookings/index.blade.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `resources/views/errors/layout.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`
- `package.json` (chart.js)

### Android

- `app/src/main/java/com/christopheraldoo/petheal/MainActivity.kt`
- `app/src/main/java/com/christopheraldoo/petheal/service/PetHealFirebaseService.kt`
- `app/src/main/AndroidManifest.xml`
- `app/src/main/res/values/strings.xml`
