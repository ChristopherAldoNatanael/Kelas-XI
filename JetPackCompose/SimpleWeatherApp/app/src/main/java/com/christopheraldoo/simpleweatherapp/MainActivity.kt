package com.christopheraldoo.simpleweatherapp

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.core.app.ActivityCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.core.view.WindowCompat
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.lifecycleScope
import androidx.navigation.compose.rememberNavController
import com.christopheraldoo.simpleweatherapp.data.ThemeMode
import com.christopheraldoo.simpleweatherapp.navigation.AppNavigation
import com.christopheraldoo.simpleweatherapp.ui.theme.WeatherProTheme
import com.christopheraldoo.simpleweatherapp.viewmodel.SettingsState
import com.christopheraldoo.simpleweatherapp.viewmodel.WeatherViewModel
import com.google.accompanist.systemuicontroller.rememberSystemUiController
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.util.Calendar

class MainActivity : ComponentActivity() {

    private lateinit var viewModel: WeatherViewModel

    // Permission launcher
    private val requestPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { permissions ->
            val granted = permissions.values.all { it }
            if (granted) {
                viewModel.getLocationAndWeather()
            } else {
                Toast.makeText(this, getString(R.string.location_permission_required), Toast.LENGTH_LONG).show()
            }
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        
        // Keep splash screen visible while initialization
        splashScreen.setKeepOnScreenCondition { false }
        
        // Enable edge-to-edge design (full screen)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.dark(Color.Transparent.toArgb()),
            navigationBarStyle = SystemBarStyle.dark(Color.Transparent.toArgb())
        )
        
        // Make system UI transparent
        WindowCompat.setDecorFitsSystemWindows(window, false)

        // Initialize ViewModel
        viewModel = ViewModelProvider(this)[WeatherViewModel::class.java]
        
        // Add a small delay to make app feel smoother
        var isReady by mutableStateOf(false)
        lifecycleScope.launch {
            delay(500)
            isReady = true
        }

        setContent {
            val navController = rememberNavController()
            val systemUiController = rememberSystemUiController()
            val settingsState by viewModel.settingsState.collectAsState()
            
            // Determine theme mode
            val currentHour = remember { Calendar.getInstance().get(Calendar.HOUR_OF_DAY) }
            val isDayTime = remember { currentHour in 6..18 }
            
            val darkTheme = when {
                settingsState is SettingsState.Success -> {
                    val preferences = (settingsState as SettingsState.Success).userPreferences
                    when (preferences.themeMode) {
                        ThemeMode.SYSTEM -> isSystemInDarkTheme()
                        ThemeMode.LIGHT -> false
                        ThemeMode.DARK -> true
                        ThemeMode.AUTO_TIME -> !isDayTime
                    }
                }
                else -> !isDayTime
            }
            
            // Make system bars transparent with appropriate content color
            LaunchedEffect(darkTheme) {
                systemUiController.setSystemBarsColor(
                    color = Color.Transparent,
                    darkIcons = !darkTheme
                )
            }
            
            WeatherProTheme(darkTheme = darkTheme) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    if (isReady) {
                        AppNavigation(
                            navController = navController,
                            viewModel = viewModel,
                            onRequestPermission = {
                                requestPermissionsAndGetWeather()
                            }
                        )
                    }
                }
            }
        }

        // Schedule widget updates
        updateWidgets()
    }
    
    private fun updateWidgets() {
        // Update any active widgets with fresh weather data
        com.christopheraldoo.simpleweatherapp.widget.WeatherWidget.scheduleWidgetUpdates(this)
    }

    private fun requestPermissionsAndGetWeather() {
        when {
            ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
            ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED -> {
                // Permissions already granted
                viewModel.getLocationAndWeather()
            }
            else -> {
                // Request permissions
                requestPermissionLauncher.launch(
                    arrayOf(
                        Manifest.permission.ACCESS_FINE_LOCATION,
                        Manifest.permission.ACCESS_COARSE_LOCATION
                    )
                )
            }
        }
    }
    
    private fun isSystemInDarkTheme(): Boolean {
        return resources.configuration.uiMode and
                android.content.res.Configuration.UI_MODE_NIGHT_MASK ==
                android.content.res.Configuration.UI_MODE_NIGHT_YES
    }
}
