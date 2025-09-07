package com.christopheraldoo.weatherapp.presentation.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.weatherapp.data.model.SearchLocation
import com.christopheraldoo.weatherapp.data.model.WeatherResponse
import com.christopheraldoo.weatherapp.data.database.entity.FavoriteLocationEntity
import com.christopheraldoo.weatherapp.data.database.entity.SearchHistoryEntity
import com.christopheraldoo.weatherapp.data.repository.WeatherRepository
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class WeatherViewModel @Inject constructor(
    private val weatherRepository: WeatherRepository
) : ViewModel() {
    
    private val _weatherState = MutableStateFlow<WeatherResult<WeatherResponse>>(WeatherResult.Loading)
    val weatherState: StateFlow<WeatherResult<WeatherResponse>> = _weatherState.asStateFlow()
    
    private val _forecastState = MutableStateFlow<WeatherResult<WeatherResponse>>(WeatherResult.Loading)
    val forecastState: StateFlow<WeatherResult<WeatherResponse>> = _forecastState.asStateFlow()
    
    private val _searchResults = MutableStateFlow<WeatherResult<List<SearchLocation>>>(WeatherResult.Success(emptyList()))
    val searchResults: StateFlow<WeatherResult<List<SearchLocation>>> = _searchResults.asStateFlow()
    
    private val _selectedLocation = MutableStateFlow("Jakarta, Indonesia")
    val selectedLocation: StateFlow<String> = _selectedLocation.asStateFlow()
    
    private val _isSearching = MutableStateFlow(false)
    val isSearching: StateFlow<Boolean> = _isSearching.asStateFlow()
    
    // Enhanced states for favorites and history
    private val _favoriteLocations = MutableStateFlow<WeatherResult<List<FavoriteLocationEntity>>>(WeatherResult.Loading)
    val favoriteLocations: StateFlow<WeatherResult<List<FavoriteLocationEntity>>> = _favoriteLocations.asStateFlow()
    
    private val _searchHistory = MutableStateFlow<WeatherResult<List<SearchHistoryEntity>>>(WeatherResult.Loading)
    val searchHistory: StateFlow<WeatherResult<List<SearchHistoryEntity>>> = _searchHistory.asStateFlow()
    
    private val _isFavorite = MutableStateFlow(false)
    val isFavorite: StateFlow<Boolean> = _isFavorite.asStateFlow()
    
    init {
        getCurrentWeather(_selectedLocation.value)
        getForecast(_selectedLocation.value)
        loadFavoriteLocations()
        loadSearchHistory()
        checkIfCurrentLocationIsFavorite()
    }
      fun getCurrentWeather(location: String, useCache: Boolean = true) {
        viewModelScope.launch {
            weatherRepository.getCurrentWeather(location, useCache)
                .collect { result ->
                    _weatherState.value = result
                }
        }
    }
    
    fun getForecast(location: String, useCache: Boolean = true) {
        viewModelScope.launch {
            weatherRepository.getWeatherForecast(location, 7, useCache)
                .collect { result ->
                    _forecastState.value = result
                }
        }
    }
      fun searchLocations(query: String) {
        // Immediately show loading for better UX
        if (query.isNotBlank()) {
            _searchResults.value = WeatherResult.Loading
        }
        
        viewModelScope.launch {
            if (query.isBlank()) {
                _searchResults.value = WeatherResult.Success(emptyList())
                return@launch
            }
            
            try {
                weatherRepository.searchLocations(query)
                    .collect { result ->
                        _searchResults.value = result
                    }
            } catch (e: Exception) {
                _searchResults.value = WeatherResult.Error("Search failed: ${e.message}")
            }
        }
    }
      fun selectLocation(location: String) {
        _selectedLocation.value = location
        _isSearching.value = false
        
        // Clear search results
        _searchResults.value = WeatherResult.Success(emptyList())
        
        // Load weather data for selected location
        getCurrentWeather(location)
        getForecast(location)
        checkIfCurrentLocationIsFavorite()
    }
    
    fun setSearching(isSearching: Boolean) {
        _isSearching.value = isSearching
        if (!isSearching) {
            _searchResults.value = WeatherResult.Success(emptyList())
        }
    }
    
    fun refreshWeather() {
        getCurrentWeather(_selectedLocation.value, useCache = false)
        getForecast(_selectedLocation.value, useCache = false)
    }
    
    // Enhanced favorite locations management
    fun loadFavoriteLocations() {
        viewModelScope.launch {
            try {
                weatherRepository.getFavoriteLocations()
                    .collect { locations ->
                        _favoriteLocations.value = WeatherResult.Success(locations)
                    }
            } catch (e: Exception) {
                _favoriteLocations.value = WeatherResult.Error(e.message ?: "Failed to load favorites")
            }
        }
    }
    
    fun addFavoriteLocation() {
        val currentLocation = _selectedLocation.value
        val currentWeatherState = _weatherState.value
        
        if (currentWeatherState is WeatherResult.Success) {
            val weatherData = currentWeatherState.data
            viewModelScope.launch {
                val success = weatherRepository.addFavoriteLocation(
                    locationName = weatherData.location.name,
                    country = weatherData.location.country,
                    latitude = weatherData.location.lat,
                    longitude = weatherData.location.lon,
                    timezoneId = weatherData.location.tzId
                )
                
                if (success) {
                    _isFavorite.value = true
                    loadFavoriteLocations()
                }
            }
        }
    }
    
    fun removeFavoriteLocation(locationName: String, country: String) {
        viewModelScope.launch {
            val success = weatherRepository.removeFavoriteLocation(locationName, country)
            if (success) {
                checkIfCurrentLocationIsFavorite()
                loadFavoriteLocations()
            }
        }
    }
    
    fun toggleFavoriteLocation() {
        if (_isFavorite.value) {
            val currentWeatherState = _weatherState.value
            if (currentWeatherState is WeatherResult.Success) {
                removeFavoriteLocation(
                    currentWeatherState.data.location.name,
                    currentWeatherState.data.location.country
                )
            }
        } else {
            addFavoriteLocation()
        }
    }
    
    private fun checkIfCurrentLocationIsFavorite() {
        val currentWeatherState = _weatherState.value
        if (currentWeatherState is WeatherResult.Success) {
            viewModelScope.launch {
                val isFav = weatherRepository.isFavoriteLocation(
                    currentWeatherState.data.location.name,
                    currentWeatherState.data.location.country
                )
                _isFavorite.value = isFav
            }
        }
    }
    
    fun setCurrentLocation(locationName: String, country: String) {
        val currentWeatherState = _weatherState.value
        if (currentWeatherState is WeatherResult.Success) {
            val weatherData = currentWeatherState.data
            viewModelScope.launch {
                weatherRepository.setCurrentLocation(
                    locationName = locationName,
                    country = country,
                    latitude = weatherData.location.lat,
                    longitude = weatherData.location.lon,
                    timezoneId = weatherData.location.tzId
                )
                loadFavoriteLocations()
            }
        }
    }
    
    // Search history management
    fun loadSearchHistory() {
        viewModelScope.launch {
            try {
                weatherRepository.getRecentSearches(10)
                    .collect { searches ->
                        _searchHistory.value = WeatherResult.Success(searches)
                    }
            } catch (e: Exception) {
                _searchHistory.value = WeatherResult.Error(e.message ?: "Failed to load search history")
            }
        }
    }
    
    fun clearSearchHistory() {
        viewModelScope.launch {
            val success = weatherRepository.clearSearchHistory()
            if (success) {
                loadSearchHistory()
            }
        }
    }
    
    // Cache management
    fun clearCache() {
        viewModelScope.launch {
            weatherRepository.clearWeatherCache()
        }
    }
}
