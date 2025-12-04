package com.christopheraldoo.bukuringkasapp.data.model

import androidx.room.TypeConverter
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken

/**
 * TypeConverters untuk Room database
 * Mengconvert antara JSON dan object collections
 */
class Converters {

    private val gson = Gson()
    
    @TypeConverter
    fun fromStringList(value: String?): List<String> {
        if (value.isNullOrBlank()) return emptyList()
        val type = object : TypeToken<ArrayList<String>>() {}.type
        return try {
            gson.fromJson<ArrayList<String>>(value, type)
        } catch (e: Exception) {
            emptyList()
        }
    }

    @TypeConverter
    fun toStringList(list: List<String>?): String {
        return gson.toJson(list ?: emptyList())
    }
    
    @TypeConverter
    fun fromKeyPointList(value: String?): List<KeyPoint> {
        if (value.isNullOrBlank()) return emptyList()
        val type = object : TypeToken<ArrayList<KeyPoint>>() {}.type
        return try {
            gson.fromJson<ArrayList<KeyPoint>>(value, type)
        } catch (e: Exception) {
            emptyList()
        }
    }

    @TypeConverter
    fun toKeyPointList(list: List<KeyPoint>?): String {
        return gson.toJson(list ?: emptyList())
    }
    
    @TypeConverter
    fun fromFormulaList(value: String?): List<Formula> {
        if (value.isNullOrBlank()) return emptyList()
        val type = object : TypeToken<ArrayList<Formula>>() {}.type
        return try {
            gson.fromJson<ArrayList<Formula>>(value, type)
        } catch (e: Exception) {
            emptyList()
        }
    }

    @TypeConverter
    fun toFormulaList(list: List<Formula>?): String {
        return gson.toJson(list ?: emptyList())
    }
}
