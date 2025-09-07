package com.christopheraldoo.weatherapp.presentation.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.weatherapp.data.datastore.Language
import com.christopheraldoo.weatherapp.data.datastore.ThemeMode
import com.christopheraldoo.weatherapp.data.datastore.UserPreferencesRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val userPreferencesRepository: UserPreferencesRepository
) : ViewModel() {
    
    data class SettingsUiState(
        val selectedLanguage: Language = Language.ENGLISH,
        val selectedTheme: ThemeMode = ThemeMode.SYSTEM,
        val notificationsEnabled: Boolean = true,
        val temperatureUnit: String = "C",
        val windSpeedUnit: String = "km/h",
        val isLoading: Boolean = false
    )
    
    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()
    
    init {
        loadSettings()
    }
    
    private fun loadSettings() {
        viewModelScope.launch {
            combine(
                userPreferencesRepository.getLanguage(),
                userPreferencesRepository.getThemeMode(),
                userPreferencesRepository.getNotificationsEnabled(),
                userPreferencesRepository.getTemperatureUnit(),
                userPreferencesRepository.getWindSpeedUnit()
            ) { language, themeMode, notifications, tempUnit, windUnit ->
                SettingsUiState(
                    selectedLanguage = language,
                    selectedTheme = themeMode,
                    notificationsEnabled = notifications,
                    temperatureUnit = tempUnit,
                    windSpeedUnit = windUnit
                )
            }.collect { newState ->
                _uiState.value = newState
            }
        }
    }
    
    fun updateLanguage(language: Language) {
        viewModelScope.launch {
            userPreferencesRepository.saveLanguage(language)
        }
    }
    
    fun updateTheme(themeMode: ThemeMode) {
        viewModelScope.launch {
            userPreferencesRepository.saveThemeMode(themeMode)
        }
    }
    
    fun toggleNotifications(enabled: Boolean) {
        viewModelScope.launch {
            userPreferencesRepository.saveNotificationsEnabled(enabled)
        }
    }
    
    fun updateTemperatureUnit(unit: String) {
        viewModelScope.launch {
            userPreferencesRepository.saveTemperatureUnit(unit)
        }
    }
    
    fun updateWindSpeedUnit(unit: String) {
        viewModelScope.launch {
            userPreferencesRepository.saveWindSpeedUnit(unit)
        }
    }
}
