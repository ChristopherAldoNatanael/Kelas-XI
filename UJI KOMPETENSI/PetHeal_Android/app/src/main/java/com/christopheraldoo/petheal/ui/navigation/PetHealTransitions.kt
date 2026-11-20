package com.christopheraldoo.petheal.ui.navigation

import androidx.compose.animation.EnterTransition
import androidx.compose.animation.ExitTransition
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.Easing
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.slideOutVertically

/**
 * SISTEM GERAK APLIKASI — satu sumber untuk semua halaman.
 *
 * Sebelum ini setiap perpindahan memakai animasi bawaan Navigation
 * (slide penuh selebar layar), sementara pergantian tab tidak dianimasikan
 * sama sekali. Akibatnya ritme aplikasi tidak konsisten: halaman detail
 * terasa "melompat", tab terasa "berkedip".
 *
 * Aturan di file ini:
 * 1. Durasi & kurva HANYA didefinisikan di [PetHealMotion] supaya semua
 *    layar bergerak dengan tempo yang sama.
 * 2. Tiga jenis gerak, dipilih dari route tujuan — bukan ditebak per layar:
 *    • tab        → fade-through (pudar + skala kecil + naik tipis)
 *    • detail    → dorong horizontal ber-parallax (halaman lama mundur)
 *    • transaksi  → naik dari bawah seperti lembar modal
 * 3. Tidak ada animasi yang menghalangi input: durasi maksimum 340 ms.
 */
object PetHealMotion {
    /** Halaman baru masuk (dorong ke depan). */
    const val PushMs = 340

    /** Halaman lama kembali (pop / tombol kembali). */
    const val PopMs = 300

    /** Pergantian tab utama. */
    const val TabMs = 260

    /** Layar transaksi yang naik seperti lembar modal. */
    const val SheetMs = 330

    /** Aksen brand (splash → layar berikutnya). */
    const val BrandMs = 420

    /** Kurva deselerasi — dipakai untuk semua gerak MASUK. */
    val EaseOut: Easing = CubicBezierEasing(0.22f, 1f, 0.36f, 1f)

    /** Kurva akselerasi — dipakai untuk semua gerak KELUAR. */
    val EaseIn: Easing = CubicBezierEasing(0.4f, 0f, 1f, 1f)
}

// ── 1. Tab utama: fade-through ────────────────────────────────────────────────
// Halaman lama memudar & sedikit membesar (seolah menjauh), halaman baru
// memudar masuk dari 96% skala dan naik ~2% tinggi layar. Ini pola
// Material "fade through" — tenang, tanpa arah kiri/kanan yang salah.
private fun tabEnter(): EnterTransition =
    fadeIn(tween(PetHealMotion.TabMs, delayMillis = 70, easing = PetHealMotion.EaseOut)) +
        scaleIn(
            animationSpec = tween(PetHealMotion.TabMs, easing = PetHealMotion.EaseOut),
            initialScale = 0.96f
        ) +
        slideInVertically(
            animationSpec = tween(PetHealMotion.TabMs, easing = PetHealMotion.EaseOut),
            initialOffsetY = { it / 40 }
        )

private fun tabExit(): ExitTransition =
    fadeOut(tween(140, easing = PetHealMotion.EaseIn)) +
        scaleOut(
            animationSpec = tween(160, easing = PetHealMotion.EaseIn),
            targetScale = 1.02f
        )

// ── 2. Halaman detail: dorong + parallax ─────────────────────────────────────
// Halaman baru hanya bergeser 1/5 lebar layar (bukan penuh), halaman lama
// mundur lebih lambat (1/8) sambil mengecil — memberi rasa kedalaman, bukan
// seperti berpindah slide presentasi.
private fun pushEnter(): EnterTransition =
    slideInHorizontally(
        animationSpec = tween(PetHealMotion.PushMs, easing = PetHealMotion.EaseOut),
        initialOffsetX = { it / 5 }
    ) +
        fadeIn(tween(PetHealMotion.PushMs - 80, easing = PetHealMotion.EaseOut)) +
        scaleIn(
            animationSpec = tween(PetHealMotion.PushMs, easing = PetHealMotion.EaseOut),
            initialScale = 0.97f
        )

private fun pushExit(): ExitTransition =
    slideOutHorizontally(
        animationSpec = tween(PetHealMotion.PushMs, easing = PetHealMotion.EaseOut),
        targetOffsetX = { -it / 8 }
    ) +
        fadeOut(tween(PetHealMotion.PushMs / 2, easing = PetHealMotion.EaseIn)) +
        scaleOut(
            animationSpec = tween(PetHealMotion.PushMs, easing = PetHealMotion.EaseOut),
            targetScale = 0.98f
        )

private fun popEnter(): EnterTransition =
    slideInHorizontally(
        animationSpec = tween(PetHealMotion.PopMs, easing = PetHealMotion.EaseOut),
        initialOffsetX = { -it / 8 }
    ) +
        fadeIn(tween(PetHealMotion.PopMs - 60, easing = PetHealMotion.EaseOut)) +
        scaleIn(
            animationSpec = tween(PetHealMotion.PopMs, easing = PetHealMotion.EaseOut),
            initialScale = 0.98f
        )

