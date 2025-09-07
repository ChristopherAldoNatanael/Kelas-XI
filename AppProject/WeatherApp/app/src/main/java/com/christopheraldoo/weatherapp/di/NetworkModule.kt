package com.christopheraldoo.weatherapp.di

import android.content.Context
import com.christopheraldoo.weatherapp.data.api.WeatherApi
import com.christopheraldoo.weatherapp.data.api.CountriesApi
import com.christopheraldoo.weatherapp.data.database.WeatherDatabase
import com.christopheraldoo.weatherapp.data.database.dao.WeatherCacheDao
import com.christopheraldoo.weatherapp.data.database.dao.FavoriteLocationDao
import com.christopheraldoo.weatherapp.data.database.dao.SearchHistoryDao
import com.squareup.moshi.Moshi
import com.squareup.moshi.kotlin.reflect.KotlinJsonAdapterFactory
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Qualifier
import javax.inject.Singleton

@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class WeatherRetrofit

@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class CountriesRetrofit

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {
    
    @Provides
    @Singleton
    fun provideMoshi(): Moshi {
        return Moshi.Builder()
            .add(KotlinJsonAdapterFactory())
            .build()
    }
    
    @Provides
    @Singleton
    fun provideOkHttpClient(): OkHttpClient {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }
        
        return OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }
      @Provides
    @Singleton
    @WeatherRetrofit
    fun provideWeatherRetrofit(okHttpClient: OkHttpClient, moshi: Moshi): Retrofit {
        return Retrofit.Builder()
            .baseUrl(WeatherApi.BASE_URL)
            .client(okHttpClient)
            .addConverterFactory(MoshiConverterFactory.create(moshi))
            .build()
    }
    
    @Provides
    @Singleton
    @CountriesRetrofit
    fun provideCountriesRetrofit(okHttpClient: OkHttpClient, moshi: Moshi): Retrofit {
        return Retrofit.Builder()
            .baseUrl(CountriesApi.BASE_URL)
            .client(okHttpClient)
            .addConverterFactory(MoshiConverterFactory.create(moshi))
            .build()
    }
    
    @Provides
    @Singleton
    fun provideWeatherApi(@WeatherRetrofit retrofit: Retrofit): WeatherApi {
        return retrofit.create(WeatherApi::class.java)
    }
    
    @Provides
    @Singleton
    fun provideCountriesApi(@CountriesRetrofit retrofit: Retrofit): CountriesApi {
        return retrofit.create(CountriesApi::class.java)
    }
    
    // Database providers
    @Provides
    @Singleton
    fun provideWeatherDatabase(@ApplicationContext context: Context): WeatherDatabase {
        return WeatherDatabase.getDatabase(context)
    }
    
    @Provides
    fun provideWeatherCacheDao(database: WeatherDatabase): WeatherCacheDao {
        return database.weatherCacheDao()
    }
    
    @Provides
    fun provideFavoriteLocationDao(database: WeatherDatabase): FavoriteLocationDao {
        return database.favoriteLocationDao()
    }
    
    @Provides
    fun provideSearchHistoryDao(database: WeatherDatabase): SearchHistoryDao {
        return database.searchHistoryDao()
    }
}
