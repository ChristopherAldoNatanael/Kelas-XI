package com.christopheraldoo.simpleweatherapp.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.christopheraldoo.simpleweatherapp.ui.AlertsScreen
import com.christopheraldoo.simpleweatherapp.ui.ForecastScreen
import com.christopheraldoo.simpleweatherapp.ui.WeatherScreen
import com.christopheraldoo.simpleweatherapp.viewmodel.WeatherViewModel

object NavigationRoutes {
    const val WEATHER = "weather"
    const val FORECAST = "forecast"
    const val ALERTS = "alerts"
}

@Composable
fun AppNavigation(
    navController: NavHostController,
    viewModel: WeatherViewModel,
    onRequestPermission: () -> Unit
) {
    NavHost(
        navController = navController,
        startDestination = NavigationRoutes.WEATHER
    ) {
        composable(NavigationRoutes.WEATHER) {
            WeatherScreen(
                viewModel = viewModel,
                onRequestPermission = onRequestPermission,
                onNavigateToForecast = {
                    navController.navigate(NavigationRoutes.FORECAST)
                },                onNavigateToAlerts = {
                    navController.navigate(NavigationRoutes.ALERTS)
                }
            )
        }
        
        composable(NavigationRoutes.FORECAST) {
            ForecastScreen(
                viewModel = viewModel,
                onBackClick = {
                    navController.navigateUp()
                }
            )
        }
        
        composable(NavigationRoutes.ALERTS) {
            AlertsScreen(
                viewModel = viewModel,
                onBackClick = {
                    navController.navigateUp()
                }
            )
        }
    }
}
