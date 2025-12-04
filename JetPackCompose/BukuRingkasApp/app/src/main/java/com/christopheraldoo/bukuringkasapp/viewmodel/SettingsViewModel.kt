package com.christopheraldoo.bukuringkasapp.viewmodel

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.data.repository.ApiConfig
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * ViewModel untuk Settings Screen
 * Mengelola pengaturan aplikasi, termasuk API key
 */
class SettingsViewModel : ViewModel() {

    private val _apiKey = MutableStateFlow("")
    val apiKey: StateFlow<String> = _apiKey
    
    private val _isTokenValid = MutableStateFlow<Boolean?>(null)
    val isTokenValid: StateFlow<Boolean?> = _isTokenValid
    
    private val _isSaveSuccess = MutableStateFlow<Boolean?>(null)
    val isSaveSuccess: StateFlow<Boolean?> = _isSaveSuccess
    
    private val _showPassword = MutableStateFlow(false)
    val showPassword: StateFlow<Boolean> = _showPassword

    /**
     * Update API key saat user mengetik
     */
    fun updateApiKey(key: String) {
        _apiKey.value = key
        _isTokenValid.value = isApiKeyValid(key)
    }

    /**
     * Toggle tampilkan/sembunyikan password
     */
    fun toggleShowPassword() {
        _showPassword.value = !_showPassword.value
    }

    /**
     * Load API key dari SharedPreferences
     */
    fun loadApiKey(context: Context) {
        viewModelScope.launch {
            val key = ApiConfig.getOpenAIApiKey(context)
            // Jika key default, tampilkan string kosong
            if (key == "sk-DummyTokenInvalidPleaseReplaceWithYourOwnToken") {
                _apiKey.value = ""
                _isTokenValid.value = false
            } else {
                _apiKey.value = key
                _isTokenValid.value = isApiKeyValid(key)
            }
        }
    }

    /**
     * Simpan API key ke SharedPreferences
     */
    fun saveApiKey(context: Context) {
        viewModelScope.launch {
            val key = _apiKey.value.trim()
            val isValid = isApiKeyValid(key)
            
            if (isValid) {
                ApiConfig.saveOpenAIApiKey(context, key)
                _isTokenValid.value = true
                _isSaveSuccess.value = true
            } else {
                _isTokenValid.value = false
                _isSaveSuccess.value = false
            }
        }
    }

    /**
     * Validasi format API key
     */
    private fun isApiKeyValid(key: String): Boolean {
        return key.isNotBlank() && key.startsWith("sk-") && key.length > 20
    }

    /**
     * Reset status simpan
     */
    fun resetSaveStatus() {
        _isSaveSuccess.value = null
    }
}
