package com.christopheraldoo.simpleweatherapp.repository

import android.location.Location
import com.christopheraldoo.simpleweatherapp.data.ForecastResponse
import com.christopheraldoo.simpleweatherapp.data.UserPreferences
import com.christopheraldoo.simpleweatherapp.data.WeatherAlertsResponse
import com.christopheraldoo.simpleweatherapp.data.WeatherResponse
import com.christopheraldoo.simpleweatherapp.network.WeatherApi
import com.christopheraldoo.simpleweatherapp.network.WeatherApiService
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.coroutines.suspendCoroutine

class WeatherRepository {

    private val weatherApiService: WeatherApiService

    init {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(WeatherApi.BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()

        weatherApiService = retrofit.create(WeatherApiService::class.java)
    }

    suspend fun getWeatherByLocation(
        location: Location, 
        units: String = "metric",
        language: String = "en"
    ): Result<WeatherResponse> {
        return suspendCoroutine { continuation ->
            weatherApiService.getCurrentWeatherByCoordinates(
                latitude = location.latitude,
                longitude = location.longitude,
                apiKey = WeatherApi.API_KEY,
                units = units,
                language = language
            ).enqueue(object : Callback<WeatherResponse> {
                override fun onResponse(
                    call: Call<WeatherResponse>,
                    response: Response<WeatherResponse>
                ) {
                    if (response.isSuccessful) {
                        response.body()?.let { weatherResponse ->
                            continuation.resume(Result.success(weatherResponse))
                        } ?: continuation.resume(Result.failure(Exception("Empty response body")))
                    } else {
                        continuation.resume(Result.failure(Exception("API Error: ${response.code()}")))
                    }
                }

                override fun onFailure(call: Call<WeatherResponse>, t: Throwable) {
                    continuation.resume(Result.failure(t))
                }
            })
        }
    }

    suspend fun getWeatherByCityName(
        cityName: String,
        units: String = "metric",
        language: String = "en"
    ): Result<WeatherResponse> {
        return suspendCoroutine { continuation ->
            weatherApiService.getCurrentWeatherByCityName(
                cityName = cityName,
                apiKey = WeatherApi.API_KEY,
                units = units,
                language = language
            ).enqueue(object : Callback<WeatherResponse> {
                override fun onResponse(
                    call: Call<WeatherResponse>,
                    response: Response<WeatherResponse>
                ) {
                    if (response.isSuccessful) {
                        response.body()?.let { weatherResponse ->
                            continuation.resume(Result.success(weatherResponse))
                        } ?: continuation.resume(Result.failure(Exception("Empty response body")))
                    } else {
                        continuation.resume(Result.failure(Exception("API Error: ${response.code()}")))
                    }
                }

                override fun onFailure(call: Call<WeatherResponse>, t: Throwable) {
                    continuation.resume(Result.failure(t))
                }
            })
        }
    }
    
    suspend fun getForecastByLocation(
        location: Location,
        units: String = "metric",
        language: String = "en"
    ): Result<ForecastResponse> {
        return suspendCoroutine { continuation ->
            weatherApiService.getForecastByCoordinates(
                latitude = location.latitude,
                longitude = location.longitude,
                apiKey = WeatherApi.API_KEY,
                units = units,
                language = language
            ).enqueue(object : Callback<ForecastResponse> {
                override fun onResponse(
                    call: Call<ForecastResponse>,
                    response: Response<ForecastResponse>
                ) {
                    if (response.isSuccessful) {
                        response.body()?.let { forecastResponse ->
                            continuation.resume(Result.success(forecastResponse))
                        } ?: continuation.resume(Result.failure(Exception("Empty forecast response body")))
                    } else {
                        continuation.resume(Result.failure(Exception("Forecast API Error: ${response.code()}")))
                    }
                }

                override fun onFailure(call: Call<ForecastResponse>, t: Throwable) {
                    continuation.resume(Result.failure(t))
                }
            })
        }
    }

    suspend fun getForecastByCityName(
        cityName: String,
        units: String = "metric",
        language: String = "en"
    ): Result<ForecastResponse> {
        return suspendCoroutine { continuation ->
            weatherApiService.getForecastByCityName(
                cityName = cityName,
                apiKey = WeatherApi.API_KEY,
                units = units,
                language = language
            ).enqueue(object : Callback<ForecastResponse> {
                override fun onResponse(
                    call: Call<ForecastResponse>,
                    response: Response<ForecastResponse>
                ) {
                    if (response.isSuccessful) {
                        response.body()?.let { forecastResponse ->
                            continuation.resume(Result.success(forecastResponse))
                        } ?: continuation.resume(Result.failure(Exception("Empty forecast response body")))
                    } else {
                        continuation.resume(Result.failure(Exception("Forecast API Error: ${response.code()}")))
                    }
                }

                override fun onFailure(call: Call<ForecastResponse>, t: Throwable) {
                    continuation.resume(Result.failure(t))
                }
            })
        }
    }
    
    suspend fun getWeatherAlerts(
        location: Location,
        units: String = "metric",
        language: String = "en"
    ): Result<WeatherAlertsResponse> {
        return suspendCoroutine { continuation ->
            weatherApiService.getOneCallData(
                latitude = location.latitude,
                longitude = location.longitude,
                apiKey = WeatherApi.API_KEY,
                units = units,
                language = language
            ).enqueue(object : Callback<WeatherAlertsResponse> {
                override fun onResponse(
                    call: Call<WeatherAlertsResponse>,
                    response: Response<WeatherAlertsResponse>
                ) {
                    if (response.isSuccessful) {
                        response.body()?.let { alertsResponse ->
                            continuation.resume(Result.success(alertsResponse))
                        } ?: continuation.resume(Result.failure(Exception("Empty alerts response body")))
                    } else {
                        continuation.resume(Result.failure(Exception("Alerts API Error: ${response.code()}")))
                    }
                }

                override fun onFailure(call: Call<WeatherAlertsResponse>, t: Throwable) {
                    continuation.resume(Result.failure(t))
                }
            })
        }
    }
}
