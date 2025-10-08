package com.christopheraldoo.simpleweatherapp.widget

import android.app.Activity
import android.appwidget.AppWidgetManager
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.simpleweatherapp.data.UserPreferencesDataStore
import com.christopheraldoo.simpleweatherapp.ui.theme.WeatherProTheme
import kotlinx.coroutines.launch

/**
 * The configuration screen for the weather widget.
 * This allows users to choose which city to display on the widget.
 */
class WeatherWidgetConfigActivity : ComponentActivity() {
    private var appWidgetId = AppWidgetManager.INVALID_APPWIDGET_ID
    private lateinit var userPreferencesDataStore: UserPreferencesDataStore

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Set the result to CANCELED.  This will cause the widget host to cancel
        // out of the widget placement if the user presses the back button.
        setResult(RESULT_CANCELED)
        
        // Find the widget id from the intent.
        appWidgetId = intent?.extras?.getInt(
            AppWidgetManager.EXTRA_APPWIDGET_ID,
            AppWidgetManager.INVALID_APPWIDGET_ID
        ) ?: AppWidgetManager.INVALID_APPWIDGET_ID

        // If this activity was started with an invalid widget ID, finish early
        if (appWidgetId == AppWidgetManager.INVALID_APPWIDGET_ID) {
            finish()
            return
        }
        
        userPreferencesDataStore = UserPreferencesDataStore(this)

        setContent {
            WeatherProTheme {
                WidgetConfigScreen(
                    onSaveClick = { cityName ->
                        saveWidgetPreferences(cityName)
                    }
                )
            }
        }
    }
    
    private fun saveWidgetPreferences(cityName: String) {
        lifecycleScope.launch {
            // Store widget city preference
            val sharedPrefs = getSharedPreferences("weather_widget_prefs", MODE_PRIVATE)
            with(sharedPrefs.edit()) {
                putString("widget_${appWidgetId}_city", cityName)
                apply()
            }
            
            // Update the widget
            val appWidgetManager = AppWidgetManager.getInstance(this@WeatherWidgetConfigActivity)
            WeatherWidgetUpdateService.startUpdateService(this@WeatherWidgetConfigActivity, cityName)
            
            // Make sure we pass back the original appWidgetId
            val resultValue = Intent()
            resultValue.putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
            setResult(RESULT_OK, resultValue)
            finish()
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WidgetConfigScreen(
    onSaveClick: (String) -> Unit
) {
    var cityName by remember { mutableStateOf("") }
    
    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MaterialTheme.colorScheme.background
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Configure Weather Widget",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                fontSize = 24.sp
            )
            
            Spacer(modifier = Modifier.height(32.dp))
            
            Text(
                text = "Enter a city name to display on the widget",
                style = MaterialTheme.typography.bodyLarge,
                modifier = Modifier.padding(bottom = 16.dp)
            )
            
            OutlinedTextField(
                value = cityName,
                onValueChange = { cityName = it },
                label = { Text("City Name") },
                leadingIcon = {
                    Icon(
                        imageVector = Icons.Default.Place,
                        contentDescription = "City"
                    )
                },
                singleLine = true,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 8.dp)
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = { onSaveClick(cityName) },
                enabled = cityName.isNotEmpty(),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Search,
                    contentDescription = "Save"
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Add Widget")
            }
        }
    }
}
