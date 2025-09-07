package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
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
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.data.model.CurrentWeather
import com.christopheraldoo.weatherapp.data.model.Location
import com.christopheraldoo.weatherapp.presentation.theme.*
import kotlinx.coroutines.delay
import java.time.LocalTime
import java.time.ZoneId
import kotlin.math.*
import kotlin.random.Random

@Composable
fun DynamicLocationWeatherCard(
    weather: CurrentWeather,
    location: Location,
    modifier: Modifier = Modifier
) {    val currentTime = remember {
        try {
            // Use simplified time calculation for API compatibility
            val calendar = java.util.Calendar.getInstance()
            LocalTime.of(calendar.get(java.util.Calendar.HOUR_OF_DAY), calendar.get(java.util.Calendar.MINUTE))
        } catch (e: Exception) {
            LocalTime.now()
        }
    }
    
    val timeOfDay = getTimeOfDay(currentTime)
    val locationContext = getLocationContext(location)
    
    Card(
        modifier = modifier
            .fillMaxWidth()
            .height(280.dp)
            .animateContentSize(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.Transparent
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Box(
            modifier = Modifier.fillMaxSize()
        ) {
            // Dynamic background with location context
            DynamicLocationBackground(
                weather = weather,
                location = location,
                timeOfDay = timeOfDay,
                locationContext = locationContext
            )
            
            // Weather content overlay
            WeatherContentOverlay(
                weather = weather,
                location = location,
                timeOfDay = timeOfDay,
                locationContext = locationContext
            )
            
            // Interactive weather particles
            InteractiveWeatherParticles(
                weather = weather,
                timeOfDay = timeOfDay,
                locationContext = locationContext
            )
        }
    }
}

@Composable
private fun DynamicLocationBackground(
    weather: CurrentWeather,
    location: Location,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
) {
    val backgroundGradient = getDynamicBackgroundGradient(
        weather = weather,
        timeOfDay = timeOfDay,
        locationContext = locationContext
    )
    
    // Animated gradient shift
    val infiniteTransition = rememberInfiniteTransition(label = "background")
    val gradientOffset by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(8000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "gradient_offset"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.sweepGradient(
                    colors = backgroundGradient,
                    center = Offset(gradientOffset * 1000f, gradientOffset * 800f)
                )
            )
    )
    
    // Add location-specific atmospheric overlay
    LocationAtmosphericOverlay(
        locationContext = locationContext,
        weather = weather,
        timeOfDay = timeOfDay
    )
}

