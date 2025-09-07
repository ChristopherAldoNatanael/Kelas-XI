package com.christopheraldoo.weatherapp.presentation.theme

import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color

/**
 * Migration Helper untuk memudahkan transisi dari hardcoded ke resource-based
 * File ini akan dihapus setelah migration selesai
 */
object MigrationHelper {
    
    /**
     * Weather Theme Factory yang menggunakan AppResources
     * Gradually replace WeatherThemes dengan ini
     */
    object WeatherThemeFactory {
        @Composable
        fun getSunnyTheme(): WeatherTheme {
            return WeatherTheme(
                background = AppResources.Colors.sunnyBackground,
                gradient = Brush.verticalGradient(
                    colors = listOf(
                        AppResources.Colors.gradientSunnyStart,
                        AppResources.Colors.gradientSunnyEnd
                    )
                ),
                textPrimary = AppResources.Colors.textPrimary,
                textSecondary = AppResources.Colors.textSecondary,
                cardBackground = AppResources.Colors.cardBackground,
                iconTint = AppResources.Colors.warningOrange
            )
        }
        
        @Composable
        fun getCloudyTheme(): WeatherTheme {
            return WeatherTheme(
                background = AppResources.Colors.cloudyBackground,
                gradient = Brush.verticalGradient(
                    colors = listOf(
                        AppResources.Colors.gradientCloudyStart,
                        AppResources.Colors.gradientCloudyEnd
                    )
                ),
                textPrimary = AppResources.Colors.textPrimary,
                textSecondary = AppResources.Colors.textSecondary,
                cardBackground = AppResources.Colors.cardBackground,
                iconTint = AppResources.Colors.cloudGray
            )
        }
        
        @Composable
        fun getRainyTheme(): WeatherTheme {
            return WeatherTheme(
                background = AppResources.Colors.rainyBackground,
                gradient = Brush.verticalGradient(
                    colors = listOf(
                        AppResources.Colors.gradientRainyStart,
                        AppResources.Colors.gradientRainyEnd
                    )
                ),
                textPrimary = AppResources.Colors.white,
                textSecondary = AppResources.Colors.transparentWhite85,
                cardBackground = AppResources.Colors.cardBackground,
                iconTint = AppResources.Colors.rainBlue
            )
        }
        
        // ... add other weather themes as needed
    }
    
    /**
     * Common UI Components Factory
     */
    object CommonUI {
        @Composable
        fun getTransparentCardBackground() = AppResources.Colors.transparentWhite25
        
        @Composable
        fun getGlassEffect() = AppResources.Colors.transparentWhite35
        
        @Composable
        fun getPrimaryText() = AppResources.Colors.textPrimary
        
        @Composable
        fun getSecondaryText() = AppResources.Colors.textSecondary
        
        @Composable
        fun getHintText() = AppResources.Colors.textHint
        
        // Common spacing values menggunakan resources
        @Composable
        fun getStandardSpacing() = AppResources.Dimensions.spacingLG
        
        @Composable
        fun getCardCornerRadius() = AppResources.Dimensions.cardCornerRadius
        
        @Composable
        fun getIconSize() = AppResources.Dimensions.iconSizeMD
    }
    
    /**
     * Search Component Migration
     */
    object SearchComponents {
        @Composable
        fun getPlaceholderText() = AppResources.Strings.searchHint
        
        @Composable
        fun getSearchingText() = AppResources.Strings.searching
        
        @Composable
        fun getNoResultsText(query: String): String {
            // Untuk sementara pakai hardcode, nanti bisa pakai string formatting
            return "Tidak ditemukan untuk \"$query\""
        }
        
        @Composable
        fun getCardBackground() = AppResources.Colors.transparentWhite25
        
        @Composable
        fun getTextColor() = AppResources.Colors.transparentWhite95
        
        @Composable
        fun getBorderColor() = AppResources.Colors.transparentWhite90
    }
}

/**
 * Extension functions untuk memudahkan migration
 */
@Composable
fun String.asAppString(): String {
    // Helper function untuk gradually migrate hardcoded strings
    // Nanti bisa digunakan untuk lookup ke resources
    return this
}

@Composable
fun Color.Companion.transparentWhite(alpha: Float): Color {
    return when {
        alpha <= 0.15f -> AppResources.Colors.transparentWhite10
        alpha <= 0.30f -> AppResources.Colors.transparentWhite25
        alpha <= 0.40f -> AppResources.Colors.transparentWhite35
        alpha <= 0.60f -> AppResources.Colors.transparentWhite50
        alpha <= 0.75f -> AppResources.Colors.transparentWhite70
        alpha <= 0.87f -> AppResources.Colors.transparentWhite85
        alpha <= 0.92f -> AppResources.Colors.transparentWhite90
        alpha <= 0.96f -> AppResources.Colors.transparentWhite95
        else -> AppResources.Colors.transparentWhite98
    }
}
