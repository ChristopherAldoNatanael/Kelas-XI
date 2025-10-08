package com.christopheraldoo.simpleweatherapp.data

import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey

object PreferencesKeys {
    val THEME_MODE = intPreferencesKey("theme_mode")
    val UNITS = stringPreferencesKey("units")
    val LANGUAGE = stringPreferencesKey("language")
    val ENABLE_NOTIFICATIONS = booleanPreferencesKey("enable_notifications")
    val DEFAULT_CITY = stringPreferencesKey("default_city")
}
