package com.christopheraldoo.petheal.ui.theme

/**
 * Palet semantik aplikasi — **satu-satunya** nama warna yang dipakai layar.
 *
 * Sebelumnya setiap layar mendefinisikan alias sendiri (`MrPrimary`, `BkPrimary`,
 * `PetPrimary`, `SrPrimary`, `BsPrimary`, …) yang semuanya menunjuk token yang
 * sama di `Color.kt`. Akibatnya mengganti satu warna berarti menyunting 4–8 file,
 * dan mudah ada layar yang tertinggal sehingga tampilan jadi tidak seragam.
 *
 * Aturan pakai:
 * - Layar memakai `Brand.*` untuk warna, bukan `Color(0xFF…)` dan bukan token
 *   `Color.kt` langsung (kecuali di `ui/theme/` sendiri).
 * - Warna **gradien tidak dipakai** di header/kartu. Bila butuh latar foto yang
 *   terbaca, gunakan scrim linear pada foto itu saja.
 * - Nama di sini menjelaskan peran, bukan rona: `accent` (aksi utama), `card`
 *   (permukaan), `border`, `textPrimary`, dan seterusnya. Dengan begitu tema
 *   gelap atau rebranding cukup mengubah nilai di `Color.kt`.
 */
object Brand {

    // ── Warna aksi utama ────────────────────────────────────────────────────
    /** Hijau utama: tombol utama, ikon aktif, badge. */
    val primary = Primary

    /** Hijau tua: latar header solid, teks di atas latar terang. */
    val primaryDeep = PrimaryDark

    /** Hijau sangat muda: latar lembut, chip tidak aktif. */
    val primarySoft = PrimaryLight

    /** Hijau pucat untuk sorotan halus (mis. baris terpilih). */
    val primaryWash = PrimaryWash

    /** Teks/ikon di atas bidang `primary` atau `primaryDeep`. */
    val onAccent = OnPrimaryStrong

    /** Warna sekunder (teal) untuk aksen non-utama. */
    val secondary = Secondary

    // ── Permukaan & garis ───────────────────────────────────────────────────
    /** Latar halaman. */
    val page = Background

    /** Latar kartu / sheet. */
    val card = Surface

    /** Permukaan alternatif yang sedikit lebih gelap dari `card`. */
    val cardAlt = SurfaceVariant

    /** Abu-abu netral untuk blok informasi pasif. */
    val slateSoft = SurfaceMuted

    /** Garis tepi halus standar. */
    val border = Outline

    /** Garis tepi lebih tegas untuk bidang gelap/berwarna. */
    val borderStrong = OutlineStrong

    // ── Teks ────────────────────────────────────────────────────────────────
    /** Teks utama. */
    val textPrimary = OnBackground

    /** Teks pendukung (keterangan, label). */
    val textSecondary = TextSecondary

    /** Teks paling redup (placeholder, satuan). */
    val textTertiary = TextTertiary

    // ── Status & semantik ───────────────────────────────────────────────────
    /** Kesalahan yang butuh tindakan (tombol hapus, validasi). */
    val danger = Error

    /** Varian merah yang lebih tegas untuk teks/ikon. */
    val rose = ErrorStrong

    /** Latar merah muda lembut. */
    val roseSoft = ErrorSoft

    /** Konfirmasi/berhasil. */
    val success = Success

    /** Latar hijau lembut untuk status berhasil. */
    val successSoft = SuccessSoft

    /** Hijau tua untuk teks status berhasil. */
    val successDeep = SuccessDeep

    /** Informasi netral (biru). */
    val sky = Info

    /** Biru lebih tua untuk teks di atas `skySoft`. */
    val skyDeep = InfoStrong

    /** Latar biru lembut. */
    val skySoft = InfoSoft

    /** Peringatan (oranye). */
    val amber = Tertiary

    /** Oranye tua untuk teks peringatan. */
    val amberDeep = WarningDeep

    /** Latar oranye lembut. */
    val amberSoft = WarningSoft

    /** Teks di atas `amberSoft`. */
    val amberText = WarningText

    /** Netral untuk status tidak diketahui. */
    val neutral = Neutral

    /** Aksen ungu (mis. layanan khusus). */
    val violet = AccentViolet

    /** Bintang rating. */
    val star = Star
}
