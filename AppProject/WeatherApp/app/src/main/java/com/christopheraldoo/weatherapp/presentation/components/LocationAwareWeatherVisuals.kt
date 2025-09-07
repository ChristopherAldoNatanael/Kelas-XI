package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.data.model.CurrentWeather
import com.christopheraldoo.weatherapp.data.model.Location
import com.christopheraldoo.weatherapp.presentation.theme.*
import java.time.LocalTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import kotlin.math.*

@Composable
fun LocationAwareWeatherVisuals(
    weather: CurrentWeather,
    location: Location,
    modifier: Modifier = Modifier
) {
    val currentTime = remember {
        try {
            val zoneId = ZoneId.of(location.tzId)
            LocalTime.now(zoneId)
        } catch (e: Exception) {
            LocalTime.now()
        }
    }
    
    val isDay = currentTime.isAfter(LocalTime.of(6, 0)) && 
                currentTime.isBefore(LocalTime.of(18, 0))
    
    val isDawn = currentTime.isAfter(LocalTime.of(5, 0)) && 
                 currentTime.isBefore(LocalTime.of(7, 0))
    
    val isDusk = currentTime.isAfter(LocalTime.of(17, 0)) && 
                 currentTime.isBefore(LocalTime.of(19, 0))
    
    val isNight = !isDay
    
    Box(
        modifier = modifier
            .fillMaxWidth()
            .animateContentSize(),
        contentAlignment = Alignment.Center
    ) {
        // Dynamic Background Based on Location & Time
        LocationTimeBackground(
            location = location,
            weather = weather,
            isDay = isDay,
            isDawn = isDawn,
            isDusk = isDusk,
            isNight = isNight
        )
        
        // Enhanced Weather Animation with Location Context
        EnhancedLocationWeatherAnimation(
            weather = weather,
            location = location,
            isDay = isDay,
            isDawn = isDawn,
            isDusk = isDusk,
            isNight = isNight,
            currentTime = currentTime
        )
    }
}

@Composable
private fun LocationTimeBackground(
    location: Location,
    weather: CurrentWeather,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean
) {
    val gradientColors = getLocationAwareGradient(
        location = location,
        conditionCode = weather.condition.code,
        isDay = isDay,
        isDawn = isDawn,
        isDusk = isDusk,
        isNight = isNight
    )
      // Animated gradient transition
    val infiniteTransition = rememberInfiniteTransition(label = "gradient")
    val animatedGradientAlpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 0.7f,
        animationSpec = infiniteRepeatable(
            animation = tween(5000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "gradient_alpha"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.radialGradient(
                    colors = gradientColors,
                    radius = 1000f
                )
            )
            .background(
                Color.White.copy(alpha = animatedGradientAlpha * 0.1f)
            )
    )
}

@Composable
private fun EnhancedLocationWeatherAnimation(
    weather: CurrentWeather,
    location: Location,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean,
    currentTime: LocalTime
) {
    val animationSize = 200.dp
    val density = LocalDensity.current
    
    // Complex animation based on multiple factors
    val complexAnimation = rememberInfiniteTransition(label = "complex_weather")
    
    val rotationAnimation by complexAnimation.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(20000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "rotation"
    )
    
    val scaleAnimation by complexAnimation.animateFloat(
        initialValue = 0.95f,
        targetValue = 1.05f,
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "scale"
    )
    
    val pulseAnimation by complexAnimation.animateFloat(
        initialValue = 0.8f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulse"
    )
    
    Box(
        modifier = Modifier
            .size(animationSize)
            .scale(scaleAnimation),
        contentAlignment = Alignment.Center
    ) {
        // Main Weather Icon with Location Context
        LocationContextualWeatherIcon(
            weather = weather,
            location = location,
            isDay = isDay,
            isDawn = isDawn,
            isDusk = isDusk,
            isNight = isNight,
            size = animationSize,
            rotation = rotationAnimation,
            pulse = pulseAnimation
        )
        
        // Atmospheric Effects Based on Location
        LocationAtmosphericEffects(
            location = location,
            weather = weather,
            isDay = isDay,
            isNight = isNight
        )
    }
}

@Composable
private fun LocationContextualWeatherIcon(
    weather: CurrentWeather,
    location: Location,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean,
    size: androidx.compose.ui.unit.Dp,
    rotation: Float,
    pulse: Float
) {
    val weatherIcon = getLocationContextualIcon(
        conditionCode = weather.condition.code,
        location = location,
        isDay = isDay,
        isDawn = isDawn,
        isDusk = isDusk,
        isNight = isNight
    )
    
    val iconColor = getLocationContextualIconColor(
        conditionCode = weather.condition.code,
        location = location,
        isDay = isDay,
        isNight = isNight
    )
    
    Text(
        text = weatherIcon,
        fontSize = (size.value * 0.6f).sp,
        color = iconColor,
        textAlign = TextAlign.Center,
        modifier = Modifier.scale(pulse)
    )
}

