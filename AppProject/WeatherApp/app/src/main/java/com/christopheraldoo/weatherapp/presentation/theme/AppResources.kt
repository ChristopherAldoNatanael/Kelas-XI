package com.christopheraldoo.weatherapp.presentation.theme

import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.colorResource
import androidx.compose.ui.res.dimensionResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.Dp
import com.christopheraldoo.weatherapp.R

/**
 * Centralized Resource Management untuk Weather App
 * Memudahkan maintenance dan konsistensi dalam penggunaan resources
 */
object AppResources {
    
    /**
     * App Colors - Menggunakan XML resources untuk consistency
     */
    object Colors {
        val black @Composable get() = colorResource(R.color.black)
        val white @Composable get() = colorResource(R.color.white)
        
        // Weather Colors
        val sunnyYellow @Composable get() = colorResource(R.color.sunny_yellow)
        val cloudGray @Composable get() = colorResource(R.color.cloud_gray)
        val cloudShadow @Composable get() = colorResource(R.color.cloud_shadow)
        val rainBlue @Composable get() = colorResource(R.color.rain_blue)
        val snowWhite @Composable get() = colorResource(R.color.snow_white)
        val stormDark @Composable get() = colorResource(R.color.storm_dark)
        
        // Gradient Colors
        val gradientSunnyStart @Composable get() = colorResource(R.color.gradient_sunny_start)
        val gradientSunnyEnd @Composable get() = colorResource(R.color.gradient_sunny_end)
        val gradientCloudyStart @Composable get() = colorResource(R.color.gradient_cloudy_start)
        val gradientCloudyEnd @Composable get() = colorResource(R.color.gradient_cloudy_end)
        val gradientRainyStart @Composable get() = colorResource(R.color.gradient_rainy_start)
        val gradientRainyEnd @Composable get() = colorResource(R.color.gradient_rainy_end)
        val gradientSnowyStart @Composable get() = colorResource(R.color.gradient_snowy_start)
        val gradientSnowyEnd @Composable get() = colorResource(R.color.gradient_snowy_end)
        
        // Transparent Colors
        val transparentWhite10 @Composable get() = colorResource(R.color.transparent_white_10)
        val transparentWhite25 @Composable get() = colorResource(R.color.transparent_white_25)
        val transparentWhite35 @Composable get() = colorResource(R.color.transparent_white_35)
        val transparentWhite50 @Composable get() = colorResource(R.color.transparent_white_50)
        val transparentWhite70 @Composable get() = colorResource(R.color.transparent_white_70)
        val transparentWhite85 @Composable get() = colorResource(R.color.transparent_white_85)
        val transparentWhite90 @Composable get() = colorResource(R.color.transparent_white_90)
        val transparentWhite95 @Composable get() = colorResource(R.color.transparent_white_95)
        val transparentWhite98 @Composable get() = colorResource(R.color.transparent_white_98)
        
        // Text Colors
        val textPrimary @Composable get() = colorResource(R.color.text_primary)
        val textSecondary @Composable get() = colorResource(R.color.text_secondary)
        val textHint @Composable get() = colorResource(R.color.text_hint)
        
        // Background Colors
        val cardBackground @Composable get() = colorResource(R.color.card_background)
        val cardBackgroundDark @Composable get() = colorResource(R.color.card_background_dark)
        
        // Status Colors
        val successGreen @Composable get() = colorResource(R.color.success_green)
        val warningOrange @Composable get() = colorResource(R.color.warning_orange)
        val errorRed @Composable get() = colorResource(R.color.error_red)
        val infoBlue @Composable get() = colorResource(R.color.info_blue)
        
        // Weather Theme Background Colors
        val sunnyBackground @Composable get() = colorResource(R.color.sunny_background)
        val cloudyBackground @Composable get() = colorResource(R.color.cloudy_background)
        val rainyBackground @Composable get() = colorResource(R.color.rainy_background)
        val snowyBackground @Composable get() = colorResource(R.color.snowy_background)
        val stormyBackground @Composable get() = colorResource(R.color.stormy_background)
        val foggyBackground @Composable get() = colorResource(R.color.foggy_background)
        val partlyCloudyBackground @Composable get() = colorResource(R.color.partly_cloudy_background)
        val windyBackground @Composable get() = colorResource(R.color.windy_background)
    }
    
