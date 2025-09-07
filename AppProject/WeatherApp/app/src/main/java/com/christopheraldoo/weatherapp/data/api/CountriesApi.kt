package com.christopheraldoo.weatherapp.data.api

import com.christopheraldoo.weatherapp.data.model.CountryInfo
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Path
import retrofit2.http.Query

interface CountriesApi {
    
    @GET("all")
    suspend fun getAllCountries(): Response<List<CountryInfo>>
    
    @GET("name/{name}")
    suspend fun searchCountriesByName(
        @Path("name") name: String,
        @Query("fields") fields: String = "name,capital,region,subregion,latlng,flag,population"
    ): Response<List<CountryInfo>>
    
    @GET("capital/{capital}")
    suspend fun searchByCapital(
        @Path("capital") capital: String,
        @Query("fields") fields: String = "name,capital,region,subregion,latlng,flag,population"
    ): Response<List<CountryInfo>>
    
    companion object {
        const val BASE_URL = "https://restcountries.com/v3.1/"
    }
}