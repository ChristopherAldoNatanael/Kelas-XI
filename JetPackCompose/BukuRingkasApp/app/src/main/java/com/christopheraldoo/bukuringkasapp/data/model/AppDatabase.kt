package com.christopheraldoo.bukuringkasapp.data.model

import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import android.content.Context

/**
 * Room Database untuk aplikasi BukuRingkasApp
 * Mengelola data riwayat ringkasan dan pertanyaan
 */
@Database(
    entities = [HistoryItem::class],
    version = 1,
    exportSchema = false
)
// TypeConverters akan ditambahkan nanti setelah Converters.kt diselesaikan
abstract class AppDatabase : RoomDatabase() {

    abstract fun historyDao(): HistoryDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "bukuringkas_database"
                )
                    .fallbackToDestructiveMigration()
                    .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
