package com.christopheraldoo.aplikasimonitoringkelas.cache

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken

/**
 * Cache Manager untuk menyimpan data lokal dan mengurangi API calls
 * Dengan TTL (Time To Live) untuk setiap cache entry
 */
class CacheManager(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("MonitoringKelasCache", Context.MODE_PRIVATE)
    private val gson = Gson()

    companion object {
        // TTL: 5 menit untuk data yang sering berubah (schedules)
        const val TTL_SHORT = 5 * 60 * 1000L

        // TTL: 30 menit untuk data master (users, teachers, subjects, classrooms)
        const val TTL_LONG = 30 * 60 * 1000L
    }

    /**
     * Save data dengan automatic TTL
     */
    fun <T> saveData(key: String, data: T, ttlMs: Long = TTL_LONG) {
        try {
            val json = gson.toJson(data)
            prefs.edit().apply {
                putString(key, json)
                putLong("${key}_time", System.currentTimeMillis())
                apply()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    /**
     * Get data dengan automatic TTL validation
     */
    fun <T> getData(key: String, type: TypeToken<T>, ttlMs: Long = TTL_LONG): T? {
        return try {
            val json = prefs.getString(key, null) ?: return null
            val savedTime = prefs.getLong("${key}_time", 0L)
            val currentTime = System.currentTimeMillis()

            // Check if data is still valid
            if (currentTime - savedTime > ttlMs) {
                clearData(key)
                return null
            }

            gson.fromJson(json, type)
        } catch (e: Exception) {
            e.printStackTrace()
            null
        }
    }

    /**
     * Check apakah cache masih valid
     */
    fun isCacheValid(key: String, ttlMs: Long = TTL_LONG): Boolean {
        return try {
            val savedTime = prefs.getLong("${key}_time", 0L)
            val currentTime = System.currentTimeMillis()
            currentTime - savedTime <= ttlMs && prefs.contains(key)
        } catch (e: Exception) {
            false
        }
    }

    /**
     * Clear cache untuk key tertentu
     */
    fun clearData(key: String) {
        prefs.edit().apply {
            remove(key)
            remove("${key}_time")
            apply()
        }
    }

    /**
     * Clear semua cache
     */
    fun clearAllCache() {
        prefs.edit().clear().apply()
    }

    /**
     * Get remaining TTL dalam milliseconds
     */
    fun getRemainingTTL(key: String, ttlMs: Long = TTL_LONG): Long {
        val savedTime = prefs.getLong("${key}_time", 0L)
        val currentTime = System.currentTimeMillis()
        val elapsed = currentTime - savedTime
        return maxOf(0, ttlMs - elapsed)
    }
}
