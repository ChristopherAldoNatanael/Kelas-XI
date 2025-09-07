package com.christopheraldoo.weatherapp.presentation.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import com.christopheraldoo.weatherapp.presentation.screen.SplashScreen
import com.christopheraldoo.weatherapp.presentation.screen.WeatherScreen

@Composable
fun WeatherNavigation() {
    var showSplash by remember { mutableStateOf(true) }
    
    if (showSplash) {
        SplashScreen {
            showSplash = false
        }
    } else {
        WeatherScreen()
    }
}
