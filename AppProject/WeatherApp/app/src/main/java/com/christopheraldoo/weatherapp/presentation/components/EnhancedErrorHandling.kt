package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.presentation.theme.*
import kotlinx.coroutines.delay

// Enhanced Error States
sealed class WeatherErrorType {
    object NetworkError : WeatherErrorType()
    object LocationError : WeatherErrorType()
    object APIError : WeatherErrorType()
    object CacheError : WeatherErrorType()
    object UnknownError : WeatherErrorType()
}

data class ErrorConfig(
    val title: String,
    val message: String,
    val icon: ImageVector,
    val actionText: String,
    val gradient: Brush
)

@Composable
fun EnhancedErrorCard(
    errorType: WeatherErrorType,
    onRetry: () -> Unit,
    onDismiss: (() -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    val config = getErrorConfig(errorType)
    
    var isRetrying by remember { mutableStateOf(false) }
    
    // Animation states
    val scale by animateFloatAsState(
        targetValue = if (isRetrying) 0.95f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy)
    )
    
    val alpha by animateFloatAsState(
        targetValue = if (isRetrying) 0.7f else 1f,
        animationSpec = tween(300)
    )
    
    Card(
        modifier = modifier
            .fillMaxWidth()
            .scale(scale)
            .padding(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
        shape = RoundedCornerShape(20.dp)
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(config.gradient)
                .padding(24.dp)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Animated Error Icon
                AnimatedErrorIcon(
                    icon = config.icon,
                    isRetrying = isRetrying,
                    modifier = Modifier.size(64.dp)
                )
                
                // Error Title
                Text(
                    text = config.title,
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    textAlign = TextAlign.Center
                )
                
                // Error Message
                Text(
                    text = config.message,
                    fontSize = 16.sp,
                    color = Color.White.copy(alpha = 0.9f),
                    textAlign = TextAlign.Center,
                    lineHeight = 22.sp
                )
                
                // Action Buttons
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Retry Button
                    Button(
                        onClick = {
                            isRetrying = true
                            onRetry()
                        },
                        enabled = !isRetrying,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color.White,
                            contentColor = Color.Black
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        if (isRetrying) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(16.dp),
                                strokeWidth = 2.dp,
                                color = Color.Black
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                        }
                        Text(
                            text = if (isRetrying) "Retrying..." else config.actionText,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                    
                    // Dismiss Button (if provided)
                    onDismiss?.let { dismiss ->
                        OutlinedButton(
                            onClick = dismiss,
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = Color.White
                            ),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Text(
                                text = "Dismiss",
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }
            }
        }
    }
    
    // Auto-reset retry state
    LaunchedEffect(isRetrying) {
        if (isRetrying) {
            delay(2000)
            isRetrying = false
        }
    }
}

@Composable
private fun AnimatedErrorIcon(
    icon: ImageVector,
    isRetrying: Boolean,
    modifier: Modifier = Modifier
) {
    val rotation by animateFloatAsState(
        targetValue = if (isRetrying) 360f else 0f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        )
    )
    
    Box(
        modifier = modifier,
        contentAlignment = Alignment.Center
    ) {
        // Background Circle
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Color.White.copy(alpha = 0.2f),
                    RoundedCornerShape(50)
                )
        )
        
        // Icon
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Color.White,
            modifier = Modifier
                .size(32.dp)
                .then(
                    if (isRetrying) Modifier.scale(rotation / 360f + 0.7f)
                    else Modifier
                )
        )
    }
}

@Composable
fun NetworkStatusIndicator(
    isConnected: Boolean,
    modifier: Modifier = Modifier
) {
    AnimatedVisibility(
        visible = !isConnected,
        enter = slideInVertically() + fadeIn(),
        exit = slideOutVertically() + fadeOut(),
        modifier = modifier
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.error
            ),
            shape = RoundedCornerShape(12.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.WifiOff,
                    contentDescription = null,
                    tint = Color.White
                )
                
                Text(
                    text = "No internet connection",
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@Composable
fun RetryWithBackoff(
    onRetry: suspend () -> Unit,
    maxRetries: Int = 3,
    initialDelayMillis: Long = 1000L,
    backoffMultiplier: Float = 2f,
    content: @Composable (retry: () -> Unit, isRetrying: Boolean, attemptCount: Int) -> Unit
) {
    var isRetrying by remember { mutableStateOf(false) }
    var attemptCount by remember { mutableStateOf(0) }
    var currentDelay by remember { mutableStateOf(initialDelayMillis) }
    
    val retry: () -> Unit = {
        if (!isRetrying && attemptCount < maxRetries) {
            isRetrying = true
        }
    }
    
    // Handle retry logic
    LaunchedEffect(isRetrying) {
        if (isRetrying) {
            delay(currentDelay)
            try {
                onRetry()
                // Reset on success
                attemptCount = 0
                currentDelay = initialDelayMillis
            } catch (e: Exception) {
                attemptCount++
                currentDelay = (currentDelay * backoffMultiplier).toLong()
            } finally {
                isRetrying = false
            }
        }
    }
    
    content(retry, isRetrying, attemptCount)
}

private fun getErrorConfig(errorType: WeatherErrorType): ErrorConfig {
    return when (errorType) {
        is WeatherErrorType.NetworkError -> ErrorConfig(
            title = "Connection Lost",
            message = "Unable to connect to weather services. Please check your internet connection and try again.",
            icon = Icons.Default.WifiOff,
            actionText = "Retry Connection",
            gradient = Brush.linearGradient(
                colors = listOf(
                    Color(0xFFE74C3C),
                    Color(0xFFC0392B)
                )
            )
        )
        
        is WeatherErrorType.LocationError -> ErrorConfig(
            title = "Location Access",
            message = "Unable to access your location. Please enable location services or search for a city manually.",
            icon = Icons.Default.LocationOff,
            actionText = "Grant Access",
            gradient = Brush.linearGradient(
                colors = listOf(
                    Color(0xFFF39C12),
                    Color(0xFFE67E22)
                )
            )
        )
        
        is WeatherErrorType.APIError -> ErrorConfig(
            title = "Service Unavailable",
            message = "Weather service is temporarily unavailable. Please try again in a few moments.",
            icon = Icons.Default.CloudOff,
            actionText = "Try Again",
            gradient = Brush.linearGradient(
                colors = listOf(
                    Color(0xFF9B59B6),
                    Color(0xFF8E44AD)
                )
            )
        )
        
        is WeatherErrorType.CacheError -> ErrorConfig(
            title = "Data Sync Issue",
            message = "Unable to sync weather data. Your cached data may be outdated.",
            icon = Icons.Default.Sync,
            actionText = "Refresh Data",
            gradient = Brush.linearGradient(
                colors = listOf(
                    Color(0xFF3498DB),
                    Color(0xFF2980B9)
                )
            )
        )
        
        is WeatherErrorType.UnknownError -> ErrorConfig(
            title = "Something Went Wrong",
            message = "An unexpected error occurred. Please restart the app or try again later.",
            icon = Icons.Default.Error,
            actionText = "Retry",
            gradient = Brush.linearGradient(
                colors = listOf(
                    Color(0xFF95A5A6),
                    Color(0xFF7F8C8D)
                )
            )
        )
    }
}