@Composable
private fun WeatherContentOverlay(
    weather: CurrentWeather,
    location: Location,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        // Dynamic location-aware weather icon
        DynamicWeatherIcon(
            weather = weather,
            location = location,
            timeOfDay = timeOfDay,
            locationContext = locationContext
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Temperature with location context
        LocationAwareTemperature(
            temperature = weather.tempC,
            locationContext = locationContext
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        // Weather description with local context
        Text(
            text = getLocalizedWeatherDescription(
                condition = weather.condition.text,
                locationContext = locationContext
            ),
            style = MaterialTheme.typography.titleMedium,
            color = Color.White.copy(alpha = 0.9f),
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Location-specific weather insights
        LocationWeatherInsights(
            weather = weather,
            location = location,
            locationContext = locationContext
        )
    }
}

@Composable
private fun DynamicWeatherIcon(
    weather: CurrentWeather,
    location: Location,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
) {
    val iconSize = 80.dp
    val conditionCode = weather.condition.code
    
    // Multiple animation layers
    val rotationAnimation = rememberInfiniteTransition(label = "icon_rotation")
    val rotation by rotationAnimation.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(15000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "rotation_value"
    )
    
    val pulseAnimation = rememberInfiniteTransition(label = "icon_pulse")
    val pulse by pulseAnimation.animateFloat(
        initialValue = 0.9f,
        targetValue = 1.1f,
        animationSpec = infiniteRepeatable(
            animation = tween(2500, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulse_value"
    )
    
    Box(
        modifier = Modifier
            .size(iconSize)
            .scale(pulse),
        contentAlignment = Alignment.Center
    ) {
        // Background glow effect
        WeatherIconGlow(
            conditionCode = conditionCode,
            timeOfDay = timeOfDay,
            locationContext = locationContext
        )
        
        // Main weather visualization
        when {
            isRainCondition(conditionCode) -> RainWeatherVisualization(locationContext, timeOfDay)
            isSnowCondition(conditionCode) -> SnowWeatherVisualization(locationContext, timeOfDay)
            isSunnyCondition(conditionCode) -> SunWeatherVisualization(locationContext, timeOfDay, rotation)
            isCloudyCondition(conditionCode) -> CloudWeatherVisualization(locationContext, timeOfDay)
            isStormCondition(conditionCode) -> StormWeatherVisualization(locationContext, timeOfDay)
            else -> DefaultWeatherVisualization(locationContext, timeOfDay)
        }
    }
}

@Composable
private fun WeatherIconGlow(
    conditionCode: Int,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
) {
    val glowColor = getWeatherGlowColor(conditionCode, timeOfDay, locationContext)
    
    Canvas(
        modifier = Modifier.fillMaxSize()
    ) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val radius = size.minDimension / 3f
        
        drawCircle(
            color = glowColor.copy(alpha = 0.3f),
            radius = radius * 1.5f,
            center = center
        )
        drawCircle(
            color = glowColor.copy(alpha = 0.2f),
            radius = radius * 2f,
            center = center
        )
    }
}

@Composable
private fun SunWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay,
    rotation: Float
) {
    Canvas(
        modifier = Modifier
            .fillMaxSize()
            .rotate(rotation)
    ) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val radius = size.minDimension / 6f
        
        val sunColor = when (timeOfDay) {
            TimeOfDay.DAWN -> Color(0xFFFFB347)
            TimeOfDay.DUSK -> Color(0xFFFF6B47)
            TimeOfDay.NIGHT -> Color(0xFFF5F5DC)
            else -> when (locationContext) {
                LocationContext.TROPICAL -> Color(0xFFFFD700)
                LocationContext.DESERT -> Color(0xFFFF8C00)
                LocationContext.ARCTIC -> Color(0xFFFFFFE0)
                else -> Color(0xFFFFD700)
            }
        }
        
        // Sun core
        drawCircle(
            color = sunColor,
            radius = radius,
            center = center
        )
        
        // Sun rays
        for (i in 0..11) {
            val angle = (i * 30f) * (PI / 180f)
            val startX = center.x + cos(angle).toFloat() * (radius * 1.5f)
            val startY = center.y + sin(angle).toFloat() * (radius * 1.5f)
            val endX = center.x + cos(angle).toFloat() * (radius * 2.2f)
            val endY = center.y + sin(angle).toFloat() * (radius * 2.2f)
            
            drawLine(
                color = sunColor,
                start = Offset(startX, startY),
                end = Offset(endX, endY),
                strokeWidth = 4.dp.toPx()
            )
        }
    }
}

@Composable
private fun RainWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
) {
    val rainColor = when (timeOfDay) {
        TimeOfDay.NIGHT -> Color(0xFF4169E1)
        else -> Color(0xFF1E90FF)
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Cloud
        drawCircle(
            color = Color.Gray.copy(alpha = 0.7f),
            radius = size.minDimension / 4f,
            center = center.copy(y = center.y - 20f)
        )
        
        // Rain drops
        for (i in 0..8) {
            val x = center.x + (i - 4) * 8f
            val y = center.y + 10f
            drawLine(
                color = rainColor,
                start = Offset(x, y),
                end = Offset(x - 2f, y + 20f),
                strokeWidth = 2.dp.toPx()
            )
        }
    }
}

@Composable
private fun SnowWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
) {
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Snow cloud
        drawCircle(
            color = Color.LightGray.copy(alpha = 0.8f),
            radius = size.minDimension / 4f,
            center = center.copy(y = center.y - 15f)
        )
        
        // Snowflakes
        for (i in 0..6) {
            val x = center.x + (i - 3) * 12f
            val y = center.y + 15f
            drawCircle(
                color = Color.White,
                radius = 3f,
                center = Offset(x, y)
            )
        }
    }
}

@Composable
private fun CloudWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
) {
    val cloudColor = when (timeOfDay) {
        TimeOfDay.NIGHT -> Color(0xFF2F2F2F)
        else -> Color(0xFF708090)
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val radius = size.minDimension / 5f
        
        // Multiple cloud puffs
        drawCircle(color = cloudColor, radius = radius, center = center)
        drawCircle(color = cloudColor, radius = radius * 0.8f, center = center.copy(x = center.x - 15f))
        drawCircle(color = cloudColor, radius = radius * 0.7f, center = center.copy(x = center.x + 15f))
    }
}

@Composable
private fun StormWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
) {
    val stormAnimation = rememberInfiniteTransition(label = "storm")
    val lightningFlash by stormAnimation.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(500, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "lightning"
    )
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        
        // Storm cloud
        drawCircle(
            color = Color(0xFF2F4F4F),
            radius = size.minDimension / 4f,
            center = center.copy(y = center.y - 10f)
        )
        
        // Lightning
        if (lightningFlash > 0.7f) {
            val lightningPath = Path().apply {
                moveTo(center.x, center.y)
                lineTo(center.x - 8f, center.y + 15f)
                lineTo(center.x + 5f, center.y + 15f)
                lineTo(center.x - 3f, center.y + 30f)
            }
            
            drawPath(
                path = lightningPath,
                color = Color.Yellow,
                style = Stroke(width = 3.dp.toPx())
            )
        }
    }
}

