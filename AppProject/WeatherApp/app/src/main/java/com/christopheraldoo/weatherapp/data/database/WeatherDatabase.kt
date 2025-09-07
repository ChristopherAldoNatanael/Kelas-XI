package com.christopheraldoo.weatherapp.data.database

import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase
import android.content.Context
import com.christopheraldoo.weatherapp.data.database.dao.WeatherCacheDao
import com.christopheraldoo.weatherapp.data.database.dao.FavoriteLocationDao
import com.christopheraldoo.weatherapp.data.database.dao.SearchHistoryDao
import com.christopheraldoo.weatherapp.data.database.entity.WeatherCacheEntity
import com.christopheraldoo.weatherapp.data.database.entity.FavoriteLocationEntity
import com.christopheraldoo.weatherapp.data.database.entity.SearchHistoryEntity

@Database(
    entities = [
        WeatherCacheEntity::class,
        FavoriteLocationEntity::class,
        SearchHistoryEntity::class
    ],
    version = 1,
    exportSchema = false
)
abstract class WeatherDatabase : RoomDatabase() {
    
    abstract fun weatherCacheDao(): WeatherCacheDao
    abstract fun favoriteLocationDao(): FavoriteLocationDao
    abstract fun searchHistoryDao(): SearchHistoryDao
    
    companion object {
        @Volatile
        private var INSTANCE: WeatherDatabase? = null
        
        fun getDatabase(context: Context): WeatherDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    WeatherDatabase::class.java,
                    "weather_database"
                )
                .addCallback(DatabaseCallback())
                .build()
                INSTANCE = instance
                instance
            }
        }
        
        private class DatabaseCallback : RoomDatabase.Callback() {
            override fun onCreate(db: SupportSQLiteDatabase) {
                super.onCreate(db)
                // Populate database with initial data if needed
            }
        }
    }
}