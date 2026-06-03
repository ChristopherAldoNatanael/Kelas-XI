package com.christopheraldoo.bukuringkasapp.data.repository

import android.content.Context
import androidx.preference.PreferenceManager

/**
 * Konfigurasi untuk Google Gemini API
 * Token disimpan di SharedPreferences atau menggunakan hardcoded sebagai fallback
 */
object GeminiConfig {
    // API Key Gemini - HARDCODED untuk kemudahan (Fallback)
    // Dapatkan API key gratis di: https://makersuite.google.com/app/apikey
    private const val HARDCODED_API_KEY = "AIzaSyBmz8INcnMyZ6Bc8ngsVqqfES4hnNg_juU"
    
    private const val PREF_GEMINI_API_KEY = "gemini_api_key"

    /**
     * Mendapatkan API key Gemini
     * Prioritas:
     * 1. Hardcoded key (Diprioritaskan untuk memastikan key yang benar digunakan)
     */
    fun getGeminiApiKey(context: Context): String {
        return HARDCODED_API_KEY
    }

    /**
     * Menyimpan API key Gemini ke SharedPreferences
     * (Tidak lagi digunakan karena kita menggunakan hardcoded key)
     */
    fun saveGeminiApiKey(context: Context, apiKey: String) {
        val prefs = PreferenceManager.getDefaultSharedPreferences(context)
        prefs.edit().putString(PREF_GEMINI_API_KEY, apiKey).apply()
    }

    /**
     * Cek apakah API key sudah dikonfigurasi
     */
    fun isApiKeyConfigured(context: Context): Boolean {
        return true
    }
    
    /**
     * Validasi format API key
     */
    fun isTokenValid(key: String): Boolean {
        return key.isNotBlank() && 
               key.startsWith("AIza") && 
               key.length >= 39
    }
    
    /**
     * Mendapatkan informasi error yang lebih deskriptif
     */
    fun getErrorMessageForInvalidToken(): String {
        return """
            API KEY GEMINI BELUM DIKONFIGURASI!
            
            Silakan masukkan API key Gemini Anda di file:
            GeminiConfig.kt -> HARDCODED_API_KEY
            
            Cara mendapatkan API key Gemini (GRATIS):
            1. Buka https://makersuite.google.com/app/apikey
            2. Login dengan akun Google
            3. Klik "Create API Key"
            4. Salin API key (format: AIza...)
            5. Paste di HARDCODED_API_KEY
        """.trimIndent()
    }
}