@Composable
private fun LocationAtmosphericEffects(
    location: Location,
    weather: CurrentWeather,
    isDay: Boolean,
    isNight: Boolean
) {
    // Create atmospheric particles based on location climate
    val climateZone = getClimateZone(location)
    
    when (climateZone) {
        ClimateZone.TROPICAL -> TropicalAtmosphericEffects(weather, isDay)
        ClimateZone.TEMPERATE -> TemperateAtmosphericEffects(weather, isDay)
        ClimateZone.ARCTIC -> ArcticAtmosphericEffects(weather, isDay)
        ClimateZone.DESERT -> DesertAtmosphericEffects(weather, isDay)
        ClimateZone.COASTAL -> CoastalAtmosphericEffects(weather, isDay)
        ClimateZone.MOUNTAIN -> MountainAtmosphericEffects(weather, isDay)
    }
}

@Composable
private fun TropicalAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Tropical effects: humidity shimmer, occasional rain drops
    if (weather.humidity > 70) {
        HumidityShimmerEffect()
    }
}

@Composable
private fun TemperateAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Temperate effects: seasonal variations
    SeasonalParticleEffect(weather.tempC)
}

@Composable
private fun ArcticAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Arctic effects: ice crystals, aurora-like shimmer
    if (weather.tempC < 0) {
        IceCrystalEffect()
    }
}

@Composable
private fun DesertAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Desert effects: heat haze, sand particles
    if (weather.tempC > 25 && isDay) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            Color.Transparent,
                            Color(0xFFFF9800).copy(alpha = 0.1f),
                            Color.Transparent
                        )
                    )
                )
        )
    }
}

@Composable
private fun CoastalAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Coastal effects: sea mist, salt particles
    SeaMistEffect(weather.humidity)
}

@Composable
private fun MountainAtmosphericEffects(weather: CurrentWeather, isDay: Boolean) {
    // Mountain effects: altitude-based particles
    AltitudeBasedEffect(weather.tempC, weather.humidity)
}

// Atmospheric effect implementations
@Composable
private fun HumidityShimmerEffect() {
    val shimmer = rememberInfiniteTransition(label = "humidity_shimmer")
    val shimmerAlpha by shimmer.animateFloat(
        initialValue = 0.1f,
        targetValue = 0.3f,
        animationSpec = infiniteRepeatable(
            animation = tween(1500, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "shimmer_alpha"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.White.copy(alpha = shimmerAlpha))
    )
}

@Composable
private fun SeasonalParticleEffect(temperature: Double) {
    // Different particles based on temperature seasons
    val particleColor = when {
        temperature < 5 -> Color.White.copy(alpha = 0.6f) // Winter snow
        temperature < 15 -> Color.Green.copy(alpha = 0.3f) // Spring
        temperature < 25 -> Color.Yellow.copy(alpha = 0.2f) // Summer
        else -> Color(0xFFFF8C00).copy(alpha = 0.3f) // Autumn
    }
    
    AnimatedParticleField(particleColor)
}

@Composable
private fun IceCrystalEffect() {
    val crystalAnimation = rememberInfiniteTransition(label = "ice_crystal")
    val crystalRotation by crystalAnimation.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(8000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "crystal_rotation"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Color.Cyan.copy(alpha = 0.1f)
            )
    )
}

@Composable
private fun HeatHazeEffect() {
    val haze = rememberInfiniteTransition(label = "heat_haze")
    val hazeOffset by haze.animateFloat(
        initialValue = -10f,
        targetValue = 10f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "haze_offset"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(
                        Color.Transparent,
                        Color(0xFFFF9800).copy(alpha = 0.1f),
                        Color.Transparent
                    )
                )
            )
    )
}

@Composable
private fun SeaMistEffect(humidity: Int) {
    val mistDensity = (humidity / 100f).coerceIn(0f, 1f)
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Color(0xFF03DAC6).copy(alpha = mistDensity * 0.2f)
            )
    )
}

@Composable
private fun AltitudeBasedEffect(temperature: Double, humidity: Int) {
    val altitudeAlpha = when {
        temperature < 0 && humidity > 60 -> 0.3f // High altitude snow clouds
        temperature < 10 -> 0.2f // Mountain mist
        else -> 0.1f
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Gray.copy(alpha = altitudeAlpha))
    )
}

@Composable
private fun AnimatedParticleField(particleColor: Color) {
    val particles = remember { (1..20).map { ParticleState() } }
    
    Canvas(modifier = Modifier.fillMaxSize()) {
        particles.forEach { particle ->
            drawCircle(
                color = particleColor,
                radius = particle.size,
                center = Offset(particle.x, particle.y)
            )
        }
    }
}

// Helper data classes and functions
private data class ParticleState(
    var x: Float = (0..1000).random().toFloat(),
    var y: Float = (0..1000).random().toFloat(),
    var size: Float = (2..8).random().toFloat()
)

