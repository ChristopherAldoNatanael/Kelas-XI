package com.christopheraldoo.simpleweatherapp.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.widget.RemoteViews
import com.christopheraldoo.simpleweatherapp.MainActivity
import com.christopheraldoo.simpleweatherapp.R
import com.christopheraldoo.simpleweatherapp.data.Units
import com.christopheraldoo.simpleweatherapp.data.UserPreferences
import com.christopheraldoo.simpleweatherapp.data.WeatherResponse
import com.christopheraldoo.simpleweatherapp.network.WeatherApi
import com.christopheraldoo.simpleweatherapp.utils.formatTemperature
import com.christopheraldoo.simpleweatherapp.utils.formatTime
import kotlinx.coroutines.*
import java.text.SimpleDateFormat
import java.util.*

/**
 * Implementation of App Widget functionality.
 */
class WeatherWidget : AppWidgetProvider() {
    
    companion object {
        // Cache for weather icons to minimize resource lookups
        private val weatherIconCache = mutableMapOf<String, Int>()
        
        /**
         * Schedule periodic widget updates
         */
        fun scheduleWidgetUpdates(context: Context) {
            // Start an immediate update
            WeatherWidgetUpdateService.startUpdateService(context)
            
            // You could also set up a periodic WorkManager task here if you wanted
            // more sophisticated scheduling than the widget's updatePeriodMillis
        }
        /**
         * Update all widgets with weather data
         */
        fun updateWidgets(
            context: Context,
            appWidgetManager: AppWidgetManager,
            appWidgetIds: IntArray,
            weather: WeatherResponse,
            userPreferences: UserPreferences
        ) {
            // There may be multiple widgets active, so update all of them
            for (appWidgetId in appWidgetIds) {
                updateAppWidget(
                    context = context,
                    appWidgetManager = appWidgetManager,
                    appWidgetId = appWidgetId,
                    weather = weather,
                    userPreferences = userPreferences
                )
            }
        }
          /**
         * Maps the OpenWeatherMap icon code to a local drawable resource
         */
        fun getWeatherIconResource(iconCode: String): Int {
            // Return cached icon resource if available
            weatherIconCache[iconCode]?.let { return it }
            
            val resourceId = when (iconCode) {
                "01d" -> R.drawable.ic_sun  // Clear sky day
                "01n" -> R.drawable.ic_sun  // Clear sky night - should be moon icon
                "02d", "02n" -> R.drawable.ic_sun // Few clouds
                "03d", "03n" -> R.drawable.ic_sun // Scattered clouds
                "04d", "04n" -> R.drawable.ic_sun // Broken clouds
                "09d", "09n" -> R.drawable.ic_sun // Shower rain
                "10d", "10n" -> R.drawable.ic_sun // Rain
                "11d", "11n" -> R.drawable.ic_sun // Thunderstorm
                "13d", "13n" -> R.drawable.ic_sun // Snow
                "50d", "50n" -> R.drawable.ic_sun // Mist
                else -> R.drawable.ic_sun // Default
            }
            
            // Cache the result
            weatherIconCache[iconCode] = resourceId
            return resourceId
        }
    }
    
    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray
    ) {
        // Start the update service to fetch fresh weather data
        WeatherWidgetUpdateService.startUpdateService(context)
    }
    
    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        
        // Handle any custom intents here
        if (intent.action == AppWidgetManager.ACTION_APPWIDGET_UPDATE) {
            // Trigger update when widgets are updated
            val appWidgetManager = AppWidgetManager.getInstance(context)
            val appWidgetIds = appWidgetManager.getAppWidgetIds(
                ComponentName(context, WeatherWidget::class.java)
            )
            onUpdate(context, appWidgetManager, appWidgetIds)
        }
    }

    override fun onEnabled(context: Context) {
        // First widget created - set up any initial state
        WeatherWidgetUpdateService.startUpdateService(context)
    }

    override fun onDisabled(context: Context) {
        // Last widget removed - clean up any resources
    }
}

/**
 * Updates a single widget instance with weather data
 */
private fun updateAppWidget(
    context: Context,
    appWidgetManager: AppWidgetManager,
    appWidgetId: Int,
    weather: WeatherResponse? = null,
    userPreferences: UserPreferences? = null
) {
    // Create an Intent to launch MainActivity when clicked
    val intent = Intent(context, MainActivity::class.java)
    val pendingIntent = PendingIntent.getActivity(
        context, appWidgetId, intent, 
        PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
    )
      // Create a refresh intent
    val refreshIntent = Intent(context, WeatherWidgetUpdateService::class.java).apply {
        action = WeatherWidgetUpdateService.ACTION_UPDATE_WIDGETS
    }
    val refreshPendingIntent = PendingIntent.getService(
        context, appWidgetId, refreshIntent,
        PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
    )

    // Construct the RemoteViews object
    val views = RemoteViews(context.packageName, R.layout.weather_widget)
    
    if (weather != null) {
        // Get city from shared preferences if not provided
        val sharedPrefs = context.getSharedPreferences("weather_widget_prefs", Context.MODE_PRIVATE)
        val widgetCity = sharedPrefs.getString("widget_${appWidgetId}_city", "")        // Format the data according to user preferences
        val cityName = weather.name
        val description = weather.weather.firstOrNull()?.description?.let {
            it.replaceFirstChar { char -> if (char.isLowerCase()) char.titlecase(Locale.getDefault()) else char.toString() }
        } ?: ""
          // Format temperature based on user preferences
        val isImperial = userPreferences?.units == Units.IMPERIAL
        val formattedTemp = formatTemperature(weather.main.temp, isImperial)
        
        // Format current time
        val updatedTime = formatTime(System.currentTimeMillis() / 1000)
        
        // Update widget with real data
        views.setTextViewText(R.id.widget_location, cityName)
        views.setTextViewText(R.id.widget_description, description)
        views.setTextViewText(R.id.widget_temperature, formattedTemp)
        views.setTextViewText(R.id.widget_updated_time, "Updated: $updatedTime")
        
        // Set weather icon based on condition
        val iconCode = weather.weather.firstOrNull()?.icon ?: "01d"
        views.setImageViewResource(R.id.widget_weather_icon, WeatherWidget.getWeatherIconResource(iconCode))
    } else {
        // Use placeholder data if no weather is available
        views.setTextViewText(R.id.widget_location, "Loading...")
        views.setTextViewText(R.id.widget_description, "Updating weather")
        views.setTextViewText(R.id.widget_temperature, "--°")
        views.setTextViewText(R.id.widget_updated_time, "Updating...")
    }
      // Set up click intents
    views.setOnClickPendingIntent(R.id.widget_layout, pendingIntent)
    views.setOnClickPendingIntent(R.id.widget_refresh_button, refreshPendingIntent)

    // Instruct the widget manager to update the widget
    appWidgetManager.updateAppWidget(appWidgetId, views)
}
