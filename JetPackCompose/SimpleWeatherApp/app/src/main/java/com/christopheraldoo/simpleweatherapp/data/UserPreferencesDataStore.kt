package com.christopheraldoo.simpleweatherapp.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

// Single instance of DataStore
val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "user_preferences")

class UserPreferencesDataStore(private val context: Context) {

    val userPreferencesFlow: Flow<UserPreferences> = context.dataStore.data
        .map { preferences ->
            UserPreferences(
                themeMode = ThemeMode.fromOrdinal(
                    preferences[PreferencesKeys.THEME_MODE] ?: ThemeMode.SYSTEM.ordinal
                ),
                units = Units.fromValue(
                    preferences[PreferencesKeys.UNITS] ?: Units.METRIC.value
                ),
                language = preferences[PreferencesKeys.LANGUAGE] ?: "en",
                enableNotifications = preferences[PreferencesKeys.ENABLE_NOTIFICATIONS] ?: true,
                defaultCity = preferences[PreferencesKeys.DEFAULT_CITY] ?: ""
            )
        }

    suspend fun updateThemeMode(themeMode: ThemeMode) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.THEME_MODE] = themeMode.ordinal
        }
    }

    suspend fun updateUnits(units: Units) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.UNITS] = units.value
        }
    }

    suspend fun updateLanguage(language: String) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.LANGUAGE] = language
        }
    }

    suspend fun updateNotificationsEnabled(enabled: Boolean) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.ENABLE_NOTIFICATIONS] = enabled
        }
    }

    suspend fun updateDefaultCity(city: String) {
        context.dataStore.edit { preferences ->
            preferences[PreferencesKeys.DEFAULT_CITY] = city
        }
    }
}
