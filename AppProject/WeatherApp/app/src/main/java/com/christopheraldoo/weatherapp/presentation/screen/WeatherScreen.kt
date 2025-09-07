package com.christopheraldoo.weatherapp.presentation.screen

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.weatherapp.data.model.ForecastDay
import com.christopheraldoo.weatherapp.data.model.WeatherResponse
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import com.christopheraldoo.weatherapp.presentation.components.*
import com.christopheraldoo.weatherapp.presentation.theme.getWeatherTheme
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.utils.*
import com.christopheraldoo.weatherapp.presentation.viewmodel.WeatherViewModel
import com.christopheraldoo.weatherapp.utils.WeatherUtils

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WeatherScreen(
    viewModel: WeatherViewModel = hiltViewModel()
) {
    val weatherState by viewModel.weatherState.collectAsState()
    val forecastState by viewModel.forecastState.collectAsState()
    val selectedLocation by viewModel.selectedLocation.collectAsState()
    val isSearching by viewModel.isSearching.collectAsState()
    val isFavorite by viewModel.isFavorite.collectAsState()
    
    var showSettings by remember { mutableStateOf(false) }
    var showFavorites by remember { mutableStateOf(false) }
      // Get weather theme based on current weather condition
    val weatherTheme = when (val state = weatherState) {
        is WeatherResult.Success -> getWeatherTheme(state.data.current.condition.text)
        else -> getWeatherTheme(null)
    }
    
    val animatedAlpha by animateFloatAsState(
        targetValue = if (weatherState is WeatherResult.Success) 1f else 0.3f,
        animationSpec = tween(1000),
        label = "content_alpha"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(weatherTheme.background)
    ) {
        // Background gradient overlay
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(brush = weatherTheme.gradient)
        )
        
        Column(
            modifier = Modifier
                .fillMaxSize()
                .alpha(animatedAlpha)
        ) {
            // Enhanced Top App Bar
            EnhancedTopAppBar(
                location = selectedLocation,
                isRefreshing = weatherState is WeatherResult.Loading,
                isFavorite = isFavorite,
                onSearchClick = { viewModel.setSearching(true) },
                onRefreshClick = { viewModel.refreshWeather() },
                onFavoritesClick = { showFavorites = true },
                onToggleFavoriteClick = { viewModel.toggleFavoriteLocation() },
                onSettingsClick = { showSettings = true }
            )
              // Main Content
            when (val state = weatherState) {
                is WeatherResult.Loading -> {
                    LoadingContent()
                }
                is WeatherResult.Success -> {
                    SuccessWeatherContent(
                        weather = state.data,
                        forecast = when (val forecast = forecastState) {
                            is WeatherResult.Success -> forecast.data.forecast?.forecastday ?: emptyList()
                            else -> emptyList()
                        }
                    )
                }
                is WeatherResult.Error -> {
                    ErrorWeatherContent(
                        message = state.message,
                        onRetry = { viewModel.refreshWeather() }
                    )
                }
            }
        }
        
        // Search Overlay
        if (isSearching) {
            SearchOverlay(
                viewModel = viewModel,
                onDismiss = { viewModel.setSearching(false) }
            )        }
        
        // Search Overlay
        if (isSearching) {
            SearchOverlay(
                viewModel = viewModel,
                onDismiss = { viewModel.setSearching(false) }
            )
        }
        
        // Favorites Screen
        if (showFavorites) {
            FavoritesScreen(
                onLocationSelected = { location ->
                    viewModel.selectLocation(location)
                    showFavorites = false
                },
                onBackPressed = { showFavorites = false },
                viewModel = viewModel
            )
        }
          // Settings Screen
        if (showSettings) {
            SettingsScreen(
                onBackClick = { showSettings = false }
            )
        }
    }
}

