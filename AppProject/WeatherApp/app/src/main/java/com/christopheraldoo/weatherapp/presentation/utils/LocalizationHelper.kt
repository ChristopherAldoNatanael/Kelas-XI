package com.christopheraldoo.weatherapp.presentation.utils

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import java.util.Locale

data class LocalizedStrings(
    val appName: String,
    val appSubtitle: String,
    val temperature: String,
    val humidity: String,
    val windSpeed: String,
    val visibility: String,
    val uvIndex: String,
    val pressure: String,
    val sunrise: String,
    val sunset: String,
    val forecast7Day: String,
    val search: String,
    val searchHint: String,
    val favorites: String,
    val currentLocation: String,
    val refresh: String,
    val settings: String,
    val language: String,
    val darkMode: String,
    val notifications: String,
    val about: String,
    val today: String,
    val tomorrow: String,
    val thisWeek: String,
    val feelsLike: String,
    val min: String,
    val max: String,
    val noDataAvailable: String,
    val loadingWeather: String,
    val errorLoadingWeather: String,
    val tryAgain: String,
    val locationPermissionRequired: String,
    val enableLocation: String,
    val worldClock: String,
    // Additional missing properties
    val loading: String,
    val error: String,
    val forecast: String,
    val wind: String,
    val high: String,
    val low: String
)

private val englishStrings = LocalizedStrings(
    appName = "Weather Matters",
    appSubtitle = "Professional Weather Experience",
    temperature = "Temperature",
    humidity = "Humidity",
    windSpeed = "Wind Speed",
    visibility = "Visibility",
    uvIndex = "UV Index",
    pressure = "Pressure",
    sunrise = "Sunrise",
    sunset = "Sunset",
    forecast7Day = "7-Day Forecast",
    search = "Search",
    searchHint = "Search for a city...",
    favorites = "Favorites",
    currentLocation = "Current Location",
    refresh = "Refresh",
    settings = "Settings",
    language = "Language",
    darkMode = "Dark Mode",
    notifications = "Notifications",
    about = "About",
    today = "Today",
    tomorrow = "Tomorrow",
    thisWeek = "This Week",
    feelsLike = "Feels like",
    min = "Min",
    max = "Max",    noDataAvailable = "No data available",
    loadingWeather = "Loading weather...",
    errorLoadingWeather = "Error loading weather",
    tryAgain = "Try Again",
    locationPermissionRequired = "Location permission required",
    enableLocation = "Enable Location",
    worldClock = "World Clock",
    // Additional missing properties
    loading = "Loading...",
    error = "Error",
    forecast = "Forecast",
    wind = "Wind",
    high = "High",
    low = "Low"
)

private val indonesianStrings = LocalizedStrings(
    appName = "Weather Matters",
    appSubtitle = "Pengalaman Cuaca Profesional",
    temperature = "Suhu",
    humidity = "Kelembapan",
    windSpeed = "Kecepatan Angin",
    visibility = "Jarak Pandang",
    uvIndex = "Indeks UV",
    pressure = "Tekanan Udara",
    sunrise = "Matahari Terbit",
    sunset = "Matahari Terbenam",
    forecast7Day = "Prakiraan 7 Hari",
    search = "Cari",
    searchHint = "Cari kota...",
    favorites = "Favorit",
    currentLocation = "Lokasi Saat Ini",
    refresh = "Perbarui",
    settings = "Pengaturan",
    language = "Bahasa",
    darkMode = "Mode Gelap",
    notifications = "Notifikasi",
    about = "Tentang",
    today = "Hari Ini",
    tomorrow = "Besok",
    thisWeek = "Minggu Ini",
    feelsLike = "Terasa seperti",
    min = "Min",
    max = "Maks",
    noDataAvailable = "Data tidak tersedia",
    loadingWeather = "Memuat cuaca...",
    errorLoadingWeather = "Gagal memuat cuaca",    tryAgain = "Coba Lagi",
    locationPermissionRequired = "Izin lokasi diperlukan",
    enableLocation = "Aktifkan Lokasi",
    worldClock = "Jam Dunia",
    // Additional missing properties
    loading = "Memuat...",
    error = "Kesalahan",
    forecast = "Prakiraan",
    wind = "Angin",
    high = "Tinggi",
    low = "Rendah"
)

enum class AppLanguage {
    ENGLISH, INDONESIAN
}

object LocalizationHelper {
    private var currentLanguage: AppLanguage = AppLanguage.ENGLISH
    
    fun setLanguage(language: AppLanguage) {
        currentLanguage = language
    }
    
    fun getCurrentTranslation(): LocalizedStrings {
        return when (currentLanguage) {
            AppLanguage.ENGLISH -> englishStrings
            AppLanguage.INDONESIAN -> indonesianStrings
        }
    }
    
    fun translateWeatherCondition(condition: String): String {
        return getWeatherConditionTranslation(condition, currentLanguage)
    }
}

@Composable
fun getLocalizedStrings(language: AppLanguage = AppLanguage.ENGLISH): LocalizedStrings {
    return remember(language) {
        when (language) {
            AppLanguage.ENGLISH -> englishStrings
            AppLanguage.INDONESIAN -> indonesianStrings
        }
    }
}

fun getSystemLanguage(): AppLanguage {
    val systemLocale = Locale.getDefault().language
    return when (systemLocale) {
        "id", "in" -> AppLanguage.INDONESIAN
        else -> AppLanguage.ENGLISH
    }
}

fun getWeatherConditionTranslation(condition: String, language: AppLanguage): String {
    return when (language) {
        AppLanguage.ENGLISH -> when (condition.lowercase()) {
            "sunny", "clear" -> "Sunny"
            "partly cloudy" -> "Partly Cloudy"
            "cloudy", "overcast" -> "Cloudy"
            "mist", "fog" -> "Foggy"
            "patchy rain possible" -> "Light Rain Possible"
            "light rain" -> "Light Rain"
            "moderate rain" -> "Moderate Rain"
            "heavy rain" -> "Heavy Rain"
            "snow" -> "Snow"
            "thundery outbreaks possible" -> "Thunderstorms Possible"
            else -> condition
        }
        AppLanguage.INDONESIAN -> when (condition.lowercase()) {
            "sunny", "clear" -> "Cerah"
            "partly cloudy" -> "Berawan Sebagian"
            "cloudy", "overcast" -> "Berawan"
            "mist", "fog" -> "Berkabut"
            "patchy rain possible" -> "Kemungkinan Hujan Ringan"
            "light rain" -> "Hujan Ringan"
            "moderate rain" -> "Hujan Sedang"
            "heavy rain" -> "Hujan Lebat"
            "snow" -> "Salju"
            "thundery outbreaks possible" -> "Kemungkinan Hujan Petir"
            else -> condition
        }
    }
}
