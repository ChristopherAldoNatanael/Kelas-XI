package com.christopheraldoo.sosialmediaapp.utils

import android.content.Context
import android.content.SharedPreferences
import androidx.appcompat.app.AppCompatDelegate
import java.util.Locale

/**
 * Utility class untuk mengelola preferences aplikasi
 * Mengurus tema, bahasa, dan pengaturan lainnya
 */
class PreferenceManager(context: Context) {
    
    companion object {
        private const val PREF_NAME = "SocialMediaPrefs"
        private const val KEY_THEME_MODE = "theme_mode"
        private const val KEY_LANGUAGE = "language"
        private const val KEY_NOTIFICATIONS_ENABLED = "notifications_enabled"
        private const val KEY_PUSH_NOTIFICATIONS = "push_notifications"
        
        // Theme modes
        const val THEME_LIGHT = "light"
        const val THEME_DARK = "dark"
        const val THEME_SYSTEM = "system"
        
        // Languages
        const val LANGUAGE_ENGLISH = "en"
        const val LANGUAGE_INDONESIAN = "id"
    }
    
    private val sharedPreferences: SharedPreferences = 
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    
    /**
     * Set theme mode
     */
    fun setThemeMode(mode: String) {
        sharedPreferences.edit().putString(KEY_THEME_MODE, mode).apply()
        applyTheme(mode)
    }
    
    /**
     * Get current theme mode
     */
    fun getThemeMode(): String {
        return sharedPreferences.getString(KEY_THEME_MODE, THEME_SYSTEM) ?: THEME_SYSTEM
    }
    
    /**
     * Apply theme to app
     */
    fun applyTheme(mode: String) {
        when (mode) {
            THEME_LIGHT -> AppCompatDelegate.setDefaultNightMode(AppCompatDelegate.MODE_NIGHT_NO)
            THEME_DARK -> AppCompatDelegate.setDefaultNightMode(AppCompatDelegate.MODE_NIGHT_YES)
            THEME_SYSTEM -> AppCompatDelegate.setDefaultNightMode(AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM)
        }
    }
    
    /**
     * Set language
     */
    fun setLanguage(languageCode: String) {
        sharedPreferences.edit().putString(KEY_LANGUAGE, languageCode).apply()
    }
    
    /**
     * Get current language
     */
    fun getLanguage(): String {
        return sharedPreferences.getString(KEY_LANGUAGE, LANGUAGE_ENGLISH) ?: LANGUAGE_ENGLISH
    }
      /**
     * Apply language to context
     */
    fun applyLanguage(context: Context): Context {
        val language = getLanguage()
        return updateBaseContextLocale(context, language)
    }
    
    /**
     * Update base context locale untuk language switching
     */
    fun updateBaseContextLocale(context: Context, language: String): Context {
        val locale = Locale(language)
        Locale.setDefault(locale)
        
        val config = android.content.res.Configuration(context.resources.configuration)
        config.setLocale(locale)
        config.setLayoutDirection(locale)
        
        return context.createConfigurationContext(config)
    }
    
    /**
     * Notifications settings
     */
    fun setNotificationsEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_NOTIFICATIONS_ENABLED, enabled).apply()
    }
    
    fun isNotificationsEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_NOTIFICATIONS_ENABLED, true)
    }
    
    fun setPushNotificationsEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_PUSH_NOTIFICATIONS, enabled).apply()
    }
    
    fun isPushNotificationsEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_PUSH_NOTIFICATIONS, true)
    }
}
