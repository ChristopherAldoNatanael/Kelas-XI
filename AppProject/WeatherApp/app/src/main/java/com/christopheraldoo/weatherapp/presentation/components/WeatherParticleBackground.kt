package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlin.math.*
import kotlin.random.Random

@Composable
fun WeatherParticleBackground(
    weatherCondition: Int,
    modifier: Modifier = Modifier
) {
    val configuration = LocalConfiguration.current
    val density = LocalDensity.current
    
    val screenWidth = with(density) { configuration.screenWidthDp.dp.toPx() }
    val screenHeight = with(density) { configuration.screenHeightDp.dp.toPx() }
    
    val particles = remember {
        generateParticles(weatherCondition, screenWidth, screenHeight)
    }
    
    var animationTime by remember { mutableFloatStateOf(0f) }
    
    LaunchedEffect(Unit) {
        while (true) {
            animationTime += 16f // ~60fps
            delay(16)
        }
    }
    
    Canvas(
        modifier = modifier.fillMaxSize()
    ) {
        when (weatherCondition) {
            in 1000..1003 -> drawSunnyParticles(this, particles, animationTime) // Sunny/Clear
            in 1006..1009 -> drawCloudyParticles(this, particles, animationTime) // Cloudy
            in 1150..1201 -> drawRainyParticles(this, particles, animationTime) // Rain
            in 1210..1225 -> drawSnowyParticles(this, particles, animationTime) // Snow
            in 1273..1282 -> drawStormyParticles(this, particles, animationTime) // Thunderstorm
            else -> drawDefaultParticles(this, particles, animationTime)
        }
    }
}

private fun generateParticles(weatherCondition: Int, screenWidth: Float, screenHeight: Float): List<Particle> {
    val particleCount = when (weatherCondition) {
        in 1000..1003 -> 15 // Sunny - few sparkles
        in 1006..1009 -> 25 // Cloudy - soft mist
        in 1150..1201 -> 100 // Rain - many droplets
        in 1210..1225 -> 80 // Snow - snowflakes
        in 1273..1282 -> 120 // Storm - intense particles
        else -> 30
    }
    
    return (0 until particleCount).map {
        Particle(
            x = Random.nextFloat() * screenWidth,
            y = Random.nextFloat() * screenHeight,
            size = Random.nextFloat() * 4f + 2f,
            speed = Random.nextFloat() * 3f + 1f,
            direction = Random.nextFloat() * 360f,
            alpha = Random.nextFloat() * 0.7f + 0.3f,
            color = getParticleColor(weatherCondition)
        )
    }
}

private fun drawSunnyParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    particles.forEachIndexed { index, particle ->
        val sparkleAlpha = (sin(time * 0.01f + index) + 1f) / 2f * particle.alpha
        val sparkleSize = particle.size * (1f + sin(time * 0.02f + index) * 0.3f)
        
        drawScope.drawCircle(
            color = particle.color.copy(alpha = sparkleAlpha),
            radius = sparkleSize,
            center = Offset(
                particle.x + sin(time * 0.005f + index) * 10f,
                particle.y + cos(time * 0.005f + index) * 5f
            )
        )
    }
}

private fun drawCloudyParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    particles.forEachIndexed { index, particle ->
        val mistAlpha = (sin(time * 0.003f + index) + 1f) / 2f * particle.alpha * 0.4f
        val mistSize = particle.size * (2f + sin(time * 0.004f + index) * 0.5f)
        
        drawScope.drawCircle(
            color = particle.color.copy(alpha = mistAlpha),
            radius = mistSize,
            center = Offset(
                particle.x + sin(time * 0.002f + index) * 15f,
                particle.y + time * 0.1f + sin(time * 0.001f + index) * 5f
            )
        )
    }
}

private fun drawRainyParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    val size = drawScope.size
    particles.forEachIndexed { index, particle ->
        val rainY = (particle.y + time * particle.speed * 2f) % (size.height + 50f)
        val rainX = particle.x + sin(time * 0.01f) * 20f // Wind effect
        
        drawScope.drawLine(
            color = particle.color.copy(alpha = particle.alpha),
            start = Offset(rainX, rainY),
            end = Offset(rainX, rainY + particle.size * 3f),
            strokeWidth = particle.size * 0.5f
        )
    }
}

private fun drawSnowyParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    val size = drawScope.size
    particles.forEachIndexed { index, particle ->
        val snowY = (particle.y + time * particle.speed * 0.8f) % (size.height + 50f)
        val snowX = particle.x + sin(time * 0.008f + index) * 30f // Floating effect
        val snowSize = particle.size * (1f + sin(time * 0.02f + index) * 0.2f)
        
        drawScope.drawCircle(
            color = particle.color.copy(alpha = particle.alpha),
            radius = snowSize,
            center = Offset(snowX, snowY)
        )
        
        // Draw snowflake pattern
        if (snowSize > 3f) {
            val center = Offset(snowX, snowY)
            repeat(6) { i ->
                val angle = i * 60f * PI / 180f
                val endX = center.x + cos(angle) * snowSize * 0.8f
                val endY = center.y + sin(angle) * snowSize * 0.8f
                
                drawScope.drawLine(
                    color = particle.color.copy(alpha = particle.alpha * 0.7f),
                    start = center,
                    end = Offset(endX.toFloat(), endY.toFloat()),
                    strokeWidth = 1f
                )
            }
        }
    }
}

private fun drawStormyParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    val size = drawScope.size
    particles.forEachIndexed { index, particle ->
        val stormY = (particle.y + time * particle.speed * 3f) % (size.height + 50f)
        val stormX = particle.x + sin(time * 0.02f + index) * 40f // Strong wind
        
        // Lightning effect - random bright flashes
        if (Random.nextFloat() < 0.001f) {
            drawScope.drawLine(
                color = Color.White.copy(alpha = 0.8f),
                start = Offset(Random.nextFloat() * size.width, 0f),
                end = Offset(Random.nextFloat() * size.width, size.height),
                strokeWidth = 3f
            )
        }
        
        // Heavy rain
        drawScope.drawLine(
            color = particle.color.copy(alpha = particle.alpha),
            start = Offset(stormX, stormY),
            end = Offset(stormX - 5f, stormY + particle.size * 4f),
            strokeWidth = particle.size * 0.7f
        )
    }
}

private fun drawDefaultParticles(drawScope: DrawScope, particles: List<Particle>, time: Float) {
    particles.forEachIndexed { index, particle ->
        val alpha = (sin(time * 0.005f + index) + 1f) / 2f * particle.alpha * 0.3f
        
        drawScope.drawCircle(
            color = particle.color.copy(alpha = alpha),
            radius = particle.size,
            center = Offset(
                particle.x + sin(time * 0.003f + index) * 8f,
                particle.y + cos(time * 0.002f + index) * 6f
            )
        )
    }
}

private fun getParticleColor(weatherCondition: Int): Color {
    return when (weatherCondition) {
        in 1000..1003 -> Color(0xFFFFF59D) // Sunny - light yellow
        in 1006..1009 -> Color(0xFFE0E0E0) // Cloudy - light gray
        in 1150..1201 -> Color(0xFF64B5F6) // Rain - light blue
        in 1210..1225 -> Color(0xFFFFFFFF) // Snow - white
        in 1273..1282 -> Color(0xFF9E9E9E) // Storm - dark gray
        else -> Color(0xFFBDBDBD) // Default - gray
    }
}

data class Particle(
    val x: Float,
    val y: Float,
    val size: Float,
    val speed: Float,
    val direction: Float,
    val alpha: Float,
    val color: Color
)
