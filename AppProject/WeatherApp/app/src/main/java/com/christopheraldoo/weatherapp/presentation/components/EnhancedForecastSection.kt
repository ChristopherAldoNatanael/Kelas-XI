package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.ripple.rememberRipple
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.data.model.ForecastDay
import com.christopheraldoo.weatherapp.data.model.Hour
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.utils.LocalizationHelper
import com.christopheraldoo.weatherapp.utils.WeatherUtils
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EnhancedForecastSection(forecast: List<ForecastDay>) {
    var selectedDayIndex by remember { mutableIntStateOf(0) }
    var showHourlyForecast by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .animateContentSize(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp)
        ) {
            // Header with toggle button
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = LocalizationHelper.getCurrentTranslation().forecast,
                    style = MaterialTheme.typography.titleLarge,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = if (showHourlyForecast) "24h" else "7d",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.White.copy(alpha = 0.8f),
                        fontSize = 12.sp
                    )
                    
                    Spacer(modifier = Modifier.width(8.dp))
                    
                    Switch(
                        checked = showHourlyForecast,
                        onCheckedChange = { showHourlyForecast = it },
                        colors = SwitchDefaults.colors(
                            checkedThumbColor = SunnyYellow,
                            checkedTrackColor = SunnyYellow.copy(alpha = 0.3f),
                            uncheckedThumbColor = Color.White,
                            uncheckedTrackColor = Color.White.copy(alpha = 0.3f)
                        )
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
              if (showHourlyForecast) {
                // Hourly Forecast for selected day
                HourlyForecast(
                    hours = if (forecast.isNotEmpty() && selectedDayIndex < forecast.size) {
                        forecast[selectedDayIndex].hour ?: emptyList()
                    } else {
                        emptyList()
                    }
                )
            } else {
                // 7-Day Forecast
                SevenDayForecast(
                    forecast = forecast,
                    selectedIndex = selectedDayIndex,
                    onDaySelected = { selectedDayIndex = it }
                )
            }
        }
    }
}

@Composable
private fun SevenDayForecast(
    forecast: List<ForecastDay>,
    selectedIndex: Int,
    onDaySelected: (Int) -> Unit
) {
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        itemsIndexed(forecast) { index, day ->
            SevenDayForecastCard(
                day = day,
                isSelected = index == selectedIndex,
                isToday = index == 0,
                onClick = { onDaySelected(index) }
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SevenDayForecastCard(
    day: ForecastDay,
    isSelected: Boolean,
    isToday: Boolean,
    onClick: () -> Unit
) {
    val scale by animateFloatAsState(
        targetValue = if (isSelected) 1.05f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "forecast_card_scale"
    )
    
    Card(
        modifier = Modifier
            .width(140.dp)
            .height(200.dp)
            .scale(scale)
            .clickable(
                indication = rememberRipple(),
                interactionSource = remember { MutableInteractionSource() }
            ) { onClick() },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (isSelected) {
                Color.White.copy(alpha = 0.25f)
            } else {
                Color.White.copy(alpha = 0.15f)
            }
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceEvenly
        ) {
            // Day label
            Text(
                text = if (isToday) {
                    LocalizationHelper.getCurrentTranslation().today
                } else {
                    formatDayOfWeek(day.date)
                },
                style = MaterialTheme.typography.bodyMedium,
                color = if (isSelected) Color.White else Color.White.copy(alpha = 0.9f),
                fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
                textAlign = TextAlign.Center
            )
            
            // Weather icon with animation
            AnimatedWeatherIcon(
                conditionCode = day.day.condition.code,
                size = 40.dp
            )
            
            // Temperature high/low
            Column(
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "${day.day.maxtempC.toInt()}°",
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${day.day.mintempC.toInt()}°",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.7f)
                )
            }
              // Rain chance
            if (day.day.dailyChanceOfRain > 0) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.WaterDrop,
                        contentDescription = "Rain chance",
                        tint = RainyBlue,
                        modifier = Modifier.size(12.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = "${day.day.dailyChanceOfRain}%",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.8f),
                        fontSize = 10.sp
                    )
                }
            }
        }
    }
}

@Composable
private fun HourlyForecast(hours: List<Hour>) {
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(hours.take(24)) { hour ->
            HourlyForecastCard(hour = hour)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HourlyForecastCard(hour: Hour) {
    val isCurrentHour = remember {
        try {
            val currentHour = Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
            val parts = hour.time.split(" ")
            if (parts.size > 1) {
                val hourTime = SimpleDateFormat("HH", Locale.getDefault()).parse(parts[1])
                val cardHour = Calendar.getInstance().apply { 
                    time = hourTime ?: Date() 
                }.get(Calendar.HOUR_OF_DAY)
                currentHour == cardHour
            } else {
                false
            }
        } catch (e: Exception) {
            false
        }
    }
    
    Card(
        modifier = Modifier
            .width(80.dp)
            .height(120.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (isCurrentHour) {
                SunnyYellow.copy(alpha = 0.3f)
            } else {
                Color.White.copy(alpha = 0.15f)
            }
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceEvenly
        ) {
            // Time
            Text(
                text = formatHourTime(hour.time),
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.9f),
                fontSize = 11.sp
            )
            
            // Weather icon
            Text(
                text = WeatherUtils.getWeatherIcon(hour.condition.code),
                style = MaterialTheme.typography.headlineMedium,
                fontSize = 24.sp
            )
            
            // Temperature
            Text(
                text = "${hour.tempC.toInt()}°",
                style = MaterialTheme.typography.titleSmall,
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
            
            // Rain chance
            if (hour.chanceOfRain > 0) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.WaterDrop,
                        contentDescription = "Rain",
                        tint = RainyBlue,
                        modifier = Modifier.size(10.dp)
                    )
                    Spacer(modifier = Modifier.width(2.dp))
                    Text(
                        text = "${hour.chanceOfRain}%",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.8f),
                        fontSize = 9.sp
                    )
                }
            }
        }
    }
}

@Composable
private fun AnimatedWeatherIcon(
    conditionCode: Int,
    size: androidx.compose.ui.unit.Dp
) {
    val rotationAnimation = rememberInfiniteTransition(label = "icon_rotation")
    val rotation by rotationAnimation.animateFloat(
        initialValue = -2f,
        targetValue = 2f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "icon_rotation_value"
    )
    
    Text(
        text = WeatherUtils.getWeatherIcon(conditionCode),
        style = MaterialTheme.typography.headlineLarge,
        fontSize = (size.value * 0.8f).sp,
        modifier = Modifier.scale(1f + rotation * 0.05f)
    )
}

private fun formatDayOfWeek(dateString: String): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
        val outputFormat = SimpleDateFormat("EEE", Locale.getDefault())
        val date = inputFormat.parse(dateString)
        outputFormat.format(date ?: Date())
    } catch (e: Exception) {
        dateString.take(3)
    }
}

private fun formatHourTime(timeString: String): String {
    return try {
        val parts = timeString.split(" ")
        if (parts.size > 1) {
            val time = parts[1]
            val hourMinute = time.split(":")
            if (hourMinute.size >= 2) {
                "${hourMinute[0]}:${hourMinute[1]}"
            } else {
                time
            }
        } else {
            timeString
        }
    } catch (e: Exception) {
        timeString
    }
}