    /**
     * App Strings - Menggunakan XML resources untuk localization support
     */
    object Strings {
        val appName @Composable get() = stringResource(R.string.app_name)
        
        // Weather Conditions
        val conditionSunny @Composable get() = stringResource(R.string.condition_sunny)
        val conditionCloudy @Composable get() = stringResource(R.string.condition_cloudy)
        val conditionPartlyCloudy @Composable get() = stringResource(R.string.condition_partly_cloudy)
        val conditionOvercast @Composable get() = stringResource(R.string.condition_overcast)
        val conditionMist @Composable get() = stringResource(R.string.condition_mist)
        val conditionFog @Composable get() = stringResource(R.string.condition_fog)
        val conditionPatchyRain @Composable get() = stringResource(R.string.condition_patchy_rain)
        val conditionLightRain @Composable get() = stringResource(R.string.condition_light_rain)
        val conditionModerateRain @Composable get() = stringResource(R.string.condition_moderate_rain)
        val conditionHeavyRain @Composable get() = stringResource(R.string.condition_heavy_rain)
        val conditionLightSnow @Composable get() = stringResource(R.string.condition_light_snow)
        val conditionModerateSnow @Composable get() = stringResource(R.string.condition_moderate_snow)
        val conditionHeavySnow @Composable get() = stringResource(R.string.condition_heavy_snow)
        val conditionThunderstorm @Composable get() = stringResource(R.string.condition_thunderstorm)
        
        // Weather Details
        val feelsLike @Composable get() = stringResource(R.string.feels_like)
        val humidity @Composable get() = stringResource(R.string.humidity)
        val windSpeed @Composable get() = stringResource(R.string.wind_speed)
        val pressure @Composable get() = stringResource(R.string.pressure)
        val visibility @Composable get() = stringResource(R.string.visibility)
        val uvIndex @Composable get() = stringResource(R.string.uv_index)
        
        // Navigation
        val favorites @Composable get() = stringResource(R.string.favorites)
        val worldClock @Composable get() = stringResource(R.string.world_clock)
        val settings @Composable get() = stringResource(R.string.settings)
        val about @Composable get() = stringResource(R.string.about)
        
        // Actions
        val search @Composable get() = stringResource(R.string.search)
        val refresh @Composable get() = stringResource(R.string.refresh)
        val addToFavorites @Composable get() = stringResource(R.string.add_to_favorites)
        val removeFromFavorites @Composable get() = stringResource(R.string.remove_from_favorites)
        
        // Messages        val loadingWeather @Composable get() = stringResource(R.string.loading_weather)
        val errorLoadingWeather @Composable get() = stringResource(R.string.error_loading_weather)
        val noInternetConnection @Composable get() = stringResource(R.string.no_internet_connection)
        val searchHint @Composable get() = stringResource(R.string.search_hint)
        val noFavoritesYet @Composable get() = stringResource(R.string.no_favorites_yet)
        
        // Additional strings for search functionality
        val clear @Composable get() = stringResource(R.string.clear)
        val close @Composable get() = stringResource(R.string.close)
        val select @Composable get() = stringResource(R.string.select)
        val select_location @Composable get() = stringResource(R.string.select_location)
        val error @Composable get() = stringResource(R.string.error)
        val no_results @Composable get() = stringResource(R.string.no_results)
        val error_occurred @Composable get() = stringResource(R.string.error_occurred)
        val searching @Composable get() = stringResource(R.string.searching)
        val popular_cities @Composable get() = stringResource(R.string.popular_cities)        val try_different_keyword @Composable get() = stringResource(R.string.try_different_keyword)
        
        @Composable
        fun no_results_for(query: String): String = stringResource(R.string.no_results_found, query)
    }
    
    /**
     * App Dimensions - Menggunakan XML resources untuk consistency
     */
    object Dimensions {
        // Spacing
        val spacingXS @Composable get() = dimensionResource(R.dimen.spacing_xs)
        val spacingSM @Composable get() = dimensionResource(R.dimen.spacing_sm)
        val spacingMD @Composable get() = dimensionResource(R.dimen.spacing_md)
        val spacingLG @Composable get() = dimensionResource(R.dimen.spacing_lg)
        val spacingXL @Composable get() = dimensionResource(R.dimen.spacing_xl)
        val spacingXXL @Composable get() = dimensionResource(R.dimen.spacing_xxl)
        val spacingXXXL @Composable get() = dimensionResource(R.dimen.spacing_xxxl)
        
