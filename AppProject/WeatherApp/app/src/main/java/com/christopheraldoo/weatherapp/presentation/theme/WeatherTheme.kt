package com.christopheraldoo.weatherapp.presentation.theme

import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color

data class WeatherTheme(
    val background: Color,
    val gradient: Brush,
    val textPrimary: Color,
    val textSecondary: Color,
    val cardBackground: Color,
    val iconTint: Color
)

object WeatherThemes {    // Cuaca Panas/Terik - Cerah, hangat, energik
    val SUNNY = WeatherTheme(
        background = Color(0xFFFFD54F), // Kuning cerah hangat
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFFFFEB3B), // Kuning terang
                Color(0xFFFF9800)  // Orange hangat
            )
        ),
        textPrimary = Color(0xFF1A1A1A), // Hitam untuk kontras
        textSecondary = Color(0xFF424242), // Abu-abu gelap
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFFFF6F00) // Orange untuk ikon
    )    // Cuaca Mendung - Lembut, tenang, redup
    val CLOUDY = WeatherTheme(
        background = Color(0xFF90A4AE), // Abu-abu lembut
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFFB0BEC5), // Abu-abu terang
                Color(0xFF607D8B)  // Abu-abu gelap
            )
        ),
        textPrimary = Color(0xFF1A1A1A), // Hitam untuk kontras
        textSecondary = Color(0xFF424242), // Abu-abu gelap
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF546E7A) // Abu-abu untuk ikon
    )    // Cuaca Gerimis - Sejuk, ringan, lembap
    val DRIZZLE = WeatherTheme(
        background = Color(0xFF81D4FA), // Biru muda sejuk
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFF4FC3F7), // Biru langit
                Color(0xFF29B6F6)  // Biru cerah
            )
        ),
        textPrimary = Color(0xFF0D47A1), // Biru gelap untuk kontras
        textSecondary = Color(0xFF1565C0), // Biru medium
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF1976D2) // Biru untuk ikon
    )    // Cuaca Hujan Deras - Gelap, dramatis, intens
    val RAIN = WeatherTheme(
        background = Color(0xFF1565C0), // Biru gelap intens
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFF0D47A1), // Biru navy
                Color(0xFF1E88E5)  // Biru cerah
            )
        ),
        textPrimary = Color(0xFFFFFFFF), // Putih untuk kontras maksimal
        textSecondary = Color(0xFFE3F2FD), // Putih kebiruan
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF2196F3) // Biru cerah untuk ikon
    )
      // Cuaca Hujan Petir - Background gelap dengan highlight kuning kontras
    val THUNDERSTORM = WeatherTheme(
        background = Color(0xFF263238), // Abu-abu gelap
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFF212121), // Hitam pekat
                Color(0xFF37474F)  // Abu-abu gelap
            )
        ),
        textPrimary = Color(0xFFFFD600), // Kuning terang untuk kontras maksimal
        textSecondary = Color(0xFFFFF59D), // Kuning muda
        cardBackground = Color(0xFF37474F), // Abu-abu gelap solid tanpa transparan
        iconTint = Color(0xFFFFD600) // Kuning cerah untuk ikon petir
    )    // Cuaca Bersalju - Dingin, bersih, damai
    val SNOW = WeatherTheme(
        background = Color(0xFFE0F7FA), // Putih kebiruan
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFFB2EBF2), // Biru muda
                Color(0xFF80DEEA)  // Teal muda
            )
        ),
        textPrimary = Color(0xFF37474F), // Abu-abu gelap untuk kontras
        textSecondary = Color(0xFF546E7A), // Abu-abu medium
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF26C6DA) // Teal untuk ikon salju
    )    // Cuaca Kabut - Redup, samar, misterius
    val FOG = WeatherTheme(
        background = Color(0xFFECEFF1), // Abu-abu sangat terang
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFFCFD8DC), // Abu-abu muda
                Color(0xFFB0BEC5)  // Abu-abu medium
            )
        ),
        textPrimary = Color(0xFF455A64), // Abu-abu gelap untuk kontras
        textSecondary = Color(0xFF607D8B), // Abu-abu medium
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF78909C) // Abu-abu untuk ikon kabut
    )// Cuaca Cerah Berawan - Ceria, seimbang, ringan
    val PARTLY_CLOUDY = WeatherTheme(
        background = Color(0xFF81C784), // Hijau cerah ceria
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFF66BB6A), // Hijau lembut
                Color(0xFF4CAF50)  // Hijau medium
            )
        ),
        textPrimary = Color(0xFF1B5E20), // Hijau gelap untuk kontras maksimal
        textSecondary = Color(0xFF2E7D32), // Hijau medium gelap
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF388E3C) // Hijau untuk ikon
    )    // Cuaca Angin Kencang - Dingin, cepat, dinamis
    val WINDY = WeatherTheme(
        background = Color(0xFFB0BEC5), // Abu-abu cerah dinamis
        gradient = Brush.verticalGradient(
            colors = listOf(
                Color(0xFF78909C), // Abu-abu medium
                Color(0xFF455A64)  // Abu-abu gelap
            )
        ),
        textPrimary = Color(0xFF1A1A1A), // Hitam untuk kontras maksimal
        textSecondary = Color(0xFF424242), // Abu-abu gelap
        cardBackground = Color(0xFFFFFFFF), // Putih solid (tidak transparan)
        iconTint = Color(0xFF607D8B) // Abu-abu untuk ikon angin
    )
      // Default untuk cuaca tidak dikenal
    val DEFAULT = PARTLY_CLOUDY
}

// Fungsi untuk mendapatkan tema berdasarkan kondisi cuaca
fun getWeatherTheme(weatherCondition: String?): WeatherTheme {
    return when (weatherCondition?.lowercase()) {
        // Sunny/Clear/Hot
        "sunny", "clear", "hot" -> WeatherThemes.SUNNY
        
        // Cloudy
        "cloudy", "overcast" -> WeatherThemes.CLOUDY
        
        // Drizzle/Light Rain
        "drizzle", "light rain", "mist" -> WeatherThemes.DRIZZLE
        
        // Heavy Rain
        "rain", "heavy rain", "moderate rain" -> WeatherThemes.RAIN
        
        // Thunderstorm
        "thunderstorm", "storm", "thunder" -> WeatherThemes.THUNDERSTORM
        
        // Snow
        "snow", "blizzard", "sleet" -> WeatherThemes.SNOW
        
        // Fog/Haze
        "fog", "haze", "smoke" -> WeatherThemes.FOG
        
        // Partly Cloudy
        "partly cloudy", "partly sunny", "scattered clouds" -> WeatherThemes.PARTLY_CLOUDY
        
        // Windy
        "windy", "breezy" -> WeatherThemes.WINDY        else -> WeatherThemes.DEFAULT
    }
}
