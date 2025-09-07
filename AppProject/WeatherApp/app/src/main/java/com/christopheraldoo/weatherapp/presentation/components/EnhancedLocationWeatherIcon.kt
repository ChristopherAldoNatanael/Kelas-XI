package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.data.model.CurrentWeather
import com.christopheraldoo.weatherapp.data.model.Location
import com.christopheraldoo.weatherapp.presentation.theme.*
import java.time.LocalTime
import java.time.ZoneId
import kotlin.math.*

@Composable
fun EnhancedLocationWeatherIcon(
    weather: CurrentWeather,
    location: Location,
    modifier: Modifier = Modifier,
    size: androidx.compose.ui.unit.Dp = 120.dp
) {    val currentTime = remember {
        try {
            // Use simplified time calculation for API compatibility
            val calendar = java.util.Calendar.getInstance()
            LocalTime.of(calendar.get(java.util.Calendar.HOUR_OF_DAY), calendar.get(java.util.Calendar.MINUTE))
        } catch (e: Exception) {
            LocalTime.now()
        }
    }
    
    val isDay = currentTime.hour in 6..18
    val isNight = !isDay
    val isDawn = currentTime.hour in 5..7
    val isDusk = currentTime.hour in 17..19
    
    // Determine location context for visual adaptation
    val locationContext = getLocationVisualContext(location)
    
    Box(
        modifier = modifier.size(size),
        contentAlignment = Alignment.Center
    ) {
        // Background glow effect
        LocationContextGlow(
            conditionCode = weather.condition.code,
            locationContext = locationContext,
            isDay = isDay,
            size = size
        )
        
        // Main weather visualization
        MainWeatherVisualization(
            conditionCode = weather.condition.code,
            temperature = weather.tempC,
            locationContext = locationContext,
            isDay = isDay,
            isDawn = isDawn,
            isDusk = isDusk,
            isNight = isNight
        )
        
        // Location-specific atmospheric effects
        AtmosphericOverlay(
            weather = weather,
            locationContext = locationContext,
            isDay = isDay
        )
    }
}

@Composable
private fun LocationContextGlow(
    conditionCode: Int,
    locationContext: LocationVisualContext,
    isDay: Boolean,
    size: androidx.compose.ui.unit.Dp
) {
    val glowColor = when {
        isSunnyCondition(conditionCode) -> if (isDay) Color(0xFFFFD700) else Color(0xFFF5F5DC)
        isRainCondition(conditionCode) -> Color(0xFF1E90FF)
        isSnowCondition(conditionCode) -> Color(0xFF87CEEB)
        isStormCondition(conditionCode) -> Color(0xFFFF6347)
        else -> Color.White
    }
    
    val contextColor = when (locationContext) {
        LocationVisualContext.TROPICAL -> Color(0xFF00BCD4)
        LocationVisualContext.DESERT -> Color(0xFFFF9800)
        LocationVisualContext.ARCTIC -> Color(0xFF03DAC6)
        LocationVisualContext.COASTAL -> Color(0xFF2196F3)
        LocationVisualContext.MOUNTAIN -> Color(0xFF4CAF50)
        else -> glowColor
    }
    
    Canvas(modifier = Modifier.size(size)) {
        val center = Offset(size.toPx() / 2f, size.toPx() / 2f)
        val radius = size.toPx() / 4f
        
        // Outer glow
        drawCircle(
            color = contextColor.copy(alpha = 0.2f),
            radius = radius * 1.8f,
            center = center
        )
        
        // Inner glow
        drawCircle(
            color = contextColor.copy(alpha = 0.3f),
            radius = radius * 1.3f,
            center = center
        )
    }
}

@Composable
private fun MainWeatherVisualization(
    conditionCode: Int,
    temperature: Double,
    locationContext: LocationVisualContext,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean
) {
    when {
        isSunnyCondition(conditionCode) -> {
            SunVisualization(locationContext, isDay, isDawn, isDusk, isNight, temperature)
        }
        isRainCondition(conditionCode) -> {
            RainVisualization(locationContext, isDay)
        }
        isSnowCondition(conditionCode) -> {
            SnowVisualization(locationContext, isDay)
        }
        isCloudyCondition(conditionCode) -> {
            CloudVisualization(locationContext, isDay)
        }
        isStormCondition(conditionCode) -> {
            StormVisualization(locationContext, isDay)
        }
        else -> {
            DefaultVisualization(locationContext, isDay)
        }
    }
}

