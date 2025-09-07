package com.christopheraldoo.weatherapp.data.api

import com.christopheraldoo.weatherapp.data.model.SearchLocation
import com.christopheraldoo.weatherapp.data.model.WeatherResponse
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Query

interface WeatherApi {
    
    @GET("current.json")
    suspend fun getCurrentWeather(
        @Query("key") apiKey: String,
        @Query("q") location: String,
        @Query("aqi") aqi: String = "yes"
    ): Response<WeatherResponse>
    
    @GET("forecast.json")
    suspend fun getWeatherForecast(
        @Query("key") apiKey: String,
        @Query("q") location: String,
        @Query("days") days: Int = 7,
        @Query("aqi") aqi: String = "yes",
        @Query("alerts") alerts: String = "yes"
    ): Response<WeatherResponse>
    
    @GET("search.json")
    suspend fun searchLocations(
        @Query("key") apiKey: String,
        @Query("q") query: String
    ): Response<List<SearchLocation>>
      companion object {
        const val BASE_URL = "https://api.weatherapi.com/v1/"
        // Updated API key - you can get free key from https://www.weatherapi.com/
        const val API_KEY = "c8f1e8b7a9d54c2b8e174158241212a8" 
    }
}
