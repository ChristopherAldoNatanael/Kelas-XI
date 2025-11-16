package com.christopheraldoo.aplikasimonitoringkelas.ui.navigation

/**
 * Sealed class untuk navigation routes di Role Siswa
 * Menggunakan Jetpack Compose Navigation
 */
sealed class SiswaScreen(val route: String, val title: String, val icon: String) {
    object Jadwal : SiswaScreen("jadwal", "Jadwal", "schedule")
    object Kehadiran : SiswaScreen("kehadiran", "Kehadiran", "check_circle")
    object Riwayat : SiswaScreen("riwayat", "Riwayat", "history")
}

/**
 * List semua screens untuk bottom navigation
 */
val siswaScreens = listOf(
    SiswaScreen.Jadwal,
    SiswaScreen.Kehadiran,
    SiswaScreen.Riwayat
)
