package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.data.model.CurrentWeather
import com.christopheraldoo.weatherapp.data.model.Location
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.utils.LocalizationHelper
import com.christopheraldoo.weatherapp.utils.WeatherUtils
import kotlin.math.sin

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CinematicWeatherCard(
    weather: CurrentWeather,
    location: Location,
    modifier: Modifier = Modifier
) {
    val configuration = LocalConfiguration.current
    val isLandscape = configuration.screenWidthDp > configuration.screenHeightDp
    
    // Get weather theme based on current conditions
    val weatherTheme = remember(weather) {
        getWeatherTheme(weather.condition.text)
    }
      // Breathing animation for the card
    val breathingAnimation = rememberInfiniteTransition(label = "breathing")
    val breathingScale by breathingAnimation.animateFloat(
        initialValue = 1f,
        targetValue = 1.02f,
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "breathing_scale"
    )
    
    Card(
        modifier = modifier
            .fillMaxWidth()
            .animateContentSize()
            .scale(breathingScale),
        shape = RoundedCornerShape(32.dp),
        colors = CardDefaults.cardColors(
            containerColor = weatherTheme.cardBackground
        ),
        elevation = CardDefaults.cardElevation(
            defaultElevation = 16.dp
        )
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(brush = weatherTheme.gradient)
        ) {
            
            if (isLandscape) {
                LandscapeWeatherContent(weather, location)
            } else {
                PortraitWeatherContent(weather, location)
            }
        }
    }
}

@Composable
private fun AnimatedBackgroundOverlay(conditionCode: Int) {
    val shimmerAnimation = rememberInfiniteTransition(label = "shimmer")
    val shimmerOffset by shimmerAnimation.animateFloat(
        initialValue = -1f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "shimmer_offset"
    )
    
    val gradientColors = when (conditionCode) {
        in 1000..1003 -> listOf(
            Color(0x1AFFD700),
            Color(0x0AFFD700),
            Color(0x1AFFA500)
        )
        in 1006..1009 -> listOf(
            Color(0x15B0BEC5),
            Color(0x08B0BEC5),
            Color(0x15CFD8DC)
        )
        else -> listOf(
            Color(0x10FFFFFF),
            Color(0x05FFFFFF),
            Color(0x10FFFFFF)
        )
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.horizontalGradient(
                    colors = gradientColors,
                    startX = shimmerOffset * 200f,
                    endX = shimmerOffset * 200f + 400f
                )
            )
    )
}

