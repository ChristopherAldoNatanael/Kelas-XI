package com.christopheraldoo.weatherapp.data.database.entity

import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "weather_cache")
data class WeatherCacheEntity(
    @PrimaryKey 
    val locationKey: String,
    @ColumnInfo(name = "weather_data")
    val weatherDataJson: String,
    @ColumnInfo(name = "last_updated")
    val lastUpdated: Long,
    @ColumnInfo(name = "expiry_time")
    val expiryTime: Long = lastUpdated + (60 * 60 * 1000) // 1 hour cache
)

@Entity(tableName = "favorite_locations")
data class FavoriteLocationEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    @ColumnInfo(name = "location_name")
    val locationName: String,
    @ColumnInfo(name = "country")
    val country: String,
    @ColumnInfo(name = "latitude")
    val latitude: Double,
    @ColumnInfo(name = "longitude")
    val longitude: Double,
    @ColumnInfo(name = "timezone_id")
    val timezoneId: String,
    @ColumnInfo(name = "is_current_location")
    val isCurrentLocation: Boolean = false,
    @ColumnInfo(name = "added_at")
    val addedAt: Long = System.currentTimeMillis(),
    @ColumnInfo(name = "display_order")
    val displayOrder: Int = 0
)

@Entity(tableName = "search_history")
data class SearchHistoryEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    @ColumnInfo(name = "search_query")
    val searchQuery: String,
    @ColumnInfo(name = "location_selected")
    val locationSelected: String,
    @ColumnInfo(name = "search_timestamp")
    val searchTimestamp: Long = System.currentTimeMillis(),
    @ColumnInfo(name = "search_count")
    val searchCount: Int = 1
)