private fun popExit(): ExitTransition =
    slideOutHorizontally(
        animationSpec = tween(PetHealMotion.PopMs, easing = PetHealMotion.EaseOut),
        targetOffsetX = { it / 5 }
    ) +
        fadeOut(tween(PetHealMotion.PopMs / 2, easing = PetHealMotion.EaseIn)) +
        scaleOut(
            animationSpec = tween(PetHealMotion.PopMs, easing = PetHealMotion.EaseOut),
            targetScale = 0.97f
        )

// ── 3. Layar transaksi: naik dari bawah ──────────────────────────────────────
private fun sheetEnter(): EnterTransition =
    slideInVertically(
        animationSpec = tween(PetHealMotion.SheetMs, easing = PetHealMotion.EaseOut),
        initialOffsetY = { it / 3 }
    ) +
        fadeIn(tween(200, easing = PetHealMotion.EaseOut))

private fun sheetExit(): ExitTransition =
    slideOutVertically(
        animationSpec = tween(280, easing = PetHealMotion.EaseIn),
        targetOffsetY = { it / 4 }
    ) +
        fadeOut(tween(180, easing = PetHealMotion.EaseIn))

/** Halaman di bawah lembar modal: mundur sedikit, bukan ikut bergeser. */
private fun recedeExit(): ExitTransition =
    scaleOut(
        animationSpec = tween(PetHealMotion.SheetMs, easing = PetHealMotion.EaseOut),
        targetScale = 0.96f
    ) + fadeOut(tween(160, easing = PetHealMotion.EaseIn))

/** Halaman di bawah lembar modal kembali ke ukuran penuh setelah lembar tutup. */
private fun recedeEnter(): EnterTransition =
    scaleIn(
        animationSpec = tween(PetHealMotion.PopMs, easing = PetHealMotion.EaseOut),
        initialScale = 0.96f
    ) + fadeIn(tween(200, easing = PetHealMotion.EaseOut))

// ── 4. Splash → layar berikutnya: silang-pudar brand ─────────────────────────
private fun brandEnter(): EnterTransition =
    fadeIn(tween(PetHealMotion.BrandMs, easing = PetHealMotion.EaseOut)) +
        scaleIn(
            animationSpec = tween(PetHealMotion.BrandMs, easing = PetHealMotion.EaseOut),
            initialScale = 1.03f
        )

private fun brandExit(): ExitTransition = fadeOut(tween(240, easing = PetHealMotion.EaseIn))

// ── Pemetaan route → jenis gerak ─────────────────────────────────────────────

/** Tab utama (dipasang di bottom navigation). */
private val TabRoots = setOf(
    Screen.Home.route,
    Screen.Pets.route,
    Screen.Bookings.route,
    Screen.MedicalRecords.route,
    Screen.Profile.route
)

/**
 * Layar yang tampil seperti lembar modal: form tambah/ubah, checkout, dan
 * pembayaran. Semuanya meminta perhatian penuh lalu ditutup kembali.
 */
private val SheetScreens = setOf(
    Screen.AddPet.route,
    Screen.EditPet.route,
    Screen.CreateBooking.route,
    Screen.Payment.route,
    Screen.MedicalExtraPayment.route,
    Screen.PetShopCheckout.route,
    Screen.PetShopReceipt.route
)

/** Halaman masuk (masuk aplikasi) yang layak pakai silang-pudar brand. */
private val BrandScreens = setOf(Screen.Splash.route, Screen.Login.route)

fun petHealEnterTransition(initialRoute: String?, targetRoute: String?): EnterTransition =
    when {
        initialRoute == Screen.Splash.route -> brandEnter()
        initialRoute in TabRoots && targetRoute in TabRoots -> tabEnter()
        targetRoute in SheetScreens -> sheetEnter()
        else -> pushEnter()
    }

fun petHealExitTransition(initialRoute: String?, targetRoute: String?): ExitTransition =
    when {
        initialRoute == Screen.Splash.route -> brandExit()
        initialRoute in TabRoots && targetRoute in TabRoots -> tabExit()
        targetRoute in SheetScreens -> recedeExit()
        else -> pushExit()
    }

fun petHealPopEnterTransition(initialRoute: String?, targetRoute: String?): EnterTransition =
    when {
        initialRoute in TabRoots && targetRoute in TabRoots -> tabEnter()
        initialRoute in SheetScreens -> recedeEnter()
        targetRoute in BrandScreens -> brandEnter()
        else -> popEnter()
    }

fun petHealPopExitTransition(initialRoute: String?, targetRoute: String?): ExitTransition =
    when {
        initialRoute in TabRoots && targetRoute in TabRoots -> tabExit()
        initialRoute in SheetScreens -> sheetExit()
        targetRoute in BrandScreens -> brandExit()
        else -> popExit()
    }