        // Aliases for common usage
        val spacingSmall @Composable get() = spacingSM
        val spacingMedium @Composable get() = spacingMD
        val spacingLarge @Composable get() = spacingLG
        
        // Card Dimensions
        val cardCornerRadius @Composable get() = dimensionResource(R.dimen.card_corner_radius)
        val cardCornerRadiusLG @Composable get() = dimensionResource(R.dimen.card_corner_radius_lg)
        val cardCornerRadiusXL @Composable get() = dimensionResource(R.dimen.card_corner_radius_xl)
        val cardCornerRadiusSmall @Composable get() = cardCornerRadius // Alias
        val cardElevation @Composable get() = dimensionResource(R.dimen.card_elevation)
        val cardElevationLG @Composable get() = dimensionResource(R.dimen.card_elevation_lg)
        
        // Icon Sizes
        val iconSizeXS @Composable get() = dimensionResource(R.dimen.icon_size_xs)
        val iconSizeSM @Composable get() = dimensionResource(R.dimen.icon_size_sm)
        val iconSizeMD @Composable get() = dimensionResource(R.dimen.icon_size_md)
        val iconSizeLG @Composable get() = dimensionResource(R.dimen.icon_size_lg)
        val iconSizeXL @Composable get() = dimensionResource(R.dimen.icon_size_xl)
        val iconSizeXXL @Composable get() = dimensionResource(R.dimen.icon_size_xxl)
        val iconSizeXXXL @Composable get() = dimensionResource(R.dimen.icon_size_xxxl)
        
        // Aliases for common usage
        val iconSizeSmall @Composable get() = iconSizeSM
        val iconSizeMedium @Composable get() = iconSizeMD
        val iconSizeLarge @Composable get() = iconSizeLG
        val iconSizeXLarge @Composable get() = iconSizeXL
        val cardCornerRadiusLG @Composable get() = dimensionResource(R.dimen.card_corner_radius_lg)
        val cardCornerRadiusXL @Composable get() = dimensionResource(R.dimen.card_corner_radius_xl)
        val cardElevation @Composable get() = dimensionResource(R.dimen.card_elevation)
        val cardElevationLG @Composable get() = dimensionResource(R.dimen.card_elevation_lg)
        
        // Icon Sizes
        val iconSizeXS @Composable get() = dimensionResource(R.dimen.icon_size_xs)
        val iconSizeSM @Composable get() = dimensionResource(R.dimen.icon_size_sm)
        val iconSizeMD @Composable get() = dimensionResource(R.dimen.icon_size_md)
        val iconSizeLG @Composable get() = dimensionResource(R.dimen.icon_size_lg)
        val iconSizeXL @Composable get() = dimensionResource(R.dimen.icon_size_xl)
        val iconSizeXXL @Composable get() = dimensionResource(R.dimen.icon_size_xxl)
        val iconSizeXXXL @Composable get() = dimensionResource(R.dimen.icon_size_xxxl)
        
        // Weather Specific
        val weatherCardHeight @Composable get() = dimensionResource(R.dimen.weather_card_height)
        val weatherIconSize @Composable get() = dimensionResource(R.dimen.weather_icon_size)
        val weatherIconSizeSM @Composable get() = dimensionResource(R.dimen.weather_icon_size_sm)
        val weatherIconSizeLG @Composable get() = dimensionResource(R.dimen.weather_icon_size_lg)
    }
}

/**
 * Helper functions untuk backward compatibility dengan hardcoded values
 * Nanti bisa dihapus setelah semua migration selesai
 */
object LegacyColors {
    // Temporary hardcoded colors yang sering dipakai - nanti akan di-migrate
    val transparentWhite25 = Color.White.copy(alpha = 0.25f)
    val transparentWhite35 = Color.White.copy(alpha = 0.35f)
    val transparentWhite85 = Color.White.copy(alpha = 0.85f)
    val transparentWhite90 = Color.White.copy(alpha = 0.9f)
    val transparentWhite95 = Color.White.copy(alpha = 0.95f)
    val transparentWhite98 = Color.White.copy(alpha = 0.98f)
    
    // Warna yang sering dipakai - akan dipindah ke XML nanti
    val darkText = Color(0xFF1A1A1A)
    val mediumGray = Color(0xFF424242)
    val lightGray = Color(0xFF90A4AE)
    val semiTransparentBlack = Color(0x80000000)
}