@Composable
private fun LoadingContent() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            CircularProgressIndicator(
                color = Color.White,
                strokeWidth = 3.dp            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = LocalizationHelper.getCurrentTranslation().loadingWeather,
                style = MaterialTheme.typography.bodyLarge,
                color = Color.White.copy(alpha = 0.95f)
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun WeatherTopAppBar(
    location: String,
    onSearchClick: () -> Unit,
    onRefreshClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.LocationOn,
                    contentDescription = "Location",
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = location,
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f)
                )
            }
            
            Row {
                IconButton(onClick = onSearchClick) {
                    Icon(
                        imageVector = Icons.Default.Search,
                        contentDescription = "Search",
                        tint = Color.White
                    )
                }
                IconButton(onClick = onRefreshClick) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = "Refresh",
                        tint = Color.White
                    )
                }
            }
        }
    }
}

@Composable
private fun LoadingWeatherState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Card(
            modifier = Modifier.padding(32.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(
                containerColor = GlassWhite
            ),
            elevation = CardDefaults.cardElevation(defaultElevation = 12.dp)
        ) {
            Column(
                modifier = Modifier.padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Animated loading icon
                val rotation by rememberInfiniteTransition(label = "loading").animateFloat(
                    initialValue = 0f,
                    targetValue = 360f,
                    animationSpec = infiniteRepeatable(
                        animation = tween(2000, easing = LinearEasing)
                    ),
                    label = "loading_rotation"
                )
                
                Box(
                    modifier = Modifier
                        .size(64.dp)
                        .rotate(rotation),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(
                        color = SunnyYellow,
                        strokeWidth = 4.dp,
                        modifier = Modifier.size(48.dp)
                    )
                    Text(
                        text = "☀️",
                        style = MaterialTheme.typography.headlineMedium
                    )
                }
                
                Spacer(modifier = Modifier.height(24.dp))
                
                Text(
                    text = LocalizationHelper.getCurrentTranslation().loading,
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = "Getting weather data...",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    }
}

@Composable
private fun SuccessWeatherContent(
    weather: WeatherResponse,
    forecast: List<ForecastDay>
) {    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(20.dp)
    ) {
        item {
            // Main weather card
            CinematicWeatherCard(
                weather = weather.current,
                location = weather.location
            )
        }
        
        item {
            // World Clock Card
            WorldClockCard()
        }
          item {
            // Favorites Card with dummy data for now
            FavoritesCard(
                favoriteLocations = emptyList(),
                onLocationClick = { /* TODO: Navigate to location */ },
                onToggleFavorite = { /* TODO: Toggle favorite */ }
            )
        }
          item {
            // Enhanced Weather Details Grid
            WeatherDetailsGrid(weather = weather.current)
        }
        
        if (forecast.isNotEmpty()) {
            item {
                // Enhanced Forecast Section
                EnhancedForecastSection(forecast = forecast)
            }
        }
        
        item {
            Spacer(modifier = Modifier.height(100.dp))
        }
    }
}

@Composable
private fun ErrorWeatherContent(
    message: String,
    onRetry: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Card(
            modifier = Modifier.padding(32.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(
                containerColor = GlassWhite
            ),
            elevation = CardDefaults.cardElevation(defaultElevation = 12.dp)
        ) {
            Column(
                modifier = Modifier.padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Animated error icon
                val pulseAnimation = rememberInfiniteTransition(label = "error_pulse")
                val pulse by pulseAnimation.animateFloat(
                    initialValue = 1f,
                    targetValue = 1.1f,
                    animationSpec = infiniteRepeatable(
                        animation = tween(1000, easing = EaseInOutSine),
                        repeatMode = RepeatMode.Reverse
                    ),
                    label = "error_pulse_value"
                )
                
                Text(
                    text = "⚡",
                    style = MaterialTheme.typography.displayMedium,
                    modifier = Modifier.scale(pulse)
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Text(
                    text = LocalizationHelper.getCurrentTranslation().error,
                    style = MaterialTheme.typography.titleLarge,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold,
                    textAlign = TextAlign.Center
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f),
                    textAlign = TextAlign.Center
                )
                
                Spacer(modifier = Modifier.height(24.dp))
                
                Button(
                    onClick = onRetry,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = SunnyYellow,
                        contentColor = Color.Black
                    ),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = "Retry",
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        "Try Again",
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
    }
}
