package com.christopheraldoo.weatherapp.utils

import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import com.christopheraldoo.weatherapp.presentation.theme.*

object WeatherUtils {
      fun getWeatherGradient(conditionCode: Int): Brush {
        return when (conditionCode) {
            // Sunny conditions
            1000 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFF87CEEB), // Sky Blue
                    Color(0xFFFFD700), // Gold
                    Color(0xFFFFA500)  // Orange
                )
            )
            // Partly cloudy
            1003 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFF87CEEB), // Sky Blue
                    Color(0xFFF0F8FF), // Alice Blue
                    Color(0xFFB0C4DE)  // Light Steel Blue
                )
            )
            // Cloudy and overcast
            1006, 1009 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFF708090), // Slate Gray
                    Color(0xFF90A4AE), // Blue Gray 400
                    Color(0xFF607D8B)  // Blue Gray 500
                )
            )
            // Mist, fog
            1030, 1135, 1147 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFFF5F5F5), // White Smoke
                    Color(0xFFDCDCDC), // Gainsboro
                    Color(0xFFC0C0C0)  // Silver
                )
            )
            // Light rain, drizzle
            1063, 1150, 1153, 1168, 1171, 1180, 1183, 1186, 1240 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFF4682B4), // Steel Blue
                    Color(0xFF5F9EA0), // Cadet Blue
                    Color(0xFF708090)  // Slate Gray
                )
            )
            // Heavy rain, thunderstorm
            1189, 1192, 1195, 1243, 1246, 1087, 1273, 1276, 1279, 1282 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFF2F4F4F), // Dark Slate Gray
                    Color(0xFF483D8B), // Dark Slate Blue
                    Color(0xFF191970)  // Midnight Blue
                )
            )            // Snow conditions (light to moderate)
            1066, 1069, 1072, 1114, 1204, 1207, 1210, 1213, 1216, 1219, 1222, 1237, 1249, 1252, 1258, 1261, 1264 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFFF0F8FF), // Alice Blue
                    Color(0xFFE6E6FA), // Lavender
                    Color(0xFFD3D3D3)  // Light Gray
                )
            )
            // Blizzard, heavy snow
            1117, 1225, 1255 -> Brush.verticalGradient(
                colors = listOf(
                    Color(0xFFB0C4DE), // Light Steel Blue
                    Color(0xFF87CEEB), // Sky Blue
                    Color(0xFFFFFFFF)  // White
                )
            )
            // Default cloudy
            else -> Brush.verticalGradient(
                colors = listOf(CloudyGradientStart, CloudyGradientEnd)
            )
        }
    }
      fun getWeatherIcon(conditionCode: Int): String {
        return when (conditionCode) {
            // Sunny/Clear
            1000 -> "☀️"
            // Partly Cloudy
            1003 -> "🌤️"
            // Cloudy
            1006 -> "⛅"
            // Overcast
            1009 -> "☁️"
            // Mist
            1030 -> "🌫️"
            // Patchy rain possible
            1063 -> "🌦️"
            // Patchy snow possible  
            1066 -> "🌨️"
            // Patchy sleet possible
            1069 -> "🌧️"
            // Patchy freezing drizzle
            1072 -> "❄️"
            // Thundery outbreaks
            1087 -> "⛈️"
            // Blowing snow
            1114 -> "🌨️"
            // Blizzard
            1117 -> "❄️"
            // Fog
            1135 -> "🌫️"
            // Freezing fog
            1147 -> "🌫️"
            // Patchy light drizzle
            1150 -> "🌦️"
            // Light drizzle
            1153 -> "🌦️"
            // Freezing drizzle
            1168 -> "🌨️"
            // Heavy freezing drizzle
            1171 -> "🌨️"
            // Patchy light rain
            1180 -> "🌦️"
            // Light rain
            1183 -> "🌦️"
            // Moderate rain at times
            1186 -> "🌧️"
            // Moderate rain
            1189 -> "🌧️"
            // Heavy rain at times
            1192 -> "🌧️"
            // Heavy rain
            1195 -> "🌧️"
            // Light freezing rain
            1198 -> "🌨️"
            // Moderate/Heavy freezing rain
            1201 -> "🌨️"
            // Light sleet
            1204 -> "🌨️"
            // Moderate/Heavy sleet
            1207 -> "🌨️"
            // Patchy light snow
            1210 -> "🌨️"
            // Light snow
            1213 -> "❄️"
            // Patchy moderate snow
            1216 -> "🌨️"
            // Moderate snow
            1219 -> "❄️"
            // Patchy heavy snow
            1222 -> "🌨️"
            // Heavy snow
            1225 -> "❄️"
            // Ice pellets
            1237 -> "🌨️"
            // Light rain shower
            1240 -> "🌦️"            // Moderate/Heavy rain shower  
            1243, 1246 -> "🌧️"
            // Torrential rain shower
            1249 -> "🌨️"  // Light sleet showers
            // Moderate/Heavy sleet showers
            1252 -> "🌨️"
            // Light snow showers
            1255 -> "🌨️"
            // Moderate/Heavy snow showers
            1258 -> "❄️"
            // Light/Moderate showers of ice pellets
            1261, 1264 -> "🌨️"
            // Patchy light rain with thunder
            1273 -> "🌩️"
            // Moderate/Heavy rain with thunder
            1276 -> "⛈️"
            // Patchy light snow with thunder
            1279 -> "⛈️"
            // Moderate/Heavy snow with thunder
            1282 -> "⛈️"
            // Default
            else -> "🌤️"
        }
    }
    
    fun getWeatherDescription(conditionText: String): String {
        return when {
            conditionText.contains("sunny", ignoreCase = true) -> "Sunny"
            conditionText.contains("clear", ignoreCase = true) -> "Clear"
            conditionText.contains("cloudy", ignoreCase = true) -> "Cloudy"
            conditionText.contains("overcast", ignoreCase = true) -> "Overcast"
            conditionText.contains("mist", ignoreCase = true) -> "Mist"
            conditionText.contains("fog", ignoreCase = true) -> "Fog"
            conditionText.contains("rain", ignoreCase = true) -> "Rainy"
            conditionText.contains("snow", ignoreCase = true) -> "Snowy"
            conditionText.contains("thunder", ignoreCase = true) -> "Stormy"
            else -> conditionText
        }
    }
    
    fun getUVIndexDescription(uvIndex: Double): String {
        return when {
            uvIndex <= 2 -> "Low"
            uvIndex <= 5 -> "Moderate"
            uvIndex <= 7 -> "High"
            uvIndex <= 10 -> "Very High"
            else -> "Extreme"
        }
    }
    
    fun getUVIndexColor(uvIndex: Double): Color {
        return when {
            uvIndex <= 2 -> AccentGreen
            uvIndex <= 5 -> AccentOrange
            uvIndex <= 7 -> AccentRed
            uvIndex <= 10 -> AccentPurple
            else -> Color(0xFF8E24AA)
        }
    }
    
    fun getWindDirection(degree: Int): String {
        return when {
            degree >= 337.5 || degree < 22.5 -> "N"
            degree < 67.5 -> "NE"
            degree < 112.5 -> "E"
            degree < 157.5 -> "SE"
            degree < 202.5 -> "S"
            degree < 247.5 -> "SW"
            degree < 292.5 -> "W"
            degree < 337.5 -> "NW"
            else -> "N"
        }
    }
    
    fun formatTime(time: String): String {
        return try {
            val parts = time.split(" ")
            if (parts.size == 2) {
                parts[1]
            } else {
                time
            }
        } catch (e: Exception) {
            time
        }
    }
}