@Composable
private fun PortraitWeatherContent(
    weather: CurrentWeather,
    location: Location
) {
    // Get weather theme
    val weatherTheme = getWeatherTheme(weather.condition.text)
    
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Time and Date
        LocationTimeInfo(
            locationName = location.name,
            timezone = location.tzId
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Weather Icon
        WeatherIcon(
            iconUrl = weather.condition.icon,
            contentDescription = weather.condition.text,
            size = 120.dp
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Main Temperature with Animation
        AnimatedTemperature(weather.tempC)
        
        Spacer(modifier = Modifier.height(12.dp))
          // Weather Description
        Text(
            text = LocalizationHelper.translateWeatherCondition(weather.condition.text),
            style = MaterialTheme.typography.titleLarge,
            color = weatherTheme.textPrimary,
            fontWeight = FontWeight.Medium,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        // Feels Like
        Text(
            text = "${LocalizationHelper.getCurrentTranslation().feelsLike} ${weather.feelslikeC.toInt()}°",
            style = MaterialTheme.typography.bodyLarge,
            color = weatherTheme.textSecondary
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Quick Stats Row
        QuickStatsRow(weather)
        
        Spacer(modifier = Modifier.height(20.dp))
        
        // Temperature Range
        TemperatureRangeCard(weather)
    }
}

@Composable
private fun LandscapeWeatherContent(
    weather: CurrentWeather,
    location: Location
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(24.dp),        verticalAlignment = Alignment.CenterVertically
    ) {
        // Left side - Main weather info
        Column(
            modifier = Modifier.weight(1f),
            horizontalAlignment = Alignment.CenterHorizontally        ) {            WeatherIcon(
                iconUrl = weather.condition.icon,
                contentDescription = weather.condition.text,
                size = 100.dp
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            AnimatedTemperature(weather.tempC, fontSize = 64.sp)
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = LocalizationHelper.translateWeatherCondition(weather.condition.text),
                style = MaterialTheme.typography.titleMedium,
                color = Color.White.copy(alpha = 0.98f),
                textAlign = TextAlign.Center
            )
        }
        
        // Right side - Details
        Column(
            modifier = Modifier
                .weight(1f)
                .padding(start = 24.dp)
        ) {            LocationTimeInfo(
                locationName = location.name,
                timezone = location.tzId
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            QuickStatsColumn(weather)
            
            Spacer(modifier = Modifier.height(16.dp))
            
            TemperatureRangeCard(weather)
        }    }
}

@Composable
private fun AnimatedWeatherIcon(
    conditionCode: Int,
    size: androidx.compose.ui.unit.Dp = 120.dp
) {
    val rotationAnimation = rememberInfiniteTransition(label = "icon_rotation")
    val rotation by rotationAnimation.animateFloat(
        initialValue = -5f,
        targetValue = 5f,
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "icon_rotation_value"
    )
    
    val scaleAnimation = rememberInfiniteTransition(label = "icon_scale")
    val scale by scaleAnimation.animateFloat(
        initialValue = 1f,
        targetValue = 1.05f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "icon_scale_value"
    )
    
    Box(
        modifier = Modifier
            .size(size)
            .scale(scale),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = WeatherUtils.getWeatherIcon(conditionCode),
            style = MaterialTheme.typography.displayLarge,
            fontSize = (size.value * 0.8f).sp
        )
    }
}

@Composable
private fun AnimatedTemperature(
    temperature: Double,
    fontSize: androidx.compose.ui.unit.TextUnit = 72.sp
) {
    var displayedTemp by remember { mutableIntStateOf(0) }
    val targetTemp = temperature.toInt()
    
    LaunchedEffect(targetTemp) {
        val animationDuration = 1000
        val steps = 20
        val stepDuration = animationDuration / steps
        val stepSize = (targetTemp - displayedTemp) / steps.toFloat()
        
        repeat(steps) {
            displayedTemp += stepSize.toInt()
            kotlinx.coroutines.delay(stepDuration.toLong())
        }
        displayedTemp = targetTemp
    }
    
    Text(
        text = "${displayedTemp}°",
        style = MaterialTheme.typography.displayLarge,
        fontSize = fontSize,
        fontWeight = FontWeight.Bold,
        color = Color.White
    )
}

@Composable
private fun QuickStatsRow(weather: CurrentWeather) {
    Row(
        horizontalArrangement = Arrangement.spacedBy(24.dp)
    ) {
        QuickStatItem(
            icon = Icons.Default.WaterDrop,
            label = LocalizationHelper.getCurrentTranslation().humidity,
            value = "${weather.humidity}%"
        )
        QuickStatItem(
            icon = Icons.Default.Air,
            label = LocalizationHelper.getCurrentTranslation().wind,
            value = "${weather.windKph.toInt()}"
        )
        QuickStatItem(
            icon = Icons.Default.WbSunny,
            label = LocalizationHelper.getCurrentTranslation().uvIndex,
            value = weather.uv.toInt().toString()
        )
    }
}

@Composable
private fun QuickStatsColumn(weather: CurrentWeather) {
    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        QuickStatItem(
            icon = Icons.Default.WaterDrop,
            label = LocalizationHelper.getCurrentTranslation().humidity,
            value = "${weather.humidity}%",
            isVertical = false
        )
        QuickStatItem(
            icon = Icons.Default.Air,
            label = LocalizationHelper.getCurrentTranslation().wind,
            value = "${weather.windKph.toInt()} km/h",
            isVertical = false
        )
        QuickStatItem(
            icon = Icons.Default.WbSunny,
            label = LocalizationHelper.getCurrentTranslation().uvIndex,
            value = weather.uv.toInt().toString(),
            isVertical = false
        )
    }
}

@Composable
private fun QuickStatItem(
    icon: ImageVector,
    label: String,
    value: String,
    isVertical: Boolean = true
) {
    if (isVertical) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = Color.White,
                modifier = Modifier.size(24.dp)
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.8f),
                fontSize = 12.sp
            )
            Text(
                text = value,
                style = MaterialTheme.typography.titleSmall,
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    } else {
        Row(
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = Color.White,
                modifier = Modifier.size(20.dp)
            )
            Spacer(modifier = Modifier.width(8.dp))
            Column {
                Text(
                    text = label,
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 12.sp
                )
                Text(
                    text = value,
                    style = MaterialTheme.typography.titleSmall,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TemperatureRangeCard(weather: CurrentWeather) {
    Card(
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.25f)
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    imageVector = Icons.Default.KeyboardArrowUp,
                    contentDescription = "High",
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    text = LocalizationHelper.getCurrentTranslation().high,
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 12.sp
                )
                Text(
                    text = "${weather.tempC.toInt() + 2}°",
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
            }
            
            Box(
                modifier = Modifier
                    .width(1.dp)
                    .height(40.dp)
                    .background(Color.White.copy(alpha = 0.35f))
            )
            
            Column(
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    imageVector = Icons.Default.KeyboardArrowDown,
                    contentDescription = "Low",
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    text = LocalizationHelper.getCurrentTranslation().low,
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 12.sp
                )
                Text(
                    text = "${weather.tempC.toInt() - 5}°",
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}
