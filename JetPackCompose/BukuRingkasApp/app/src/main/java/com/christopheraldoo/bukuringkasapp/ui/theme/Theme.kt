package com.christopheraldoo.bukuringkasapp.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

// Light color scheme untuk BukuRingkasApp
private val LightColorScheme = lightColorScheme(
    primary = PrimaryBlue,
    onPrimary = OnPrimaryLight,
    primaryContainer = PrimaryBlue,
    onPrimaryContainer = OnPrimaryLight,
    secondary = AccentYellow,
    onSecondary = Color.Black,
    secondaryContainer = AccentYellow,
    onSecondaryContainer = Color.Black,
    tertiary = SuccessGreen,
    onTertiary = Color.Black,
    tertiaryContainer = SuccessGreen,
    onTertiaryContainer = Color.Black,
    error = ErrorRed,
    onError = Color.White,
    errorContainer = ErrorRed,
    onErrorContainer = Color.White,
    background = BackgroundLight,
    onBackground = OnBackgroundLight,
    surface = SurfaceLight,
    onSurface = OnSurfaceLight,
    surfaceVariant = CardBackgroundLight,
    onSurfaceVariant = TextSecondaryLight,
    outline = CardBorderLight,
    outlineVariant = CardBorderLight,
    scrim = Color.Black.copy(alpha = 0.32f),
    inverseSurface = Color.Black,
    inverseOnSurface = Color.White,
    inversePrimary = PrimaryBlue,
    surfaceTint = PrimaryBlue
)

// Dark color scheme (akan diimplementasi nanti)
private val DarkColorScheme = darkColorScheme(
    primary = PrimaryDark,
    onPrimary = OnPrimaryDark,
    primaryContainer = PrimaryDark,
    onPrimaryContainer = OnPrimaryDark,
    secondary = AccentYellow,
    onSecondary = Color.Black,
    secondaryContainer = AccentYellow,
    onSecondaryContainer = Color.Black,
    tertiary = SuccessGreen,
    onTertiary = Color.Black,
    tertiaryContainer = SuccessGreen,
    onTertiaryContainer = Color.Black,
    error = ErrorRed,
    onError = Color.White,
    errorContainer = ErrorRed,
    onErrorContainer = Color.White,
    background = BackgroundDark,
    onBackground = OnBackgroundDark,
    surface = SurfaceDark,
    onSurface = OnSurfaceDark,
    surfaceVariant = CardBackgroundDark,
    onSurfaceVariant = TextSecondaryDark,
    outline = CardBorderDark,
    outlineVariant = CardBorderDark,
    scrim = Color.White.copy(alpha = 0.32f),
    inverseSurface = Color.White,
    inverseOnSurface = Color.Black,
    inversePrimary = PrimaryDark,
    surfaceTint = PrimaryDark
)

/**
 * Tema utama untuk BukuRingkasApp
 * Menggunakan Material 3 dengan warna edukatif yang lembut
 */
@Composable
fun BukuRingkasAppTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) {
        DarkColorScheme
    } else {
        LightColorScheme
    }

    // Mengatur status bar dan navigation bar
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = colorScheme.background.toArgb()
            window.navigationBarColor = colorScheme.surface.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
            WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = BukuRingkasTypography,
        content = content
    )
}

/**
 * Fungsi utility untuk mendapatkan warna subject berdasarkan nama mata pelajaran
 */
@Composable
fun getSubjectColor(subject: String): Color {
    return when (subject.lowercase()) {
        "matematika" -> MathColor
        "fisika" -> PhysicsColor
        "kimia" -> ChemistryColor
        "biologi" -> BiologyColor
        "bahasa indonesia" -> IndonesianColor
        "bahasa inggris" -> EnglishColor
        "sejarah" -> HistoryColor
        "geografi" -> GeographyColor
        "ekonomi" -> EconomicsColor
        else -> PrimaryBlue // Default color
    }
}

/**
 * Fungsi utility untuk mendapatkan nama subject yang diformat dengan baik
 */
fun formatSubjectName(subject: String): String {
    return subject.split(" ").joinToString(" ") {
        it.lowercase().replaceFirstChar { char -> char.uppercase() }
    }
}

/**
 * Fungsi utility untuk mendapatkan warna grade badge
 */
@Composable
fun getGradeColor(grade: Int): Color {
    return when (grade) {
        10 -> MathColor      // SD kelas 4-6 atau SMA kelas 10
        11 -> PhysicsColor   // SMA kelas 11
        12 -> ChemistryColor // SMA kelas 12
        else -> PrimaryBlue  // Default
    }
}
