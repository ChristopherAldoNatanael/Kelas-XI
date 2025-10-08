package com.christopheraldoo.simpleweatherapp.ui

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.ExperimentalComposeUiApi
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.*
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.christopheraldoo.simpleweatherapp.R
import com.christopheraldoo.simpleweatherapp.data.ForecastItem
import com.christopheraldoo.simpleweatherapp.network.WeatherApi
import com.christopheraldoo.simpleweatherapp.viewmodel.*
import java.text.SimpleDateFormat
import java.util.*

// Function to get weather emoji based on weather condition
fun getWeatherEmoji(weatherMain: String): String {
    return when (weatherMain.lowercase()) {
        "clear" -> "☀️"          // Clear sky
        "clouds" -> "☁️"         // Clouds
        "rain" -> "🌧️"          // Rain
        "drizzle" -> "🌦️"       // Drizzle
        "thunderstorm" -> "⛈️"   // Thunderstorm
        "snow" -> "❄️"           // Snow
        "mist" -> "🌫️"          // Mist/Fog
        "fog" -> "🌫️"           // Fog
        "smoke" -> "💨"          // Smoke
        "haze" -> "🌫️"          // Haze
        "dust" -> "💨"           // Dust
        "sand" -> "💨"           // Sand
        "ash" -> "🌋"            // Volcanic ash
        "squall" -> "💨"         // Squall
        "tornado" -> "🌪️"       // Tornado
        else -> "🌤️"            // Default
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalComposeUiApi::class)
@Composable
fun WeatherScreen(
    viewModel: WeatherViewModel,
    onRequestPermission: () -> Unit = {},
    onNavigateToForecast: () -> Unit = {},
    onNavigateToAlerts: () -> Unit = {}
) {
    val weatherState by viewModel.weatherState.collectAsState()
    val forecastState by viewModel.forecastState.collectAsState()
    val alertsState by viewModel.alertsState.collectAsState()
    val scrollState = rememberScrollState()
    val keyboardController = LocalSoftwareKeyboardController.current
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current
    
    // Search city state
    var searchQuery by remember { mutableStateOf("") }
    val focusRequester = remember { FocusRequester() }
    var isSearchVisible by remember { mutableStateOf(false) }
    
    // Get current hour for dynamic background
    val currentHour = Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
    val isDayTime = currentHour in 6..18
    
    // Animated gradient background based on time of day
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = if (isDayTime) {
                        listOf(
                            Color(0xFF3F96FA),
                            Color(0xFF6EBAFF),
                            Color(0xFF93D3FB)
                        )
                    } else {
                        listOf(
                            Color(0xFF0A1128),
                            Color(0xFF1C3059),
                            Color(0xFF364986)
                        )
                    }
                )
            )
    ) {
        // Main content
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Header Card with Search
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(8.dp)
                    .clip(RoundedCornerShape(24.dp))
                    .shadow(
                        elevation = 8.dp,
                        shape = RoundedCornerShape(24.dp),
                        clip = true,
                        ambientColor = Color.Black.copy(alpha = 0.1f),
                        spotColor = Color.Black.copy(alpha = 0.2f)
                    ),
                colors = CardDefaults.cardColors(
                    containerColor = Color.White.copy(alpha = 0.15f)
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Column(
                    modifier = Modifier
                        .background(
                            brush = Brush.verticalGradient(
                                colors = listOf(
                                    Color.White.copy(alpha = 0.2f),
                                    Color.White.copy(alpha = 0.05f)
                                )
                            )
                        )
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "🌤️",
                            fontSize = 28.sp,
                            color = Color.White,
                            modifier = Modifier.semantics { contentDescription = "Weather icon" }
                        )
                        
                        Text(
                            text = stringResource(R.string.app_name),
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            fontFamily = FontFamily.SansSerif
                        )
                        
                        Row {
                            IconButton(onClick = { viewModel.refresh() }) {
                                Icon(
                                    imageVector = Icons.Default.Refresh,
                                    contentDescription = stringResource(R.string.refresh),
                                    tint = Color.White
                                )
                            }
                              /* Settings removed to fix app errors */
                        }
                    }

                    Text(
                        text = SimpleDateFormat("EEEE, dd MMMM yyyy", Locale.getDefault()).format(Date()),
                        fontSize = 16.sp,
                        color = Color.White.copy(alpha = 0.9f),
                        fontFamily = FontFamily.SansSerif,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                    
                    // Search Bar
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (isSearchVisible) {
                            OutlinedTextField(
                                value = searchQuery,
                                onValueChange = { searchQuery = it },
                                modifier = Modifier
                                    .weight(1f)
                                    .focusRequester(focusRequester)
                                    .shadow(4.dp, RoundedCornerShape(16.dp)),
                                placeholder = { Text(stringResource(R.string.search_city)) },                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = Color.White,
                                    unfocusedTextColor = Color.White,
                                    focusedPlaceholderColor = Color.White.copy(alpha = 0.7f),
                                    unfocusedPlaceholderColor = Color.White.copy(alpha = 0.7f),
                                    focusedBorderColor = Color.White,
                                    unfocusedBorderColor = Color.White.copy(alpha = 0.5f),
                                    focusedContainerColor = Color.White.copy(alpha = 0.1f),
                                    unfocusedContainerColor = Color.White.copy(alpha = 0.1f),
                                    cursorColor = Color.White
                                ),
                                keyboardOptions = KeyboardOptions.Default.copy(imeAction = ImeAction.Search),
                                keyboardActions = KeyboardActions(onSearch = {
                                    if (searchQuery.isNotEmpty()) {
                                        viewModel.searchWeatherByCity(searchQuery)
                                        keyboardController?.hide()
                                        focusManager.clearFocus()
                                        isSearchVisible = false
                                    }
                                }),
                                shape = RoundedCornerShape(16.dp),
                                singleLine = true,
                                trailingIcon = {
                                    IconButton(onClick = { 
                                        if (searchQuery.isNotEmpty()) {
                                            viewModel.searchWeatherByCity(searchQuery)
                                            keyboardController?.hide()
                                            focusManager.clearFocus()
                                            isSearchVisible = false
                                        }
                                    }) {
                                        Text("🔍", fontSize = 18.sp)
                                    }
                                }
                            )
                            
                            Spacer(modifier = Modifier.width(8.dp))
                            
                            IconButton(
                                onClick = { 
                                    isSearchVisible = false
                                    searchQuery = ""
                                    keyboardController?.hide()
                                    focusManager.clearFocus()
                                },
                                modifier = Modifier
                                    .background(
                                        color = Color.White.copy(alpha = 0.2f),
                                        shape = CircleShape
                                    )
                                    .size(40.dp)
                            ) {
                                Text("✖", fontSize = 16.sp, color = Color.White)
                            }
                            
                            LaunchedEffect(isSearchVisible) {
                                if (isSearchVisible) {
                                    focusRequester.requestFocus()
                                }
                            }
                        } else {
                            FilledTonalButton(
                                onClick = { isSearchVisible = true },
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.filledTonalButtonColors(
                                    containerColor = Color.White.copy(alpha = 0.2f),
                                ),
                                contentPadding = PaddingValues(12.dp)
                            ) {
                                Text(
                                    text = "🔍 ${stringResource(R.string.search)}",
                                    color = Color.White,
                                    fontSize = 16.sp
                                )
                            }
                        }
                    }
                }
            }

            // Weather Alerts Banner (if any)
            if (alertsState is AlertsState.Success) {
                val alerts = (alertsState as AlertsState.Success).alertsResponse.alerts
                if (alerts != null && alerts.isNotEmpty()) {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(8.dp)
                            .clip(RoundedCornerShape(16.dp))
                            .clickable { onNavigateToAlerts() },
                        colors = CardDefaults.cardColors(
                            containerColor = Color(0xFFFFC107).copy(alpha = 0.8f)
                        )
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Warning,
                                contentDescription = null,
                                tint = Color.Black
                            )
                            Column(
                                modifier = Modifier.weight(1f)
                            ) {
                                Text(
                                    text = stringResource(R.string.weather_alerts),
                                    fontWeight = FontWeight.Bold,
                                    color = Color.Black
                                )
                                Text(
                                    text = "${alerts.size} ${if (alerts.size > 1) "alerts" else "alert"} active",
                                    color = Color.Black.copy(alpha = 0.8f),
                                    fontSize = 14.sp
                                )
                            }
                            Icon(
                                painter = painterResource(id = R.drawable.ic_arrow_right),
                                contentDescription = "View alerts",
                                tint = Color.Black
                            )
                        }
                    }
                }
            }

            // Current Weather Card
            AnimatedVisibility(
                visible = weatherState is WeatherState.Success,
                enter = fadeIn(animationSpec = tween(500)) + expandVertically(),
                exit = fadeOut(animationSpec = tween(500)) + shrinkVertically()
            ) {
                if (weatherState is WeatherState.Success) {
                    val weather = (weatherState as WeatherState.Success).weather
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(8.dp)
                            .clip(RoundedCornerShape(32.dp))
                            .shadow(
                                elevation = 8.dp,
                                shape = RoundedCornerShape(32.dp),
                                clip = true,
                                ambientColor = Color.Black.copy(alpha = 0.1f),
                                spotColor = Color.Black.copy(alpha = 0.2f)
                            ),
                        colors = CardDefaults.cardColors(
                            containerColor = Color.White.copy(alpha = 0.15f)
                        ),
                        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .background(
                                    brush = Brush.verticalGradient(
                                        colors = if (isDayTime) {
                                            listOf(
                                                Color(0xFF4facfe).copy(alpha = 0.8f),
                                                Color(0xFF00f2fe).copy(alpha = 0.8f)
                                            )
                                        } else {
                                            listOf(
                                                Color(0xFF384785).copy(alpha = 0.8f),
                                                Color(0xFF192B6C).copy(alpha = 0.8f)
                                            )
                                        }
                                    )
                                )
                                .padding(32.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            // Location and Temperature Row
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Column {
                                    Text(
                                        text = "📍 ${weather.name}, ${weather.sys.country}",
                                        fontSize = 20.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = Color.White,
                                        fontFamily = FontFamily.SansSerif
                                    )

                                    val weatherDesc = weather.weather.firstOrNull()?.description ?: ""
                                    val weatherEmoji = getWeatherEmoji(weather.weather.firstOrNull()?.main ?: "")
                                    Text(
                                        text = "$weatherEmoji ${weatherDesc.replaceFirstChar { it.uppercase() }}",
                                        fontSize = 16.sp,
                                        color = Color.White.copy(alpha = 0.9f),
                                        fontFamily = FontFamily.SansSerif,
                                        modifier = Modifier.padding(top = 4.dp)
                                    )
                                }

                                // Weather Icon
                                val iconUrl = weather.weather.firstOrNull()?.icon ?: ""
                                AsyncImage(
                                    model = ImageRequest.Builder(LocalContext.current)
                                        .data("${WeatherApi.ICON_BASE_URL}$iconUrl@4x.png")
                                        .crossfade(true)
                                        .build(),
                                    contentDescription = "Weather icon",
                                    contentScale = ContentScale.FillHeight,
                                    modifier = Modifier.size(72.dp)
                                )
                            }

                            // Temperature display
                            Row(
                                verticalAlignment = Alignment.Bottom,
                                modifier = Modifier.padding(vertical = 24.dp)
                            ) {
                                Text(
                                    text = "${weather.main.temp.toInt()}",
                                    fontSize = 72.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White,
                                    fontFamily = FontFamily.SansSerif
                                )
                                Text(
                                    text = "°C",
                                    fontSize = 32.sp,
                                    fontWeight = FontWeight.Normal,
                                    color = Color.White.copy(alpha = 0.8f),
                                    modifier = Modifier.padding(bottom = 12.dp, start = 4.dp)
                                )
                            }

                            // Feels like text
                            Text(
                                text = "${stringResource(R.string.feels_like)} ${weather.main.feelsLike.toInt()}°C",
                                fontSize = 16.sp,
                                color = Color.White.copy(alpha = 0.9f),
                                fontFamily = FontFamily.SansSerif
                            )

                            Divider(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 24.dp),
                                color = Color.White.copy(alpha = 0.2f),
                                thickness = 1.dp
                            )

                            // Weather details grid
                            Column(
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    WeatherDetailItem(
                                        icon = "💧",
                                        title = stringResource(R.string.humidity),
                                        value = "${weather.main.humidity}%"
                                    )
                                    WeatherDetailItem(
                                        icon = "🌡️",
                                        title = stringResource(R.string.pressure),
                                        value = "${weather.main.pressure} hPa"
                                    )
                                }

                                Spacer(modifier = Modifier.height(16.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    WeatherDetailItem(
                                        icon = "💨",
                                        title = stringResource(R.string.wind),
                                        value = "${weather.wind.speed} m/s"
                                    )
                                    WeatherDetailItem(
                                        icon = "👁️",
                                        title = stringResource(R.string.visibility),
                                        value = "${weather.visibility / 1000} km"
                                    )
                                }

                                Spacer(modifier = Modifier.height(16.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    val sunriseTime = SimpleDateFormat("HH:mm", Locale.getDefault())
                                        .format(Date(weather.sys.sunrise * 1000))
                                    val sunsetTime = SimpleDateFormat("HH:mm", Locale.getDefault())
                                        .format(Date(weather.sys.sunset * 1000))

                                    WeatherDetailItem(
                                        icon = "🌅",
                                        title = stringResource(R.string.sunrise),
                                        value = sunriseTime
                                    )
                                    WeatherDetailItem(
                                        icon = "🌇",
                                        title = stringResource(R.string.sunset),
                                        value = sunsetTime
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Forecast Preview Card (when available)
            if (forecastState is ForecastState.Success) {
                val forecast = (forecastState as ForecastState.Success).forecast
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(8.dp)
                        .clip(RoundedCornerShape(24.dp))
                        .shadow(
                            elevation = 4.dp,
                            shape = RoundedCornerShape(24.dp),
                            clip = true
                        )
                        .clickable { onNavigateToForecast() },
                    colors = CardDefaults.cardColors(
                        containerColor = Color.White.copy(alpha = 0.15f)
                    )
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(bottom = 16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = stringResource(R.string.forecast_for, forecast.city.name),
                                style = MaterialTheme.typography.titleMedium,
                                color = Color.White
                            )
                            TextButton(
                                onClick = { onNavigateToForecast() },
                                colors = ButtonDefaults.textButtonColors(
                                    contentColor = Color.White
                                )
                            ) {
                                Text(stringResource(R.string.five_day_forecast))
                                Icon(
                                    painter = painterResource(id = R.drawable.ic_arrow_right),
                                    contentDescription = null,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }
                        
                        // Show next 5 forecast items
                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            val groupedForecasts = forecast.list
                                .take(8) // Next 24 hours (3-hour intervals)
                            
                            items(groupedForecasts) { forecastItem ->
                                ForecastHourlyItem(forecastItem = forecastItem)
                            }
                        }
                    }
                }
            }

            // Loading indicator
            if (weatherState is WeatherState.Loading) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Color.White)
                }
            }

            // Error State
            if (weatherState is WeatherState.Error) {
                ErrorCard(
                    message = (weatherState as WeatherState.Error).message,
                    onRetry = { viewModel.retry() }
                )
            }

            // Location Permission Request
            if (weatherState is WeatherState.LocationPermissionRequired) {
                PermissionCard(
                    onRequestPermission = onRequestPermission
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
        }
    }
}

