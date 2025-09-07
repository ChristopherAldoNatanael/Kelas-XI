package com.christopheraldoo.weatherapp.data.model

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

@JsonClass(generateAdapter = true)
data class CountryInfo(
    @Json(name = "name") val name: CountryName,
    @Json(name = "capital") val capital: List<String>? = null,
    @Json(name = "region") val region: String,
    @Json(name = "subregion") val subregion: String? = null,
    @Json(name = "latlng") val latlng: List<Double>? = null,
    @Json(name = "flag") val flag: String? = null,
    @Json(name = "population") val population: Long? = null
)

@JsonClass(generateAdapter = true)
data class CountryName(
    @Json(name = "common") val common: String,
    @Json(name = "official") val official: String
)

// Enhanced Search Location with more metadata
@JsonClass(generateAdapter = true)
data class EnhancedSearchLocation(
    val id: Int,
    val name: String,
    val region: String,
    val country: String,
    val countryCode: String = "",
    val lat: Double,
    val lon: Double,
    val url: String = "",
    val population: Long? = null,
    val timezone: String? = null,
    val isCapital: Boolean = false,
    val isMajorCity: Boolean = false,
    val flag: String? = null,
    val type: LocationType = LocationType.CITY
)

enum class LocationType {
    CITY,
    CAPITAL,
    COUNTRY,
    REGION,
    LANDMARK
}