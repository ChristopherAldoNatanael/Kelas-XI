package com.christopheraldoo.aplikasimonitoringkelas.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Light theme colors
private val LightColorScheme = lightColorScheme(
    primary = Color(0xFF1565C0), // Deep Blue
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD1E3F9),
    onPrimaryContainer = Color(0xFF0A325B),
    secondary = Color(0xFF26A69A), // Teal
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFB3E5FC),
    onSecondaryContainer = Color(0xFF003543),
    tertiary = Color(0xFF8E24AA), // Purple
    onTertiary = Color.White,
    tertiaryContainer = Color(0xFFF3E5F5),
    onTertiaryContainer = Color(0xFF4A1452),
    background = Color.White,
    onBackground = Color(0xFF1C1B1F),
    surface = Color.White,
    onSurface = Color(0xFF1C1B1F),
    error = Color(0xFFC62828),
    onError = Color.White,
    errorContainer = Color(0xFFFFDAD6),
    onErrorContainer = Color(0xFF410002)
)

// Dark theme colors
private val DarkColorScheme = darkColorScheme(
    primary = Color(0xFF90CAF9), // Lighter Blue for dark theme
    onPrimary = Color(0xFF002E5B),
    primaryContainer = Color(0xFF004786),
    onPrimaryContainer = Color(0xFFD1E3F9),
    secondary = Color(0xFF80DEEA), // Lighter Teal for dark theme
    onSecondary = Color(0xFF00363D),
    secondaryContainer = Color(0xFF004F58),
    onSecondaryContainer = Color(0xFFB3E5FC),
    tertiary = Color(0xFFCE93D8), // Lighter Purple for dark theme
    onTertiary = Color(0xFF481259),
    tertiaryContainer = Color(0xFF642B73),
    onTertiaryContainer = Color(0xFFF3E5F5),
    background = Color(0xFF1C1B1F),
    onBackground = Color(0xFFE6E1E5),
    surface = Color(0xFF1C1B1F),
    onSurface = Color(0xFFE6E1E5),
    error = Color(0xFFFF8A80),
    onError = Color(0xFF600000),
    errorContainer = Color(0xFF8B0000),
    onErrorContainer = Color(0xFFFFDAD6)
)

@Composable
fun AppTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        content = content
    )
}
