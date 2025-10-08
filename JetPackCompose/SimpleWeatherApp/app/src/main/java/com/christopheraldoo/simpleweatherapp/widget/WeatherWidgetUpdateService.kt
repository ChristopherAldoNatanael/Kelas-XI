package com.christopheraldoo.simpleweatherapp.widget

import android.app.Service
import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.IBinder
import com.christopheraldoo.simpleweatherapp.data.Units
import com.christopheraldoo.simpleweatherapp.data.UserPreferencesDataStore
import com.christopheraldoo.simpleweatherapp.repository.WeatherRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Service to update weather widgets with the latest weather data
 */
class WeatherWidgetUpdateService : Service() {
    private val serviceJob = SupervisorJob()
    private val serviceScope = CoroutineScope(Dispatchers.IO + serviceJob)
    private val weatherRepository = WeatherRepository()
    
    companion object {
        const val ACTION_UPDATE_WIDGETS = "com.christopheraldoo.simpleweatherapp.UPDATE_WIDGETS"
        const val EXTRA_CITY_NAME = "extra_city_name"
        
        fun startUpdateService(context: Context, cityName: String? = null) {
            val intent = Intent(context, WeatherWidgetUpdateService::class.java).apply {
                action = ACTION_UPDATE_WIDGETS
                cityName?.let {
                    putExtra(EXTRA_CITY_NAME, it)
                }
            }
            context.startService(intent)
        }
    }

    override fun onBind(intent: Intent): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_UPDATE_WIDGETS) {
            val cityName = intent.getStringExtra(EXTRA_CITY_NAME)
            serviceScope.launch {
                updateWidgetsWithWeather(cityName)
            }
        }
        
        return START_NOT_STICKY
    }
    
    override fun onDestroy() {
        super.onDestroy()
        serviceScope.cancel()
    }
    
    private suspend fun updateWidgetsWithWeather(cityName: String?) {
        try {
            // Get user preferences for units and language
            val preferencesDataStore = UserPreferencesDataStore(applicationContext)
            val userPreferences = preferencesDataStore.userPreferencesFlow.first()
            
            val units = userPreferences.units.value
            val language = userPreferences.language
            
            // Determine which city to use
            val cityToUse = cityName ?: userPreferences.defaultCity
            
            // If we have a city, fetch weather for it
            if (cityToUse.isNotEmpty()) {
                val weatherResult = weatherRepository.getWeatherByCityName(cityToUse, units, language)
                
                weatherResult.fold(
                    onSuccess = { weatherResponse ->
                        withContext(Dispatchers.Main) {
                            // Update all widgets with this data
                            val appWidgetManager = AppWidgetManager.getInstance(applicationContext)
                            val appWidgetIds = appWidgetManager.getAppWidgetIds(
                                ComponentName(applicationContext, WeatherWidget::class.java)
                            )
                            
                            WeatherWidget.updateWidgets(
                                context = applicationContext,
                                appWidgetManager = appWidgetManager,
                                appWidgetIds = appWidgetIds,
                                weather = weatherResponse,
                                userPreferences = userPreferences
                            )
                        }
                    },
                    onFailure = {
                        // Handle error - could log or show an error in widget
                    }
                )
            }
        } catch (e: Exception) {
            // Handle exceptions
        }
    }
}
