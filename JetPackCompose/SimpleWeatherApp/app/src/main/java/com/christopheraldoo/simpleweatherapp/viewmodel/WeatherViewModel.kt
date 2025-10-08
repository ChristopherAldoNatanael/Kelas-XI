package com.christopheraldoo.simpleweatherapp.viewmodel

import android.app.Application
import android.location.Location
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.simpleweatherapp.data.*
import com.christopheraldoo.simpleweatherapp.location.LocationResult
import com.christopheraldoo.simpleweatherapp.location.LocationService
import com.christopheraldoo.simpleweatherapp.repository.WeatherRepository
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

sealed class WeatherState {
    object Loading : WeatherState()
    data class Success(val weather: WeatherResponse) : WeatherState()
    data class Error(val message: String) : WeatherState()
    object LocationPermissionRequired : WeatherState()
}

sealed class ForecastState {
    object Loading : ForecastState()
    data class Success(val forecast: ForecastResponse) : ForecastState()
    data class Error(val message: String) : ForecastState()
    object Empty : ForecastState()
}

sealed class AlertsState {
    object Loading : AlertsState()
    data class Success(val alertsResponse: WeatherAlertsResponse) : AlertsState()
    data class Error(val message: String) : AlertsState()
    object Empty : AlertsState()
}

sealed class SettingsState {
    object Loading : SettingsState()
    data class Success(val userPreferences: UserPreferences) : SettingsState()
    object Error : SettingsState()
}

class WeatherViewModel(application: Application) : AndroidViewModel(application) {

    private val weatherRepository = WeatherRepository()
    private val locationService = LocationService(application)
    private val userPreferencesDataStore = UserPreferencesDataStore(application)

    private val _weatherState = MutableStateFlow<WeatherState>(WeatherState.LocationPermissionRequired)
    val weatherState: StateFlow<WeatherState> = _weatherState.asStateFlow()

    private val _forecastState = MutableStateFlow<ForecastState>(ForecastState.Empty)
    val forecastState: StateFlow<ForecastState> = _forecastState.asStateFlow()
    
    private val _alertsState = MutableStateFlow<AlertsState>(AlertsState.Empty)
    val alertsState: StateFlow<AlertsState> = _alertsState.asStateFlow()
    
    private val _settingsState = MutableStateFlow<SettingsState>(SettingsState.Loading)
    val settingsState: StateFlow<SettingsState> = _settingsState.asStateFlow()
    
    // Last known location for re-fetching data
    private var lastLocation: Location? = null
    private var lastCity: String? = null

    // Settings values
    val userPreferencesFlow = userPreferencesDataStore.userPreferencesFlow
    
    init {
        // Initial state - try to load weather automatically
        _weatherState.value = WeatherState.Loading

        // Load user preferences
        loadUserPreferences()
    }
    
    private fun loadUserPreferences() {
        viewModelScope.launch {
            userPreferencesFlow.collect { preferences ->
                _settingsState.value = SettingsState.Success(preferences)

                // Try to get location-based weather first
                if (_weatherState.value is WeatherState.Loading) {
                    getLocationAndWeather()
                } else if (preferences.defaultCity.isNotEmpty() && _weatherState.value !is WeatherState.Success) {
                    searchWeatherByCity(preferences.defaultCity)
                }
            }
        }
    }

    fun getLocationAndWeather() {
        viewModelScope.launch {
            _weatherState.value = WeatherState.Loading

            // Get current location
            locationService.getCurrentLocation().collect { locationResult ->
                when (locationResult) {
                    is com.christopheraldoo.simpleweatherapp.location.LocationResult.Success -> {
                        lastLocation = locationResult.location
                        loadWeatherByLocation(locationResult.location)
                        loadForecastByLocation(locationResult.location)
                        loadWeatherAlerts(locationResult.location)
                    }
                    is com.christopheraldoo.simpleweatherapp.location.LocationResult.Error -> {
                        if (locationResult.message.contains("permissions not granted")) {
                            _weatherState.value = WeatherState.LocationPermissionRequired
                        } else {
                            _weatherState.value = WeatherState.Error(locationResult.message)
                        }
                    }
                    com.christopheraldoo.simpleweatherapp.location.LocationResult.Loading -> {
                        // Still loading location
                    }
                }
            }
        }
    }
    
    fun searchWeatherByCity(city: String) {
        viewModelScope.launch {
            _weatherState.value = WeatherState.Loading
            _forecastState.value = ForecastState.Loading
            
            lastCity = city
            
            try {
                // Get current user preferences
                val currentPreferences = (_settingsState.value as? SettingsState.Success)?.userPreferences
                val units = currentPreferences?.units?.value ?: Units.METRIC.value
                val language = currentPreferences?.language ?: "en"
                
                // Get current weather
                val weatherResult = weatherRepository.getWeatherByCityName(city, units, language)
                weatherResult.fold(
                    onSuccess = { weatherResponse ->
                        _weatherState.value = WeatherState.Success(weatherResponse)
                        
                        // Get forecast for the same city
                        loadForecastByCityName(city)
                    },
                    onFailure = { exception ->
                        _weatherState.value = WeatherState.Error(
                            exception.message ?: "Failed to find weather for '$city'"
                        )
                        _forecastState.value = ForecastState.Error(
                            "Could not load forecast for '$city'"
                        )
                    }
                )
            } catch (e: Exception) {
                _weatherState.value = WeatherState.Error(
                    e.message ?: "An unexpected error occurred while searching for '$city'"
                )
                _forecastState.value = ForecastState.Error(
                    "Failed to load forecast"
                )
            }
        }
    }