private enum class ClimateZone {
    TROPICAL, TEMPERATE, ARCTIC, DESERT, COASTAL, MOUNTAIN
}

private fun getClimateZone(location: Location): ClimateZone {
    val latitude = location.lat
    val locationName = location.name.lowercase()
    val country = location.country.lowercase()
    
    return when {
        abs(latitude) < 23.5 -> ClimateZone.TROPICAL
        abs(latitude) > 66.5 -> ClimateZone.ARCTIC
        locationName.contains("desert") || country.contains("saudi") || country.contains("uae") -> ClimateZone.DESERT
        locationName.contains("coast") || locationName.contains("beach") || country.contains("island") -> ClimateZone.COASTAL
        locationName.contains("mountain") || locationName.contains("hill") || latitude > 1000 -> ClimateZone.MOUNTAIN
        else -> ClimateZone.TEMPERATE
    }
}

private fun getLocationAwareGradient(
    location: Location,
    conditionCode: Int,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean
): List<Color> {
    val climateZone = getClimateZone(location)
    
    return when {
        isDawn -> listOf(
            Color(0xFF4A90E2),
            Color(0xFFFFB347),
            Color(0xFFFF6B6B)
        )
        isDusk -> listOf(
            Color(0xFFFF6B6B),
            Color(0xFFFFB347),
            Color(0xFF4A5568)
        )
        isNight -> listOf(
            Color(0xFF1A202C),
            Color(0xFF2D3748),
            Color(0xFF4A5568)
        )
        else -> when (climateZone) {
            ClimateZone.TROPICAL -> listOf(
                Color(0xFF4ECDC4),
                Color(0xFF44A08D),
                Color(0xFF093637)
            )
            ClimateZone.DESERT -> listOf(
                Color(0xFFFFB347),
                Color(0xFFFF8C42),
                Color(0xFFD2691E)
            )
            ClimateZone.ARCTIC -> listOf(
                Color(0xFFE3F2FD),
                Color(0xFFBBDEFB),
                Color(0xFF90CAF9)
            )
            ClimateZone.COASTAL -> listOf(
                Color(0xFF81DEEA),
                Color(0xFF4DD0E1),
                Color(0xFF26C6DA)
            )
            ClimateZone.MOUNTAIN -> listOf(
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

private fun getLocationContextualIcon(
    conditionCode: Int,
    location: Location,
    isDay: Boolean,
    isDawn: Boolean,
    isDusk: Boolean,
    isNight: Boolean
): String {
    val baseIcon = getBaseWeatherIcon(conditionCode)
    val climateZone = getClimateZone(location)
    
    return when {
        isDawn -> when (conditionCode) {
            1000 -> "🌅" // Sunrise
            1003 -> "🌤️" // Partly cloudy dawn
            else -> baseIcon
        }
        isDusk -> when (conditionCode) {
            1000 -> "🌇" // Sunset
            1003 -> "🌥️" // Partly cloudy dusk
            else -> baseIcon
        }
        isNight -> when (conditionCode) {
            1000 -> "🌙" // Clear night
            1003 -> "🌙" // Partly cloudy night
            else -> baseIcon
        }
        else -> when (climateZone) {
            ClimateZone.TROPICAL -> when (conditionCode) {
                1000 -> "🏝️" // Tropical sun
                else -> baseIcon
            }
            ClimateZone.DESERT -> when (conditionCode) {
                1000 -> "🏜️" // Desert sun
                else -> baseIcon
            }
            ClimateZone.ARCTIC -> when (conditionCode) {
                1000 -> "🧊" // Arctic conditions
                else -> "❄️"
            }
            else -> baseIcon
        }
    }
}

private fun getLocationContextualIconColor(
    conditionCode: Int,
    location: Location,
    isDay: Boolean,
    isNight: Boolean
): Color {
    val climateZone = getClimateZone(location)
    
    return when {
        isNight -> Color.White.copy(alpha = 0.9f)
        else -> when (climateZone) {
            ClimateZone.TROPICAL -> Color(0xFF00BCD4)
            ClimateZone.DESERT -> Color(0xFFFF9800)
            ClimateZone.ARCTIC -> Color(0xFF03DAC6)
            ClimateZone.COASTAL -> Color(0xFF2196F3)
            ClimateZone.MOUNTAIN -> Color(0xFF4CAF50)
            else -> Color.White
        }
    }
}

private fun getBaseWeatherIcon(conditionCode: Int): String {
    return when (conditionCode) {
        1000 -> "☀️"
        1003 -> "🌤️"
        1006 -> "⛅"
        1009 -> "☁️"
        1030 -> "🌫️"
        in 1063..1087 -> "🌧️"
        in 1114..1117 -> "❄️"
        in 1135..1147 -> "🌫️"
        in 1150..1201 -> "🌦️"
        in 1204..1237 -> "🌨️"
        in 1240..1246 -> "🌧️"
        in 1249..1264 -> "🌨️"
        in 1273..1282 -> "⛈️"
        else -> "🌤️"
    }
}
