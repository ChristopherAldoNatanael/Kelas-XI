package com.christopheraldoo.aplikasimonitoringkelas.ui.components

import androidx.compose.ui.graphics.vector.ImageVector

/**
 * Data class for bottom navigation items
 * Used across different activities (Siswa, Kurikulum, Kepala Sekolah, etc.)
 */
data class BottomNavItem(
    val route: String,
    val label: String,
    val icon: ImageVector
)