    private fun loadWeatherByLocation(location: Location) {
        viewModelScope.launch {
            try {
                // Get current user preferences
                val currentPreferences = (_settingsState.value as? SettingsState.Success)?.userPreferences
                val units = currentPreferences?.units?.value ?: Units.METRIC.value
                val language = currentPreferences?.language ?: "en"
                
                val result = weatherRepository.getWeatherByLocation(location, units, language)
                result.fold(
                    onSuccess = { weatherResponse ->
                        _weatherState.value = WeatherState.Success(weatherResponse)
                    },
                    onFailure = { exception ->
                        _weatherState.value = WeatherState.Error(
                            exception.message ?: "Failed to load weather data"
                        )
                    }
                )
            } catch (e: Exception) {
                _weatherState.value = WeatherState.Error(
                    e.message ?: "An unexpected error occurred"
                )
            }
        }
    }
    
    private fun loadForecastByLocation(location: Location) {
        viewModelScope.launch {
            _forecastState.value = ForecastState.Loading
            
            try {
                // Get current user preferences
                val currentPreferences = (_settingsState.value as? SettingsState.Success)?.userPreferences
                val units = currentPreferences?.units?.value ?: Units.METRIC.value
                val language = currentPreferences?.language ?: "en"
                
                val result = weatherRepository.getForecastByLocation(location, units, language)
                result.fold(
                    onSuccess = { forecastResponse ->
                        _forecastState.value = ForecastState.Success(forecastResponse)
                    },
                    onFailure = { exception ->
                        _forecastState.value = ForecastState.Error(
                            exception.message ?: "Failed to load forecast data"
                        )
                    }
                )
            } catch (e: Exception) {
                _forecastState.value = ForecastState.Error(
                    e.message ?: "An unexpected error occurred while loading forecast"
                )
            }
        }
    }
    
    private fun loadForecastByCityName(city: String) {
        viewModelScope.launch {
            _forecastState.value = ForecastState.Loading
            
            try {
                // Get current user preferences
                val currentPreferences = (_settingsState.value as? SettingsState.Success)?.userPreferences
                val units = currentPreferences?.units?.value ?: Units.METRIC.value
                val language = currentPreferences?.language ?: "en"
                
                val result = weatherRepository.getForecastByCityName(city, units, language)
                result.fold(
                    onSuccess = { forecastResponse ->
                        _forecastState.value = ForecastState.Success(forecastResponse)
                    },
                    onFailure = { exception ->
                        _forecastState.value = ForecastState.Error(
                            exception.message ?: "Failed to load forecast data for $city"
                        )
                    }
                )
            } catch (e: Exception) {
                _forecastState.value = ForecastState.Error(
                    e.message ?: "An unexpected error occurred while loading forecast for $city"
                )
            }
        }
    }
    
    private fun loadWeatherAlerts(location: Location) {
        viewModelScope.launch {
            _alertsState.value = AlertsState.Loading
            
            try {
                // Get current user preferences
                val currentPreferences = (_settingsState.value as? SettingsState.Success)?.userPreferences
                val units = currentPreferences?.units?.value ?: Units.METRIC.value
                val language = currentPreferences?.language ?: "en"
                
                val result = weatherRepository.getWeatherAlerts(location, units, language)
                result.fold(
                    onSuccess = { alertsResponse ->
                        _alertsState.value = AlertsState.Success(alertsResponse)
                    },
                    onFailure = { exception ->
                        _alertsState.value = AlertsState.Error(
                            exception.message ?: "Failed to load weather alerts"
                        )
                    }
                )
            } catch (e: Exception) {
                _alertsState.value = AlertsState.Error(
                    e.message ?: "An unexpected error occurred while loading weather alerts"
                )
            }
        }
    }
    
    private fun updateWidgets() {
        // Get application context safely
        getApplication<Application>().let { app ->
            // Update all widgets when weather data changes
            com.christopheraldoo.simpleweatherapp.widget.WeatherWidgetUpdateService.startUpdateService(app)
        }
    }

    // Refresh all weather data
    fun refresh() {
        lastLocation?.let {
            loadWeatherByLocation(it)
            loadForecastByLocation(it)
            loadWeatherAlerts(it)
        } ?: lastCity?.let {
            searchWeatherByCity(it)
        } ?: getLocationAndWeather()
        
        // Update widgets with new data
        updateWidgets()
    }
    
    // User preferences update methods
    fun updateThemeMode(themeMode: ThemeMode) {
        viewModelScope.launch {
            userPreferencesDataStore.updateThemeMode(themeMode)
        }
    }
      fun updateUnits(units: Units) {
        viewModelScope.launch {
            userPreferencesDataStore.updateUnits(units)
            // Refresh data with new units
            refresh()
            // Ensure widgets are updated with new units
            updateWidgets()
        }
    }
    
    fun updateLanguage(language: String) {
        viewModelScope.launch {
            userPreferencesDataStore.updateLanguage(language)
            // Refresh data with new language
            refresh()
            // Ensure widgets are updated with new language
            updateWidgets()
        }
    }
    
    fun updateNotificationsEnabled(enabled: Boolean) {
        viewModelScope.launch {
            userPreferencesDataStore.updateNotificationsEnabled(enabled)
        }
    }
    
    fun updateDefaultCity(city: String) {
        viewModelScope.launch {
            userPreferencesDataStore.updateDefaultCity(city)
            if (city.isNotEmpty()) {
                searchWeatherByCity(city)
            }
        }
    }

    fun retry() {
        getLocationAndWeather()
    }
}