@Composable
private fun DefaultWeatherVisualization(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
) {
    Canvas(modifier = Modifier.fillMaxSize()) {
        val center = Offset(size.width / 2f, size.height / 2f)
        drawCircle(
            color = Color.White.copy(alpha = 0.8f),
            radius = size.minDimension / 5f,
            center = center
        )
    }
}

@Composable
private fun LocationAwareTemperature(
    temperature: Double,
    locationContext: LocationContext
) {
    val tempColor = when (locationContext) {
        LocationContext.TROPICAL -> Color(0xFF00BCD4)
        LocationContext.DESERT -> Color(0xFFFF5722)
        LocationContext.ARCTIC -> Color(0xFF03DAC6)
        LocationContext.MOUNTAIN -> Color(0xFF4CAF50)
        else -> Color.White
    }
    
    Text(
        text = "${temperature.toInt()}°",
        style = MaterialTheme.typography.displayLarge,
        fontWeight = FontWeight.Bold,
        color = tempColor,
        fontSize = 64.sp
    )
}

@Composable
private fun LocationWeatherInsights(
    weather: CurrentWeather,
    location: Location,
    locationContext: LocationContext
) {
    val insight = getLocationSpecificInsight(weather, location, locationContext)
    
    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.Black.copy(alpha = 0.3f)
        )
    ) {
        Text(
            text = insight,
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White.copy(alpha = 0.9f),
            modifier = Modifier.padding(12.dp),
            textAlign = TextAlign.Center
        )
    }
}

@Composable
private fun InteractiveWeatherParticles(
    weather: CurrentWeather,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
) {
    var particles by remember { mutableStateOf(generateWeatherParticles(weather, locationContext)) }
    
    LaunchedEffect(weather.condition.code) {
        while (true) {
            delay(50)
            particles = particles.map { it.update() }
        }
    }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        particles.forEach { particle ->
            drawCircle(
                color = particle.color,
                radius = particle.size,
                center = Offset(particle.x, particle.y)
            )
        }
    }
}

@Composable
private fun LocationAtmosphericOverlay(
    locationContext: LocationContext,
    weather: CurrentWeather,
    timeOfDay: TimeOfDay
) {
    val overlayAlpha = when (locationContext) {
        LocationContext.TROPICAL -> 0.1f
        LocationContext.DESERT -> 0.15f
        LocationContext.ARCTIC -> 0.2f
        LocationContext.COASTAL -> 0.08f
        else -> 0.05f
    }
    
    val overlayColor = getLocationOverlayColor(locationContext, timeOfDay)
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(overlayColor.copy(alpha = overlayAlpha))
    )
}

// Helper functions and data classes
private enum class TimeOfDay {
    DAWN, DAY, DUSK, NIGHT
}

private enum class LocationContext {
    TROPICAL, TEMPERATE, ARCTIC, DESERT, COASTAL, MOUNTAIN, URBAN
}

private data class WeatherParticle(
    var x: Float,
    var y: Float,
    var size: Float,
    var color: Color,
    var velocityX: Float = Random.nextFloat() * 2f - 1f,
    var velocityY: Float = Random.nextFloat() * 2f + 1f
) {
    fun update(): WeatherParticle {
        return copy(
            x = (x + velocityX) % 400f,
            y = if (y > 600f) -10f else y + velocityY
        )
    }
}

private fun getTimeOfDay(time: LocalTime): TimeOfDay {
    return when (time.hour) {
        in 5..7 -> TimeOfDay.DAWN
        in 8..16 -> TimeOfDay.DAY
        in 17..19 -> TimeOfDay.DUSK
        else -> TimeOfDay.NIGHT
    }
}

private fun getLocationContext(location: Location): LocationContext {
    val lat = abs(location.lat)
    val locationName = location.name.lowercase()
    val country = location.country.lowercase()
    
    return when {
        lat < 23.5 -> LocationContext.TROPICAL
        lat > 66.5 -> LocationContext.ARCTIC
        locationName.contains("desert") || country.contains("saudi") -> LocationContext.DESERT
        locationName.contains("mountain") || locationName.contains("hill") -> LocationContext.MOUNTAIN
        locationName.contains("coast") || locationName.contains("beach") -> LocationContext.COASTAL
        locationName.contains("city") || locationName.contains("urban") -> LocationContext.URBAN
        else -> LocationContext.TEMPERATE
    }
}

