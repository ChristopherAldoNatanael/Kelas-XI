package com.christopheraldoo.weatherapp.domain.model

sealed class WeatherResult<out T> {
    data class Success<out T>(val data: T) : WeatherResult<T>()
    data class Error(val message: String) : WeatherResult<Nothing>()
    object Loading : WeatherResult<Nothing>()
}

inline fun <T> WeatherResult<T>.onSuccess(action: (value: T) -> Unit): WeatherResult<T> {
    if (this is WeatherResult.Success) action(data)
    return this
}

inline fun <T> WeatherResult<T>.onError(action: (message: String) -> Unit): WeatherResult<T> {
    if (this is WeatherResult.Error) action(message)
    return this
}

inline fun <T> WeatherResult<T>.onLoading(action: () -> Unit): WeatherResult<T> {
    if (this is WeatherResult.Loading) action()
    return this
}
