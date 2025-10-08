package com.christopheraldoo.simpleweatherapp.data

import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey

enum class ThemeMode {
    SYSTEM, LIGHT, DARK, AUTO_TIME;

    companion object {
        fun fromOrdinal(ordinal: Int) = values().firstOrNull { it.ordinal == ordinal } ?: SYSTEM
    }
}

enum class Units(val value: String) {
    METRIC("metric"), 
    IMPERIAL("imperial");

    companion object {
        fun fromValue(value: String) = values().firstOrNull { it.value == value } ?: METRIC
    }
}

data class UserPreferences(
    val themeMode: ThemeMode = ThemeMode.SYSTEM,
    val units: Units = Units.METRIC,
    val language: String = "en",
    val enableNotifications: Boolean = true,
    val defaultCity: String = ""
)