@Composable
private fun SunVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean,
    temperature: Double
) {
    val rotation = rememberInfiniteTransition(label = "sun_rotation")
    val rotationAngle by rotation.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(20000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "rotation"
    )
    
    val sunColor = when {
        isNight -> Color(0xFFF5F5DC) // Moon-like color
        isDawn -> Color(0xFFFFB347) // Dawn orange
        isDusk -> Color(0xFFFF6347) // Sunset red
        else -> when (locationContext) {
            LocationVisualContext.TROPICAL -> Color(0xFFFFD700)
            LocationVisualContext.DESERT -> Color(0xFFFF8C00)
            LocationVisualContext.ARCTIC -> Color(0xFFFFFFE0)
            else -> Color(0xFFFFD700)
        }
    }
    
    Canvas(
        modifier = Modifier
            .fillMaxSize()
            .rotate(rotationAngle)
    ) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val radius = size.minDimension / 8f
        
        // Sun/Moon core
        drawCircle(
            color = sunColor,
            radius = radius,
            center = center
        )
        
        // Rays (only for day)
        if (isDay || isDawn || isDusk) {
            val rayCount = if (locationContext == LocationVisualContext.DESERT) 16 else 12
            for (i in 0 until rayCount) {
                val angle = (i * (360f / rayCount)) * (PI / 180f)
                val startRadius = radius * 1.4f
                val endRadius = radius * 2f
                
                val startX = center.x + cos(angle).toFloat() * startRadius
                val startY = center.y + sin(angle).toFloat() * startRadius
                val endX = center.x + cos(angle).toFloat() * endRadius
                val endY = center.y + sin(angle).toFloat() * endRadius
                
                drawLine(
                    color = sunColor,
                    start = Offset(startX, startY),
                    end = Offset(endX, endY),
                    strokeWidth = 3.dp.toPx()
                )
            }
        }
    }
}

@Composable
private fun RainVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val rainColor = when (locationContext) {
        LocationVisualContext.TROPICAL -> Color(0xFF00BCD4)
        LocationVisualContext.COASTAL -> Color(0xFF2196F3)
        else -> Color(0xFF1E90FF)
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Cloud
        drawCircle(
            color = Color.Gray.copy(alpha = 0.7f),
            radius = size.minDimension / 6f,
            center = center.copy(y = center.y - size.height * 0.1f)
        )
        
        // Rain drops
        val dropCount = when (locationContext) {
            LocationVisualContext.TROPICAL -> 12
            LocationVisualContext.COASTAL -> 10
            else -> 8
        }
        
        for (i in 0 until dropCount) {
            val x = center.x + (i - dropCount/2) * (size.width * 0.08f)
            val y = center.y + size.height * 0.05f
            drawLine(
                color = rainColor,
                start = Offset(x, y),
                end = Offset(x - size.width * 0.01f, y + size.height * 0.15f),
                strokeWidth = 2.dp.toPx()
            )
        }
    }
}

@Composable
private fun SnowVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val pulse = rememberInfiniteTransition(label = "snow_pulse")
    val pulseValue by pulse.animateFloat(
        initialValue = 0.8f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulse"
    )
    
    Canvas(modifier = Modifier.fillMaxSize().scale(pulseValue)) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Snow cloud
        val cloudColor = when (locationContext) {
            LocationVisualContext.ARCTIC -> Color(0xFFF0F8FF)
            LocationVisualContext.MOUNTAIN -> Color(0xFFE6E6FA)
            else -> Color.LightGray
        }
        
        drawCircle(
            color = cloudColor.copy(alpha = 0.8f),
            radius = size.minDimension / 5f,
            center = center.copy(y = center.y - size.height * 0.08f)
        )
        
        // Snowflakes
        val flakeCount = when (locationContext) {
            LocationVisualContext.ARCTIC -> 15
            LocationVisualContext.MOUNTAIN -> 12
            else -> 8
        }
        
        for (i in 0 until flakeCount) {
            val x = center.x + (i - flakeCount/2) * (size.width * 0.1f)
            val y = center.y + size.height * 0.1f + (i % 3) * size.height * 0.05f
            
            // Draw snowflake
            drawCircle(
                color = Color.White,
                radius = 3f + (i % 3) * 1f,
                center = Offset(x, y)
            )
        }
    }
}

@Composable
private fun CloudVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val cloudColor = when {
        !isDay -> Color(0xFF2F2F2F)
        locationContext == LocationVisualContext.COASTAL -> Color(0xFF90A4AE)
        locationContext == LocationVisualContext.MOUNTAIN -> Color(0xFF78909C)
        else -> Color(0xFF708090)
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val baseRadius = size.minDimension / 7f
        
        // Main cloud
        drawCircle(
            color = cloudColor,
            radius = baseRadius,
            center = center
        )
        
        // Left puff
        drawCircle(
            color = cloudColor,
            radius = baseRadius * 0.8f,
            center = center.copy(x = center.x - baseRadius * 0.8f)
        )
        
        // Right puff
        drawCircle(
            color = cloudColor,
            radius = baseRadius * 0.7f,
            center = center.copy(x = center.x + baseRadius * 0.8f)
        )
        
        // Top puff
        drawCircle(
            color = cloudColor,
            radius = baseRadius * 0.6f,
            center = center.copy(y = center.y - baseRadius * 0.6f)
        )
    }
}

