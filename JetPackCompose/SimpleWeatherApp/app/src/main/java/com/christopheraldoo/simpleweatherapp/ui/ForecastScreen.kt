package com.christopheraldoo.simpleweatherapp.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.christopheraldoo.simpleweatherapp.R
import com.christopheraldoo.simpleweatherapp.data.ForecastItem
import com.christopheraldoo.simpleweatherapp.network.WeatherApi
import com.christopheraldoo.simpleweatherapp.viewmodel.ForecastState
import com.christopheraldoo.simpleweatherapp.viewmodel.WeatherViewModel
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ForecastScreen(
    viewModel: WeatherViewModel,
    onBackClick: () -> Unit
) {
    val forecastState by viewModel.forecastState.collectAsState()
    
    val currentHour = Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
    val isDayTime = currentHour in 6..18
    
    // Group forecast items by day
    val groupedForecasts = if (forecastState is ForecastState.Success) {
        val forecastList = (forecastState as ForecastState.Success).forecast.list
        groupForecastsByDay(forecastList)
    } else {
        emptyMap()
    }

    // Root container
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
        // Top app bar
        Column(modifier = Modifier.fillMaxSize()) {
            TopAppBar(
                title = { Text(stringResource(R.string.forecast)) },
                navigationIcon = {
                    IconButton(onClick = onBackClick) {
                        Icon(
                            imageVector = Icons.Default.ArrowBack,
                            contentDescription = "Navigate back"
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.Transparent,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
            
            // Content
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
            ) {
                when (forecastState) {
                    is ForecastState.Loading -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center
                        ) {
                            CircularProgressIndicator(color = Color.White)
                        }
                    }
                    is ForecastState.Error -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(
                                    text = stringResource(R.string.error_occurred),
                                    color = Color.White,
                                    fontSize = 18.sp,
                                    fontWeight = FontWeight.Bold
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = (forecastState as ForecastState.Error).message,
                                    color = Color.White.copy(alpha = 0.8f),
                                    textAlign = TextAlign.Center
                                )
                                Spacer(modifier = Modifier.height(16.dp))
                                Button(
                                    onClick = { viewModel.refresh() },
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Color.White.copy(alpha = 0.2f),
                                        contentColor = Color.White
                                    )
                                ) {
                                    Text(stringResource(R.string.retry))
                                }
                            }
                        }
                    }
                    is ForecastState.Success -> {
                        val forecast = (forecastState as ForecastState.Success).forecast
                        
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(vertical = 8.dp),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            item {
                                Text(
                                    text = stringResource(R.string.forecast_for, forecast.city.name),
                                    fontSize = 20.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White,
                                    modifier = Modifier.padding(vertical = 8.dp)
                                )
                            }
                            
                            // Display each day's forecast
                            groupedForecasts.forEach { (day, forecastItems) ->
                                item {
                                    DayForecast(
                                        day = day,
                                        forecastItems = forecastItems,
                                        isDayTime = isDayTime
                                    )
                                }
                            }
                            
                            // Add space at bottom
                            item { Spacer(modifier = Modifier.height(16.dp)) }
                        }
                    }
                    ForecastState.Empty -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = "No forecast data available",
                                color = Color.White,
                                fontSize = 18.sp
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun DayForecast(
    day: String,
    forecastItems: List<ForecastItem>,
    isDayTime: Boolean
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.1f)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Day header
            Text(
                text = day,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
                modifier = Modifier.padding(bottom = 16.dp)
            )
            
            // Hourly forecast items
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                contentPadding = PaddingValues(vertical = 4.dp)
            ) {
                items(forecastItems) { item ->
                    ForecastItemCard(forecastItem = item)
                }
            }
            
            // Daily summary
            val minTemp = forecastItems.minOfOrNull { it.main.tempMin }?.toInt() ?: 0
            val maxTemp = forecastItems.maxOfOrNull { it.main.tempMax }?.toInt() ?: 0
            val mostFrequentWeather = forecastItems
                .groupBy { it.weather.firstOrNull()?.main ?: "" }
                .maxByOrNull { it.value.size }?.key ?: ""
            
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Min: ${minTemp}° / Max: ${maxTemp}°",
                    fontSize = 16.sp,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
                
                Text(
                    text = mostFrequentWeather,
                    fontSize = 16.sp,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    }
}

@Composable
fun ForecastItemCard(forecastItem: ForecastItem) {
    val time = SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(forecastItem.dt * 1000))
    val temperature = forecastItem.main.temp.toInt()
    val icon = forecastItem.weather.firstOrNull()?.icon ?: ""
    
    Card(
        modifier = Modifier
            .width(100.dp)
            .padding(4.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.15f)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp)
        ) {
            Text(
                text = time,
                fontSize = 14.sp,
                color = Color.White,
                fontWeight = FontWeight.Medium
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            AsyncImage(
                model = "${WeatherApi.ICON_BASE_URL}$icon.png",
                contentDescription = null,
                modifier = Modifier.size(48.dp),
                contentScale = ContentScale.Fit
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "$temperature°",
                fontSize = 18.sp,
                color = Color.White,
                fontWeight = FontWeight.Bold
            )
            
            Text(
                text = forecastItem.weather.firstOrNull()?.main ?: "",
                fontSize = 12.sp,
                color = Color.White.copy(alpha = 0.8f),
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(top = 4.dp)
            )
        }
    }
}

// Helper function to group forecast items by day
@Composable
fun groupForecastsByDay(forecastItems: List<ForecastItem>): Map<String, List<ForecastItem>> {
    val dateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
    val dayFormat = SimpleDateFormat("EEEE, MMM d", Locale.getDefault())
    
    val todayLabel = stringResource(R.string.today)
    val tomorrowLabel = stringResource(R.string.tomorrow)
    
    return forecastItems
        .groupBy { item ->
            val date = Date(item.dt * 1000)
            val dateString = dateFormat.format(date)
            
            // Mark today and tomorrow specially
            val today = dateFormat.format(Date())
            val cal = Calendar.getInstance()
            cal.time = Date()
            cal.add(Calendar.DAY_OF_YEAR, 1)
            val tomorrow = dateFormat.format(cal.time)
            
            when (dateString) {
                today -> todayLabel
                tomorrow -> tomorrowLabel
                else -> dayFormat.format(date)
            }
        }
        .toSortedMap()
}
