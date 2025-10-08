package com.christopheraldoo.simpleweatherapp.network

import com.christopheraldoo.simpleweatherapp.data.ForecastResponse
import com.christopheraldoo.simpleweatherapp.data.WeatherAlertsResponse
import com.christopheraldoo.simpleweatherapp.data.WeatherResponse
import retrofit2.http.GET
import retrofit2.http.Query
import retrofit2.Call

interface WeatherApiService {

    @GET("weather")
    fun getCurrentWeatherByCoordinates(
        @Query("lat") latitude: Double,
        @Query("lon") longitude: Double,
        @Query("appid") apiKey: String,
        @Query("units") units: String = "metric",
        @Query("lang") language: String = "en"
    ): Call<WeatherResponse>

    @GET("weather")
    fun getCurrentWeatherByCityName(
        @Query("q") cityName: String,
        @Query("appid") apiKey: String,
        @Query("units") units: String = "metric",
        @Query("lang") language: String = "en"
    ): Call<WeatherResponse>
    
    @GET("forecast")
    fun getForecastByCoordinates(
        @Query("lat") latitude: Double,
        @Query("lon") longitude: Double,
        @Query("appid") apiKey: String,
        @Query("units") units: String = "metric",
        @Query("lang") language: String = "en",
        @Query("cnt") count: Int = 40 // 5 days forecast, every 3 hours = 40 data points
    ): Call<ForecastResponse>
    
    @GET("forecast")
    fun getForecastByCityName(
        @Query("q") cityName: String,
        @Query("appid") apiKey: String,
        @Query("units") units: String = "metric",
        @Query("lang") language: String = "en",
        @Query("cnt") count: Int = 40
    ): Call<ForecastResponse>
    
    @GET("onecall")
    fun getOneCallData(
        @Query("lat") latitude: Double,
        @Query("lon") longitude: Double,
        @Query("exclude") exclude: String = "minutely,hourly,daily",
        @Query("appid") apiKey: String,
        @Query("units") units: String = "metric",
        @Query("lang") language: String = "en"
    ): Call<WeatherAlertsResponse>
}

object WeatherApi {
    const val BASE_URL = "https://api.openweathermap.org/data/2.5/"
    const val ICON_BASE_URL = "https://openweathermap.org/img/wn/"
    const val ONECALL_API_URL = "https://api.openweathermap.org/data/3.0/onecall"

    // Use the API key from Config
    val API_KEY get() = com.christopheraldoo.simpleweatherapp.utils.Config.OPENWEATHER_API_KEY
}