@Composable
private fun StormVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val lightning = rememberInfiniteTransition(label = "lightning")
    val lightningFlash by lightning.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = keyframes {
                durationMillis = 2000
                0f at 0
                0f at 1500
                1f at 1600
                0f at 1700
                0f at 2000
            },
            repeatMode = RepeatMode.Restart
        ),
        label = "flash"
    )
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Storm cloud
        drawCircle(
            color = Color(0xFF2F4F4F),
            radius = size.minDimension / 5f,
            center = center.copy(y = center.y - size.height * 0.1f)
        )
        
        // Lightning
        if (lightningFlash > 0.5f) {
            val lightningColor = Color.Yellow.copy(alpha = lightningFlash)
            
            // Zigzag lightning path
            val startX = center.x
            val startY = center.y
            
            drawLine(
                color = lightningColor,
                start = Offset(startX, startY),
                end = Offset(startX - size.width * 0.02f, startY + size.height * 0.08f),
                strokeWidth = 4.dp.toPx()
            )
            
            drawLine(
                color = lightningColor,
                start = Offset(startX - size.width * 0.02f, startY + size.height * 0.08f),
                end = Offset(startX + size.width * 0.03f, startY + size.height * 0.15f),
                strokeWidth = 4.dp.toPx()
            )
            
            drawLine(
                color = lightningColor,
                start = Offset(startX + size.width * 0.03f, startY + size.height * 0.15f),
                end = Offset(startX - size.width * 0.01f, startY + size.height * 0.22f),
                strokeWidth = 4.dp.toPx()
            )
        }
    }
}

@Composable
private fun DefaultVisualization(
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val defaultColor = when (locationContext) {
        LocationVisualContext.TROPICAL -> Color(0xFF4ECDC4)
        LocationVisualContext.DESERT -> Color(0xFFFFB347)
        LocationVisualContext.ARCTIC -> Color(0xFF87CEEB)
        LocationVisualContext.COASTAL -> Color(0xFF81DEEA)
        LocationVisualContext.MOUNTAIN -> Color(0xFF9CCC65)
        else -> Color.White
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        drawCircle(
            color = defaultColor.copy(alpha = 0.8f),
            radius = size.minDimension / 6f,
            center = center
        )
    }
}

@Composable
private fun AtmosphericOverlay(
    weather: CurrentWeather,
    locationContext: LocationVisualContext,
    isDay: Boolean
) {
    val overlayAlpha = when (locationContext) {
        LocationVisualContext.TROPICAL -> if (weather.humidity > 70) 0.15f else 0.05f
        LocationVisualContext.DESERT -> if (weather.tempC > 30 && isDay) 0.1f else 0.02f
        LocationVisualContext.ARCTIC -> if (weather.tempC < 0) 0.2f else 0.05f
        LocationVisualContext.COASTAL -> 0.08f
        LocationVisualContext.MOUNTAIN -> 0.06f
        else -> 0.03f
    }
    
    val overlayColor = when (locationContext) {
        LocationVisualContext.TROPICAL -> Color(0xFF00BCD4)
        LocationVisualContext.DESERT -> Color(0xFFFF9800)
        LocationVisualContext.ARCTIC -> Color(0xFF03DAC6)
        LocationVisualContext.COASTAL -> Color(0xFF2196F3)
        LocationVisualContext.MOUNTAIN -> Color(0xFF4CAF50)
        else -> Color.White
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(overlayColor.copy(alpha = overlayAlpha))
    )
}

// Helper enums and functions
private enum class LocationVisualContext {
    TROPICAL, TEMPERATE, ARCTIC, DESERT, COASTAL, MOUNTAIN, URBAN
}

private fun getLocationVisualContext(location: Location): LocationVisualContext {
    val latitude = abs(location.lat)
    val locationName = location.name.lowercase()
    val country = location.country.lowercase()
    
    return when {
        latitude < 23.5 -> LocationVisualContext.TROPICAL
        latitude > 66.5 -> LocationVisualContext.ARCTIC
        locationName.contains("desert") || country.contains("saudi") || country.contains("uae") -> LocationVisualContext.DESERT
        locationName.contains("mountain") || locationName.contains("hill") -> LocationVisualContext.MOUNTAIN
        locationName.contains("coast") || locationName.contains("beach") || country.contains("island") -> LocationVisualContext.COASTAL
        locationName.contains("city") || locationName.contains("urban") -> LocationVisualContext.URBAN
        else -> LocationVisualContext.TEMPERATE
    }
}

// Weather condition helpers
private fun isSunnyCondition(code: Int): Boolean = code == 1000
private fun isRainCondition(code: Int): Boolean = code in 1063..1201 || code in 1240..1246
private fun isSnowCondition(code: Int): Boolean = code in 1066..1117 || code in 1204..1237 || code in 1249..1264
private fun isCloudyCondition(code: Int): Boolean = code in 1003..1030
private fun isStormCondition(code: Int): Boolean = code == 1087 || code in 1273..1282
