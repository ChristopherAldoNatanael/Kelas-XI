package com.christopheraldoo.weatherapp.data.database.dao

import androidx.room.*
import com.christopheraldoo.weatherapp.data.database.entity.WeatherCacheEntity
import com.christopheraldoo.weatherapp.data.database.entity.FavoriteLocationEntity
import com.christopheraldoo.weatherapp.data.database.entity.SearchHistoryEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface WeatherCacheDao {
    
    @Query("SELECT * FROM weather_cache WHERE locationKey = :locationKey AND expiry_time > :currentTime")
    suspend fun getCachedWeather(locationKey: String, currentTime: Long): WeatherCacheEntity?
    
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertWeatherCache(weatherCache: WeatherCacheEntity)
    
    @Query("DELETE FROM weather_cache WHERE expiry_time < :currentTime")
    suspend fun deleteExpiredCache(currentTime: Long)
    
    @Query("DELETE FROM weather_cache")
    suspend fun clearAllCache()
}

@Dao
interface FavoriteLocationDao {
    
    @Query("SELECT * FROM favorite_locations ORDER BY display_order ASC, added_at DESC")
    fun getAllFavoriteLocations(): Flow<List<FavoriteLocationEntity>>
    
    @Query("SELECT * FROM favorite_locations WHERE is_current_location = 1 LIMIT 1")
    suspend fun getCurrentLocation(): FavoriteLocationEntity?
    
    @Insert
    suspend fun insertFavoriteLocation(location: FavoriteLocationEntity): Long
    
    @Update
    suspend fun updateFavoriteLocation(location: FavoriteLocationEntity)
    
    @Delete
    suspend fun deleteFavoriteLocation(location: FavoriteLocationEntity)
    
    @Query("DELETE FROM favorite_locations WHERE location_name = :locationName AND country = :country")
    suspend fun deleteFavoriteByName(locationName: String, country: String)
    
    @Query("SELECT EXISTS(SELECT 1 FROM favorite_locations WHERE location_name = :locationName AND country = :country)")
    suspend fun isFavorite(locationName: String, country: String): Boolean
    
    @Query("UPDATE favorite_locations SET display_order = :order WHERE id = :id")
    suspend fun updateDisplayOrder(id: Long, order: Int)
}

@Dao
interface SearchHistoryDao {
    
    @Query("SELECT * FROM search_history ORDER BY search_timestamp DESC LIMIT :limit")
    fun getRecentSearches(limit: Int = 10): Flow<List<SearchHistoryEntity>>
    
    @Query("SELECT * FROM search_history WHERE search_query LIKE '%' || :query || '%' ORDER BY search_count DESC, search_timestamp DESC LIMIT :limit")
    suspend fun searchHistory(query: String, limit: Int = 5): List<SearchHistoryEntity>
    
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSearchHistory(searchHistory: SearchHistoryEntity)
    
    @Query("UPDATE search_history SET search_count = search_count + 1, search_timestamp = :timestamp WHERE search_query = :query AND location_selected = :location")
    suspend fun updateSearchCount(query: String, location: String, timestamp: Long)
    
    @Query("DELETE FROM search_history WHERE search_timestamp < :cutoffTime")
    suspend fun deleteOldSearches(cutoffTime: Long)
    
    @Query("DELETE FROM search_history")
    suspend fun clearSearchHistory()
}