package com.christopheraldoo.bukuringkasapp.data.repository

import android.content.Context
import androidx.preference.PreferenceManager

/**
 * Konfigurasi untuk API OpenAI
 * Token disimpan di local.properties untuk keamanan
 * JANGAN PERNAH commit API key ke repository!
 */
object ApiConfig {
    // API Key OpenAI - Ganti dengan API key kamu sendiri
    // Dapatkan di: https://platform.openai.com/api-keys
    private const val HARDCODED_API_KEY = "YOUR_OPENAI_API_KEY_HERE"
    
    // Token API OpenAI - HARDCODED langsung dari kode
    fun getOpenAIApiKey(context: Context): String {
        return HARDCODED_API_KEY
    }

    // Menyimpan API key ke SharedPreferences (untuk kompatibilitas)
    fun saveOpenAIApiKey(context: Context, apiKey: String) {
        val prefs = PreferenceManager.getDefaultSharedPreferences(context)
        prefs.edit().putString("openai_api_key", apiKey).apply()
    }

    // Cek apakah API key sudah dikonfigurasi (selalu true karena hardcoded)
    fun isApiKeyConfigured(context: Context): Boolean {
        return HARDCODED_API_KEY != "sk-DummyTokenInvalidPleaseReplaceWithYourOwnToken" &&
               HARDCODED_API_KEY.startsWith("sk-") &&
               HARDCODED_API_KEY.length > 20
    }
    
    // Base URL untuk OpenAI API
    const val OPENAI_API_BASE_URL = "https://api.openai.com/v1/"
    /**
     * Validasi token API
     */
    fun isTokenValid(context: Context): Boolean {
        val token = getOpenAIApiKey(context)
        return token.isNotBlank() &&
               token.startsWith("sk-") &&
               token.length > 20 &&
               token != "sk-DummyTokenInvalidPleaseReplaceWithYourOwnToken"
    }
    
    /**
     * Mendapatkan informasi error yang lebih deskriptif
     */
    fun getErrorMessageForInvalidToken(context: Context): String {
        val token = getOpenAIApiKey(context)
        val isConfigured = isApiKeyConfigured(context)
        
        return if (!isConfigured) {
            """
                API KEY BELUM DIKONFIGURASI!
                
                Mohon masukkan API key OpenAI Anda di halaman Pengaturan.
                
                Cara mendapatkan API key OpenAI:
                1. Buka https://platform.openai.com/api-keys
                2. Buat akun atau login
                3. Buat API key baru
                4. Salin API key dan masukkan di aplikasi
            """.trimIndent()
        } else {
            """
                Token API OpenAI: ${token.take(10)}...
                Status: ${if (isTokenValid(context)) "VALID" else "INVALID"}
                
                Jika masih error, pastikan:
                1. Token OpenAI benar (sk-...)
                2. Koneksi internet stabil
                3. Token memiliki akses ke model yang dibutuhkan
                4. Saldo/kredit OpenAI masih tersedia
            """.trimIndent()
        }
    }
}
