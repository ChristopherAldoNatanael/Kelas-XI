package com.christopheraldoo.weatherapp.data.datastore

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "weather_preferences")

@Singleton
class UserPreferencesRepository @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private object PreferencesKeys {
        val FAVORITE_LOCATIONS = stringSetPreferencesKey("favorite_locations")
        val SELECTED_LANGUAGE = stringPreferencesKey("selected_language")
        val THEME_MODE = stringPreferencesKey("theme_mode")
        val TEMPERATURE_UNIT = stringPreferencesKey("temperature_unit")
        val LAST_LOCATION = stringPreferencesKey("last_location")
        val NOTIFICATION_ENABLED = booleanPreferencesKey("notification_enabled")
        val AUTO_LOCATION = booleanPreferencesKey("auto_location")
        val WIND_SPEED_UNIT = stringPreferencesKey("wind_speed_unit")
    }    // Favorite Locations
    val favoriteLocations: Flow<Set<String>> = context.dataStore.data.map { preferences ->
        preferences[PreferencesKeys.FAVORITE_LOCATIONS] ?: emptySet()
    }

    suspend fun addFavoriteLocation(location: String) {
        context.dataStore.edit { preferences ->
            val currentFavorites = preferences[PreferencesKeys.FAVORITE_LOCATIONS] ?: emptySet()
            preferences[PreferencesKeys.FAVORITE_LOCATIONS] = currentFavorites + location
        }
    }

    suspend fun removeFavoriteLocation(location: String) {
        context.dataStore.edit { preferences ->
            val currentFavorites = preferences[PreferencesKeys.FAVORITE_LOCATIONS] ?: emptySet()
            preferences[PreferencesKeys.FAVORITE_LOCATIONS] = currentFavorites - location
        }
    }

    suspend fun clearFavoriteLocations() {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.FAVORITE_LOCATIONS] = emptySet()
        }
    }

    // Language Settings
    suspend fun saveLanguage(language: Language) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.SELECTED_LANGUAGE] = language.code
        }
    }

    fun getLanguage(): Flow<Language> {
        return context.dataStore.data.map { preferences ->
            val languageCode = preferences[PreferencesKeys.SELECTED_LANGUAGE] ?: Language.ENGLISH.code
            Language.values().find { it.code == languageCode } ?: Language.ENGLISH
        }
    }

    // Theme Mode Settings
    suspend fun saveThemeMode(themeMode: ThemeMode) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.THEME_MODE] = themeMode.name
        }
    }

    fun getThemeMode(): Flow<ThemeMode> {
        return context.dataStore.data.map { preferences ->
            val themeModeString = preferences[PreferencesKeys.THEME_MODE] ?: ThemeMode.SYSTEM.name
            try {
                ThemeMode.valueOf(themeModeString)
            } catch (e: IllegalArgumentException) {
                ThemeMode.SYSTEM
            }
        }
    }

    // Temperature Unit Settings
    suspend fun saveTemperatureUnit(unit: String) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.TEMPERATURE_UNIT] = unit
        }
    }

    fun getTemperatureUnit(): Flow<String> {
        return context.dataStore.data.map { preferences ->
            preferences[PreferencesKeys.TEMPERATURE_UNIT] ?: "C"
        }
    }

    // Wind Speed Unit Settings
    suspend fun saveWindSpeedUnit(unit: String) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.WIND_SPEED_UNIT] = unit
        }
    }

    fun getWindSpeedUnit(): Flow<String> {
        return context.dataStore.data.map { preferences ->
            preferences[PreferencesKeys.WIND_SPEED_UNIT] ?: "km/h"
        }
    }

    // Last Location
    val lastLocation: Flow<String> = context.dataStore.data.map { preferences ->
        preferences[PreferencesKeys.LAST_LOCATION] ?: "Jakarta, Indonesia"
    }

    suspend fun setLastLocation(location: String) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.LAST_LOCATION] = location
        }
    }

    // Notifications Settings
    suspend fun saveNotificationsEnabled(enabled: Boolean) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.NOTIFICATION_ENABLED] = enabled
        }
    }

    fun getNotificationsEnabled(): Flow<Boolean> {
        return context.dataStore.data.map { preferences ->
            preferences[PreferencesKeys.NOTIFICATION_ENABLED] ?: true
        }
    }

    // Auto Location
    val autoLocation: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[PreferencesKeys.AUTO_LOCATION] ?: true
    }

    suspend fun setAutoLocation(enabled: Boolean) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.AUTO_LOCATION] = enabled
        }
    }

    // Clear all preferences
    suspend fun clearAllPreferences() {
        context.dataStore.edit { preferences ->
            preferences.clear()
        }
    }
}

enum class Language(val code: String, val displayName: String) {
    ENGLISH("en", "English"),
    INDONESIAN("id", "Bahasa Indonesia")
}

enum class ThemeMode {
    SYSTEM,
    LIGHT,
    DARK
}
