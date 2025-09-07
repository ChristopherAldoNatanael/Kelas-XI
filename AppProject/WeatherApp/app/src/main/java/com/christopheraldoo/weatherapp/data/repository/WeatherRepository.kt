package com.christopheraldoo.weatherapp.data.repository

import com.christopheraldoo.weatherapp.data.api.WeatherApi
import com.christopheraldoo.weatherapp.data.api.CountriesApi
import com.christopheraldoo.weatherapp.data.model.SearchLocation
import com.christopheraldoo.weatherapp.data.model.WeatherResponse
import com.christopheraldoo.weatherapp.data.model.EnhancedSearchLocation
import com.christopheraldoo.weatherapp.data.model.LocationType
import com.christopheraldoo.weatherapp.data.mock.MockWeatherData
import com.christopheraldoo.weatherapp.data.database.dao.WeatherCacheDao
import com.christopheraldoo.weatherapp.data.database.dao.FavoriteLocationDao
import com.christopheraldoo.weatherapp.data.database.dao.SearchHistoryDao
import com.christopheraldoo.weatherapp.data.database.entity.WeatherCacheEntity
import com.christopheraldoo.weatherapp.data.database.entity.FavoriteLocationEntity
import com.christopheraldoo.weatherapp.data.database.entity.SearchHistoryEntity
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import com.squareup.moshi.Moshi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.first
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class WeatherRepository @Inject constructor(
    private val weatherApi: WeatherApi,
    private val countriesApi: CountriesApi,
    private val weatherCacheDao: WeatherCacheDao,
    private val favoriteLocationDao: FavoriteLocationDao,
    private val searchHistoryDao: SearchHistoryDao,
    private val moshi: Moshi
) {

    fun getCurrentWeather(location: String, useCache: Boolean = true): Flow<WeatherResult<WeatherResponse>> = flow {
        emit(WeatherResult.Loading)
        
        val locationKey = location.lowercase().replace(" ", "_")
        val currentTime = System.currentTimeMillis()
        
        // Try to get cached data first if cache is enabled
        if (useCache) {
            try {
                val cachedWeather = weatherCacheDao.getCachedWeather(locationKey, currentTime)
                if (cachedWeather != null) {
                    val weatherAdapter = moshi.adapter(WeatherResponse::class.java)
                    val weatherData = weatherAdapter.fromJson(cachedWeather.weatherDataJson)
                    if (weatherData != null) {
                        emit(WeatherResult.Success(weatherData))
                        return@flow
                    }
                }
            } catch (e: Exception) {
                // Cache error, continue with API call
            }
        }
        
        try {
            val response = weatherApi.getCurrentWeather(WeatherApi.API_KEY, location)
            if (response.isSuccessful) {
                response.body()?.let { weather ->
                    // Cache the successful response
                    try {
                        val weatherAdapter = moshi.adapter(WeatherResponse::class.java)
                        val weatherJson = weatherAdapter.toJson(weather)
                        val cacheEntity = WeatherCacheEntity(
                            locationKey = locationKey,
                            weatherDataJson = weatherJson,
                            lastUpdated = currentTime
                        )
                        weatherCacheDao.insertWeatherCache(cacheEntity)
                        
                        // Clean up expired cache entries
                        weatherCacheDao.deleteExpiredCache(currentTime)
                    } catch (e: Exception) {
                        // Caching failed, but still return the data
                    }
                    
                    emit(WeatherResult.Success(weather))
                } ?: emit(WeatherResult.Error("No weather data available"))
            } else {
                // API failed, use mock data to demonstrate UI
                emit(WeatherResult.Success(MockWeatherData.getMockWeatherResponse(location)))
            }
        } catch (e: Exception) {
            // Network error, use mock data to demonstrate UI
            emit(WeatherResult.Success(MockWeatherData.getMockWeatherResponse(location)))
        }
    }    fun getWeatherForecast(location: String, days: Int = 7, useCache: Boolean = true): Flow<WeatherResult<WeatherResponse>> = flow {
        emit(WeatherResult.Loading)
        
        val locationKey = "${location.lowercase().replace(" ", "_")}_forecast_$days"
        val currentTime = System.currentTimeMillis()
        
        // Try cache first
        if (useCache) {
            try {
                val cachedWeather = weatherCacheDao.getCachedWeather(locationKey, currentTime)
                if (cachedWeather != null) {
                    val weatherAdapter = moshi.adapter(WeatherResponse::class.java)
                    val weatherData = weatherAdapter.fromJson(cachedWeather.weatherDataJson)
                    if (weatherData != null) {
                        emit(WeatherResult.Success(weatherData))
                        return@flow
                    }
                }
            } catch (e: Exception) {
                // Cache error, continue with API call
            }
        }
        
        try {
            val response = weatherApi.getWeatherForecast(WeatherApi.API_KEY, location, days)
            if (response.isSuccessful) {
                response.body()?.let { weather ->
                    // Cache the successful response
                    try {
                        val weatherAdapter = moshi.adapter(WeatherResponse::class.java)
                        val weatherJson = weatherAdapter.toJson(weather)
                        val cacheEntity = WeatherCacheEntity(
                            locationKey = locationKey,
                            weatherDataJson = weatherJson,
                            lastUpdated = currentTime,
                            expiryTime = currentTime + (2 * 60 * 60 * 1000) // 2 hours for forecast
                        )
                        weatherCacheDao.insertWeatherCache(cacheEntity)
                    } catch (e: Exception) {
                        // Caching failed, but still return the data
                    }
                    
                    emit(WeatherResult.Success(weather))
                } ?: emit(WeatherResult.Error("No forecast data available"))
            } else {
                // API failed, use mock data to demonstrate UI
                emit(WeatherResult.Success(MockWeatherData.getMockWeatherResponse(location)))
            }
        } catch (e: Exception) {
            // Network error, use mock data to demonstrate UI
            emit(WeatherResult.Success(MockWeatherData.getMockWeatherResponse(location)))
        }
    }    fun searchLocations(query: String): Flow<WeatherResult<List<SearchLocation>>> = flow {
        emit(WeatherResult.Loading)
        
        val searchQuery = query.trim()
        
        // Save search to history
        if (searchQuery.isNotBlank()) {
            try {
                val existingSearch = searchHistoryDao.searchHistory(searchQuery, 1).firstOrNull()
                if (existingSearch != null) {
                    searchHistoryDao.updateSearchCount(searchQuery, "", System.currentTimeMillis())
                } else {
                    searchHistoryDao.insertSearchHistory(
                        SearchHistoryEntity(
                            searchQuery = searchQuery,
                            locationSelected = ""
                        )
                    )
                }
            } catch (e: Exception) {
                // Search history failed, continue with search
            }
        }
        
        try {
            // Try WeatherAPI first
            val response = weatherApi.searchLocations(WeatherApi.API_KEY, searchQuery)
            if (response.isSuccessful) {
                response.body()?.let { locations ->
                    if (locations.isNotEmpty()) {
                        emit(WeatherResult.Success(locations))
                        return@flow
                    }
                }
            }
            
            // If WeatherAPI fails or returns empty, try enhanced search
            val enhancedLocations = enhancedLocationSearch(searchQuery)
            val convertedLocations = enhancedLocations.map { enhanced ->                SearchLocation(
                    id = enhanced.id.toLong(),
                    name = enhanced.name,
                    region = enhanced.region,
                    country = enhanced.country,
                    lat = enhanced.lat,
                    lon = enhanced.lon,
                    url = enhanced.url
                )
            }
            
            if (convertedLocations.isNotEmpty()) {
                emit(WeatherResult.Success(convertedLocations))
            } else {
                // Fallback to mock data
                val mockLocations = getMockSearchLocations(searchQuery)
                emit(WeatherResult.Success(mockLocations))
            }
            
        } catch (e: Exception) {
            // Network error, provide mock search results
            val mockLocations = getMockSearchLocations(searchQuery)
            emit(WeatherResult.Success(mockLocations))
        }
    }
    
    private suspend fun enhancedLocationSearch(query: String): List<EnhancedSearchLocation> {
        val results = mutableListOf<EnhancedSearchLocation>()
        val queryLower = query.lowercase()
        
        try {
            // Search countries by name
            val countryResponse = countriesApi.searchCountriesByName(query)
            if (countryResponse.isSuccessful) {
                countryResponse.body()?.let { countries ->
                    countries.take(5).forEachIndexed { index, country ->
                        // Add country capital as search result
                        country.capital?.firstOrNull()?.let { capital ->
                            country.latlng?.let { latlng ->
                                if (latlng.size >= 2) {
                                    results.add(
                                        EnhancedSearchLocation(
                                            id = 1000 + index,
                                            name = capital,
                                            region = country.subregion ?: country.region,
                                            country = country.name.common,
                                            lat = latlng[0],
                                            lon = latlng[1],
                                            population = country.population,
                                            isCapital = true,
                                            isMajorCity = true,
                                            flag = country.flag,
                                            type = LocationType.CAPITAL
                                        )
                                    )
                                }
                            }
                        }
                        
                        // Add country itself if it's a small nation
                        if (country.population != null && country.population < 10000000) {
                            country.latlng?.let { latlng ->
                                if (latlng.size >= 2) {
                                    results.add(
                                        EnhancedSearchLocation(
                                            id = 2000 + index,
                                            name = country.name.common,
                                            region = country.subregion ?: country.region,
                                            country = country.name.common,
                                            lat = latlng[0],
                                            lon = latlng[1],
                                            population = country.population,
                                            flag = country.flag,
                                            type = LocationType.COUNTRY
                                        )
                                    )
                                }
                            }
                        }
                    }
                }
            }
            
            // Search by capital city
            val capitalResponse = countriesApi.searchByCapital(query)
            if (capitalResponse.isSuccessful) {
                capitalResponse.body()?.let { countries ->
                    countries.take(3).forEachIndexed { index, country ->
                        country.capital?.firstOrNull()?.let { capital ->
                            country.latlng?.let { latlng ->
                                if (latlng.size >= 2) {
                                    results.add(
                                        EnhancedSearchLocation(
                                            id = 3000 + index,
                                            name = capital,
                                            region = country.subregion ?: country.region,
                                            country = country.name.common,
                                            lat = latlng[0],
                                            lon = latlng[1],
                                            population = country.population,
                                            isCapital = true,
                                            isMajorCity = true,
                                            flag = country.flag,
                                            type = LocationType.CAPITAL
                                        )
                                    )
                                }
                            }
                        }
                    }
                }
            }
            
        } catch (e: Exception) {
            // Countries API failed, continue with local data
        }
          // Add enhanced mock data for major cities
        results.addAll(getEnhancedMockLocations(queryLower))
        
        // Remove duplicates and limit results
        return results.distinctBy { "${it.name}, ${it.country}" }.take(15)
    }
    
    private fun getEnhancedMockLocations(query: String): List<EnhancedSearchLocation> {
        val worldCities = listOf(
            // Indonesian Cities
            EnhancedSearchLocation(1, "Jakarta", "Jakarta", "Indonesia", "ID", -6.21, 106.85, population = 10770000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(2, "Surabaya", "East Java", "Indonesia", "ID", -7.25, 112.75, population = 2874000, isMajorCity = true),
            EnhancedSearchLocation(3, "Bandung", "West Java", "Indonesia", "ID", -6.90, 107.62, population = 2444000, isMajorCity = true),
            EnhancedSearchLocation(4, "Medan", "North Sumatra", "Indonesia", "ID", 3.59, 98.67, population = 2435000, isMajorCity = true),
            EnhancedSearchLocation(5, "Semarang", "Central Java", "Indonesia", "ID", -6.97, 110.42, population = 1653000),
            
            // Major World Cities
            EnhancedSearchLocation(11, "London", "England", "United Kingdom", "GB", 51.52, -0.11, population = 8982000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(12, "New York", "New York", "United States", "US", 40.71, -74.01, population = 8336000, isMajorCity = true),
            EnhancedSearchLocation(13, "Tokyo", "Tokyo", "Japan", "JP", 35.69, 139.69, population = 37400000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(14, "Paris", "Île-de-France", "France", "FR", 48.85, 2.35, population = 2161000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(15, "Sydney", "New South Wales", "Australia", "AU", -33.87, 151.21, population = 5312000, isMajorCity = true),
            EnhancedSearchLocation(16, "Singapore", "Singapore", "Singapore", "SG", 1.29, 103.85, population = 5686000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(17, "Dubai", "Dubai", "United Arab Emirates", "AE", 25.20, 55.27, population = 3411000, isMajorCity = true),
            EnhancedSearchLocation(18, "Hong Kong", "Hong Kong", "Hong Kong", "HK", 22.32, 114.17, population = 7482000, isMajorCity = true),
            EnhancedSearchLocation(19, "Seoul", "Seoul", "South Korea", "KR", 37.57, 126.98, population = 9776000, isCapital = true, isMajorCity = true, type = LocationType.CAPITAL),
            EnhancedSearchLocation(20, "Mumbai", "Maharashtra", "India", "IN", 19.08, 72.88, population = 20411000, isMajorCity = true)
        )
        
        return if (query.isBlank()) {
            emptyList()
        } else {
            worldCities.filter { location ->
                location.name.lowercase().contains(query) ||
                location.country.lowercase().contains(query) ||                location.region.lowercase().contains(query)
            }
        }
    }
    
    // Enhanced favorite locations management
    fun getFavoriteLocations(): Flow<List<FavoriteLocationEntity>> {
        return favoriteLocationDao.getAllFavoriteLocations()
    }
    
    suspend fun addFavoriteLocation(
        locationName: String,
        country: String,
        latitude: Double,
        longitude: Double,
        timezoneId: String,
        isCurrentLocation: Boolean = false
    ): Boolean {
        return try {
            // Check if already exists
            val exists = favoriteLocationDao.isFavorite(locationName, country)
            if (!exists) {
                val maxOrder = favoriteLocationDao.getAllFavoriteLocations().first().maxOfOrNull { it.displayOrder } ?: 0
                val favoriteLocation = FavoriteLocationEntity(
                    locationName = locationName,
                    country = country,
                    latitude = latitude,
                    longitude = longitude,
                    timezoneId = timezoneId,
                    isCurrentLocation = isCurrentLocation,
                    displayOrder = maxOrder + 1
                )
                favoriteLocationDao.insertFavoriteLocation(favoriteLocation)
                true
            } else {
                false // Already exists
            }
        } catch (e: Exception) {
            false
        }
    }
    
    suspend fun removeFavoriteLocation(locationName: String, country: String): Boolean {
        return try {
            favoriteLocationDao.deleteFavoriteByName(locationName, country)
            true
        } catch (e: Exception) {
            false
        }
    }
    
    suspend fun isFavoriteLocation(locationName: String, country: String): Boolean {
        return try {
            favoriteLocationDao.isFavorite(locationName, country)
        } catch (e: Exception) {
            false
        }
    }
    
    suspend fun setCurrentLocation(locationName: String, country: String, latitude: Double, longitude: Double, timezoneId: String): Boolean {
        return try {
            // Remove current location flag from all locations
            val allFavorites = favoriteLocationDao.getAllFavoriteLocations().first()
            allFavorites.forEach { favorite ->
                if (favorite.isCurrentLocation) {
                    favoriteLocationDao.updateFavoriteLocation(favorite.copy(isCurrentLocation = false))
                }
            }
            
            // Add or update the new current location
            val exists = favoriteLocationDao.isFavorite(locationName, country)
            if (exists) {
                val existingFavorite = allFavorites.find { it.locationName == locationName && it.country == country }
                existingFavorite?.let {
                    favoriteLocationDao.updateFavoriteLocation(it.copy(isCurrentLocation = true))
                }
            } else {
                addFavoriteLocation(locationName, country, latitude, longitude, timezoneId, true)
            }
            true
        } catch (e: Exception) {
            false
        }
    }
    
    suspend fun getCurrentLocation(): FavoriteLocationEntity? {
        return try {
            favoriteLocationDao.getCurrentLocation()
        } catch (e: Exception) {
            null
        }
    }
    
    // Search history management
    fun getRecentSearches(limit: Int = 10): Flow<List<SearchHistoryEntity>> {
        return searchHistoryDao.getRecentSearches(limit)
    }
    
    suspend fun clearSearchHistory(): Boolean {
        return try {
            searchHistoryDao.clearSearchHistory()
            true
        } catch (e: Exception) {
            false
        }
    }
    
    // Cache management
    suspend fun clearWeatherCache(): Boolean {
        return try {
            weatherCacheDao.clearAllCache()
            true
        } catch (e: Exception) {
            false
        }
    }
    
    suspend fun refreshWeatherData(location: String): Flow<WeatherResult<WeatherResponse>> {
        return getCurrentWeather(location, useCache = false)
    }

    private fun getMockSearchLocations(query: String): List<SearchLocation> {
        val allLocations = listOf(
            // Indonesian Cities
            SearchLocation(1, "Jakarta", "Jakarta", "Indonesia", -6.21, 106.85, "http://jakarta.url"),
            SearchLocation(2, "Surabaya", "East Java", "Indonesia", -7.25, 112.75, "http://surabaya.url"),
            SearchLocation(3, "Bandung", "West Java", "Indonesia", -6.90, 107.62, "http://bandung.url"),
            SearchLocation(4, "Medan", "North Sumatra", "Indonesia", 3.59, 98.67, "http://medan.url"),
            SearchLocation(5, "Semarang", "Central Java", "Indonesia", -6.97, 110.42, "http://semarang.url"),
            SearchLocation(6, "Makassar", "South Sulawesi", "Indonesia", -5.15, 119.43, "http://makassar.url"),
            SearchLocation(7, "Palembang", "South Sumatra", "Indonesia", -2.99, 104.76, "http://palembang.url"),
            SearchLocation(8, "Yogyakarta", "Yogyakarta", "Indonesia", -7.80, 110.36, "http://yogyakarta.url"),
            SearchLocation(9, "Denpasar", "Bali", "Indonesia", -8.65, 115.22, "http://denpasar.url"),
            SearchLocation(10, "Balikpapan", "East Kalimantan", "Indonesia", -1.24, 116.83, "http://balikpapan.url"),
            
            // Major International Cities
            SearchLocation(11, "London", "England", "United Kingdom", 51.52, -0.11, "http://london.url"),
            SearchLocation(12, "New York", "New York", "United States", 40.71, -74.01, "http://newyork.url"),
            SearchLocation(13, "Tokyo", "Tokyo", "Japan", 35.69, 139.69, "http://tokyo.url"),
            SearchLocation(14, "Paris", "Ile-de-France", "France", 48.85, 2.35, "http://paris.url"),
            SearchLocation(15, "Sydney", "New South Wales", "Australia", -33.87, 151.21, "http://sydney.url"),
            SearchLocation(16, "Singapore", "Singapore", "Singapore", 1.29, 103.85, "http://singapore.url"),
            SearchLocation(17, "Dubai", "Dubai", "United Arab Emirates", 25.20, 55.27, "http://dubai.url"),
            SearchLocation(18, "Hong Kong", "Hong Kong", "China", 22.32, 114.17, "http://hongkong.url"),
            SearchLocation(19, "Seoul", "Seoul", "South Korea", 37.57, 126.98, "http://seoul.url"),
            SearchLocation(20, "Mumbai", "Maharashtra", "India", 19.08, 72.88, "http://mumbai.url"),
            SearchLocation(21, "Bangkok", "Bangkok", "Thailand", 13.76, 100.50, "http://bangkok.url"),
            SearchLocation(22, "Kuala Lumpur", "Kuala Lumpur", "Malaysia", 3.14, 101.69, "http://kl.url"),
            SearchLocation(23, "Manila", "Metro Manila", "Philippines", 14.60, 120.98, "http://manila.url"),
            SearchLocation(24, "Ho Chi Minh City", "Ho Chi Minh", "Vietnam", 10.82, 106.63, "http://hcmc.url"),
            
            // European Cities
            SearchLocation(25, "Berlin", "Berlin", "Germany", 52.52, 13.41, "http://berlin.url"),
            SearchLocation(26, "Rome", "Lazio", "Italy", 41.90, 12.50, "http://rome.url"),
            SearchLocation(27, "Madrid", "Madrid", "Spain", 40.42, -3.70, "http://madrid.url"),
            SearchLocation(28, "Amsterdam", "North Holland", "Netherlands", 52.37, 4.90, "http://amsterdam.url"),
            SearchLocation(29, "Vienna", "Vienna", "Austria", 48.21, 16.37, "http://vienna.url"),
            SearchLocation(30, "Zurich", "Zurich", "Switzerland", 47.38, 8.54, "http://zurich.url"),
            SearchLocation(31, "Stockholm", "Stockholm", "Sweden", 59.33, 18.07, "http://stockholm.url"),
            SearchLocation(32, "Oslo", "Oslo", "Norway", 59.91, 10.75, "http://oslo.url"),
            SearchLocation(33, "Copenhagen", "Capital Region", "Denmark", 55.68, 12.57, "http://copenhagen.url"),
            SearchLocation(34, "Helsinki", "Uusimaa", "Finland", 60.17, 24.95, "http://helsinki.url"),
            SearchLocation(35, "Warsaw", "Mazovia", "Poland", 52.23, 21.01, "http://warsaw.url"),
            SearchLocation(36, "Prague", "Prague", "Czech Republic", 50.08, 14.44, "http://prague.url"),
            SearchLocation(37, "Budapest", "Budapest", "Hungary", 47.50, 19.04, "http://budapest.url"),
            SearchLocation(38, "Brussels", "Brussels", "Belgium", 50.85, 4.35, "http://brussels.url"),
            
            // Americas
            SearchLocation(39, "Los Angeles", "California", "United States", 34.05, -118.24, "http://la.url"),
            SearchLocation(40, "Chicago", "Illinois", "United States", 41.88, -87.63, "http://chicago.url"),
            SearchLocation(41, "Miami", "Florida", "United States", 25.76, -80.19, "http://miami.url"),
            SearchLocation(42, "San Francisco", "California", "United States", 37.77, -122.42, "http://sf.url"),
            SearchLocation(43, "Toronto", "Ontario", "Canada", 43.65, -79.38, "http://toronto.url"),
            SearchLocation(44, "Vancouver", "British Columbia", "Canada", 49.25, -123.10, "http://vancouver.url"),
            SearchLocation(45, "São Paulo", "São Paulo", "Brazil", -23.55, -46.64, "http://saopaulo.url"),
            SearchLocation(46, "Rio de Janeiro", "Rio de Janeiro", "Brazil", -22.91, -43.17, "http://rio.url"),
            SearchLocation(47, "Mexico City", "Mexico City", "Mexico", 19.43, -99.13, "http://mexicocity.url"),
            SearchLocation(48, "Buenos Aires", "Buenos Aires", "Argentina", -34.61, -58.38, "http://buenosaires.url"),
            
            // Africa & Middle East
            SearchLocation(49, "Cairo", "Cairo", "Egypt", 30.04, 31.24, "http://cairo.url"),
            SearchLocation(50, "Cape Town", "Western Cape", "South Africa", -33.92, 18.42, "http://capetown.url"),
            SearchLocation(51, "Lagos", "Lagos", "Nigeria", 6.52, 3.38, "http://lagos.url"),
            SearchLocation(52, "Nairobi", "Nairobi", "Kenya", -1.29, 36.82, "http://nairobi.url"),
            SearchLocation(53, "Tel Aviv", "Tel Aviv", "Israel", 32.11, 34.80, "http://telaviv.url"),
            SearchLocation(54, "Istanbul", "Istanbul", "Turkey", 41.01, 28.98, "http://istanbul.url"),
            SearchLocation(55, "Riyadh", "Riyadh", "Saudi Arabia", 24.71, 46.67, "http://riyadh.url"),
            SearchLocation(56, "Doha", "Doha", "Qatar", 25.28, 51.53, "http://doha.url"),
            
            // Antarctica & Polar Regions
            SearchLocation(57, "McMurdo Station", "Ross Island", "Antarctica", -77.85, 166.67, "http://mcmurdo.url"),
            SearchLocation(58, "Rothera Research Station", "Adelaide Island", "Antarctica", -67.57, -68.13, "http://rothera.url"),
            SearchLocation(59, "Halley Research Station", "Brunt Ice Shelf", "Antarctica", -75.58, -26.66, "http://halley.url"),
            SearchLocation(60, "South Pole Station", "South Pole", "Antarctica", -90.00, 0.00, "http://southpole.url"),
            
            // Australia & Oceania
            SearchLocation(61, "Melbourne", "Victoria", "Australia", -37.81, 144.96, "http://melbourne.url"),
            SearchLocation(62, "Perth", "Western Australia", "Australia", -31.95, 115.86, "http://perth.url"),
            SearchLocation(63, "Brisbane", "Queensland", "Australia", -27.47, 153.03, "http://brisbane.url"),
            SearchLocation(64, "Auckland", "Auckland", "New Zealand", -36.85, 174.76, "http://auckland.url"),
            SearchLocation(65, "Wellington", "Wellington", "New Zealand", -41.29, 174.78, "http://wellington.url"),
            SearchLocation(66, "Suva", "Central Division", "Fiji", -18.14, 178.44, "http://suva.url")
        )
        
        return if (query.isBlank()) {
            emptyList()
        } else {
            val queryLower = query.lowercase()
            allLocations.filter { location ->
                location.name.lowercase().contains(queryLower) || 
                location.region.lowercase().contains(queryLower) ||
                location.country.lowercase().contains(queryLower) ||
                
                // Special handling for common search terms
                (queryLower.contains("antar") && location.country.lowercase() == "antarctica") ||
                (queryLower.contains("kutub") && location.country.lowercase() == "antarctica") ||
                (queryLower.contains("es") && location.country.lowercase() == "antarctica") ||
                (queryLower.contains("polar") && location.country.lowercase() == "antarctica")
            }.sortedBy { location ->
                // Sort by relevance - exact matches first, then partial matches
                when {
                    location.name.lowercase() == queryLower -> 0
                    location.name.lowercase().startsWith(queryLower) -> 1
                    location.country.lowercase().startsWith(queryLower) -> 2
                    location.region.lowercase().startsWith(queryLower) -> 3
                    else -> 4
                }
            }.take(8) // Limit to 8 results for better UX
        }
    }
}