@Composable
fun WeatherDetailItem(
    icon: String,
    title: String,
    value: String
) {
    Column(
        horizontalAlignment = Alignment.Start
    ) {
        Text(
            text = "$icon $title",
            fontSize = 14.sp,
            color = Color.White.copy(alpha = 0.7f),
            fontFamily = FontFamily.SansSerif
        )
        Text(
            text = value,
            fontSize = 18.sp,
            fontWeight = FontWeight.SemiBold,
            color = Color.White,
            fontFamily = FontFamily.SansSerif,
            modifier = Modifier.padding(top = 4.dp)
        )
    }
}

@Composable
fun ForecastHourlyItem(forecastItem: ForecastItem) {
    val temp = forecastItem.main.temp.toInt()
    val time = SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(forecastItem.dt * 1000))
    val iconUrl = forecastItem.weather.firstOrNull()?.icon ?: ""
    
    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.15f)
        ),
        modifier = Modifier.width(80.dp)
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .padding(8.dp)
                .fillMaxWidth()
        ) {
            Text(
                text = time,
                fontSize = 14.sp,
                color = Color.White,
                modifier = Modifier.padding(bottom = 4.dp)
            )
            
            AsyncImage(
                model = "${WeatherApi.ICON_BASE_URL}$iconUrl.png",
                contentDescription = "Weather icon for $time",
                modifier = Modifier.size(40.dp),
                contentScale = ContentScale.Fit
            )
            
            Text(
                text = "$temp°",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
                modifier = Modifier.padding(top = 4.dp)
            )
        }
    }
}

@Composable
fun ErrorCard(message: String, onRetry: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = Icons.Outlined.Info,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onErrorContainer,
                modifier = Modifier.size(48.dp)
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = stringResource(R.string.error_occurred),
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onErrorContainer,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                color = MaterialTheme.colorScheme.onErrorContainer,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = 16.dp)
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Button(
                onClick = onRetry,
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.error,
                    contentColor = MaterialTheme.colorScheme.onError
                )
            ) {
                Text(text = stringResource(R.string.retry))
            }
        }
    }
}

@Composable
fun PermissionCard(onRequestPermission: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "📍",
                fontSize = 48.sp,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = stringResource(R.string.location_permission_required),
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onPrimaryContainer,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = stringResource(R.string.permission_explanation),
                color = MaterialTheme.colorScheme.onPrimaryContainer,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = 16.dp)
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Button(
                onClick = onRequestPermission,
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary
                )
            ) {
                Text(text = stringResource(R.string.grant_permission))
            }
        }
    }
}
