package com.christopheraldoo.weatherapp

import android.Manifest
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.core.app.ActivityCompat
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import com.christopheraldoo.weatherapp.presentation.screen.WeatherScreen
import com.christopheraldoo.weatherapp.presentation.theme.WeatherMattersTheme
import com.christopheraldoo.weatherapp.presentation.viewmodel.WeatherViewModel
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    
    private val viewModel: WeatherViewModel by viewModels()
    
    // Location permission request
    private val locationPermissionRequest = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        when {
            permissions.getOrDefault(Manifest.permission.ACCESS_FINE_LOCATION, false) -> {
                getCurrentLocationWeather()
            }
            permissions.getOrDefault(Manifest.permission.ACCESS_COARSE_LOCATION, false) -> {
                getCurrentLocationWeather()  
            }
            else -> {
                // Permission denied, use default location (Jakarta, Indonesia)
                viewModel.selectLocation("Jakarta, Indonesia")
                showToast("Using default location: Jakarta, Indonesia")
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Set Compose content
        setContent {
            WeatherMattersTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    WeatherScreen(viewModel = viewModel)
                }
            }
        }
        
        // Request location permission and load weather
        requestLocationPermission()
        
        // Observe weather state for error handling
        observeWeatherState()
    }
    
    
    private fun observeWeatherState() {
        lifecycleScope.launch {
            viewModel.weatherState.collect { result ->
                when (result) {
                    is WeatherResult.Error -> {
                        showToast("Weather Error: ${result.message}")
                    }
                    is WeatherResult.Success -> {
                        showToast("Weather data updated successfully!")
                    }
                    is WeatherResult.Loading -> {
                        // Loading state handled in Compose UI
                    }
                }
            }
        }
    }
    
    private fun requestLocationPermission() {
        when {
            ActivityCompat.checkSelfPermission(
                this,
                Manifest.permission.ACCESS_FINE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED -> {
                getCurrentLocationWeather()
            }
            else -> {
                locationPermissionRequest.launch(
                    arrayOf(
                        Manifest.permission.ACCESS_FINE_LOCATION,
                        Manifest.permission.ACCESS_COARSE_LOCATION
                    )
                )
            }
        }
    }
    
    private fun getCurrentLocationWeather() {
        try {
            val locationManager = getSystemService(LOCATION_SERVICE) as LocationManager
            
            // Check if GPS is enabled
            if (!locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER) &&
                !locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                showToast("Please enable location services")
                // Use default location
                viewModel.selectLocation("Jakarta, Indonesia")
                return
            }
            
            // For simplicity, use default location
            // In production, implement proper location detection
            viewModel.selectLocation("Jakarta, Indonesia")
            
        } catch (e: Exception) {
            showToast("Location error: ${e.message}")
            viewModel.selectLocation("Jakarta, Indonesia")
        }
    }
    
    private fun showToast(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
    }
}