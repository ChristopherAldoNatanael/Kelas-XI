package com.christopheraldoo.sosialmediaapp

import android.app.Application
import android.content.Context
import android.content.res.Configuration
import com.christopheraldoo.sosialmediaapp.utils.PreferenceManager
import java.util.Locale

/**
 * Application class untuk mengelola konfigurasi global aplikasi
 * Termasuk language switching dan theme management
 */
class SocialMediaApplication : Application() {
    
    private lateinit var preferenceManager: PreferenceManager
    
    override fun onCreate() {
        super.onCreate()
        preferenceManager = PreferenceManager(this)
        
        // Apply saved theme
        preferenceManager.applyTheme(preferenceManager.getThemeMode())
    }
    
    override fun attachBaseContext(base: Context) {
        val preferenceManager = PreferenceManager(base)
        val contextWithLanguage = updateBaseContextLocale(base, preferenceManager.getLanguage())
        super.attachBaseContext(contextWithLanguage)
    }
    
    override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        val language = preferenceManager.getLanguage()
        updateBaseContextLocale(this, language)
    }
    
    private fun updateBaseContextLocale(context: Context, language: String): Context {
        val locale = Locale(language)
        Locale.setDefault(locale)
        
        val config = Configuration(context.resources.configuration)
        config.setLocale(locale)
        
        return context.createConfigurationContext(config)
    }
}