private fun getDynamicBackgroundGradient(
    weather: CurrentWeather,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
): List<Color> {
    return when (timeOfDay) {
        TimeOfDay.DAWN -> listOf(
            Color(0xFF4A90E2),
            Color(0xFFFFB347),
            Color(0xFFFF6B6B)
        )
        TimeOfDay.DUSK -> listOf(
            Color(0xFFFF6B6B),
            Color(0xFFFFB347),
            Color(0xFF4A5568)
        )
        TimeOfDay.NIGHT -> listOf(
            Color(0xFF1A202C),
            Color(0xFF2D3748),
            Color(0xFF4A5568)
        )
        TimeOfDay.DAY -> when (locationContext) {
            LocationContext.TROPICAL -> listOf(
                Color(0xFF4ECDC4),
                Color(0xFF44A08D),
                Color(0xFF093637)
            )
            LocationContext.DESERT -> listOf(
                Color(0xFFFFB347),
                Color(0xFFFF8C42),
                Color(0xFFD2691E)
            )
            LocationContext.ARCTIC -> listOf(
                Color(0xFFE3F2FD),
                Color(0xFFBBDEFB),
                Color(0xFF90CAF9)
            )
            LocationContext.COASTAL -> listOf(
                Color(0xFF81DEEA),
                Color(0xFF4DD0E1),
                Color(0xFF26C6DA)
            )
            LocationContext.MOUNTAIN -> listOf(
                Color(0xFF9CCC65),
                Color(0xFF689F38),
                Color(0xFF33691E)
            )
            else -> listOf(
                Color(0xFF87CEEB),
                Color(0xFF98FB98),
                Color(0xFF90EE90)
            )
        }
    }
}

private fun getWeatherGlowColor(
    conditionCode: Int,
    timeOfDay: TimeOfDay,
    locationContext: LocationContext
): Color {
    return when {
        isSunnyCondition(conditionCode) -> Color(0xFFFFD700)
        isRainCondition(conditionCode) -> Color(0xFF1E90FF)
        isSnowCondition(conditionCode) -> Color(0xFF87CEEB)
        isStormCondition(conditionCode) -> Color(0xFFFF6B47)
        else -> Color.White
    }
}

private fun getLocalizedWeatherDescription(
    condition: String,
    locationContext: LocationContext
): String {
    return when (locationContext) {
        LocationContext.TROPICAL -> "Tropical $condition"
        LocationContext.DESERT -> "Desert $condition"
        LocationContext.ARCTIC -> "Arctic $condition"
        LocationContext.COASTAL -> "Coastal $condition"
        LocationContext.MOUNTAIN -> "Mountain $condition"
        else -> condition
    }
}

private fun getLocationSpecificInsight(
    weather: CurrentWeather,
    location: Location,
    locationContext: LocationContext
): String {
    return when (locationContext) {
        LocationContext.TROPICAL -> "High humidity typical for tropical climate"
        LocationContext.DESERT -> "Dry conditions with extreme temperature variation"
        LocationContext.ARCTIC -> "Cold conditions with potential for rapid weather changes"
        LocationContext.COASTAL -> "Maritime influence moderates temperature"
        LocationContext.MOUNTAIN -> "Elevation affects temperature and pressure"
        else -> "Current conditions for ${location.name}"
    }
}

private fun getLocationOverlayColor(
    locationContext: LocationContext,
    timeOfDay: TimeOfDay
): Color {
    return when (locationContext) {
        LocationContext.TROPICAL -> Color(0xFF00BCD4)
        LocationContext.DESERT -> Color(0xFFFF9800)
        LocationContext.ARCTIC -> Color(0xFF03DAC6)
        LocationContext.COASTAL -> Color(0xFF2196F3)
        LocationContext.MOUNTAIN -> Color(0xFF4CAF50)
        else -> Color.White
    }
}

private fun generateWeatherParticles(
    weather: CurrentWeather,
    locationContext: LocationContext
): List<WeatherParticle> {
    val particleCount = when {
        isRainCondition(weather.condition.code) -> 15
        isSnowCondition(weather.condition.code) -> 20
        else -> 8
    }
    
    return (1..particleCount).map {
        WeatherParticle(
            x = Random.nextFloat() * 400f,
            y = Random.nextFloat() * 600f,
            size = Random.nextFloat() * 3f + 1f,
            color = when {
                isRainCondition(weather.condition.code) -> Color(0xFF1E90FF)
                isSnowCondition(weather.condition.code) -> Color.White
                else -> Color.White.copy(alpha = 0.6f)
            }
        )
    }
}

// Weather condition helpers
private fun isSunnyCondition(code: Int) = code == 1000
private fun isRainCondition(code: Int) = code in 1063..1201 || code in 1240..1246
private fun isSnowCondition(code: Int) = code in 1066..1117 || code in 1204..1237 || code in 1249..1264
private fun isCloudyCondition(code: Int) = code in 1003..1030
private fun isStormCondition(code: Int) = code in 1087..1087 || code in 1273..1282
