package com.christopheraldoo.weatherapp.presentation.screen

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.presentation.theme.*
import kotlinx.coroutines.delay

@Composable
fun SplashScreen(
    onSplashFinished: () -> Unit
) {
    val configuration = LocalConfiguration.current
    val screenWidth = configuration.screenWidthDp.dp
    val screenHeight = configuration.screenHeightDp.dp

    var animationStarted by remember { mutableStateOf(false) }
    
    // Multiple animation states for cinematic effect
    val fadeIn = animateFloatAsState(
        targetValue = if (animationStarted) 1f else 0f,
        animationSpec = tween(durationMillis = 2000, easing = EaseInOutCubic),
        label = "fadeIn"
    )
    
    val scaleAnimation = animateFloatAsState(
        targetValue = if (animationStarted) 1f else 0.3f,
        animationSpec = tween(durationMillis = 2500, easing = EaseOutElastic),
        label = "scale"
    )
    
    val rotationAnimation = animateFloatAsState(
        targetValue = if (animationStarted) 0f else -180f,
        animationSpec = tween(durationMillis = 3000, easing = EaseInOutBack),
        label = "rotation"
    )
    
    val slideAnimation = animateFloatAsState(
        targetValue = if (animationStarted) 0f else screenWidth.value,
        animationSpec = tween(durationMillis = 2000, delayMillis = 500, easing = EaseOutCubic),
        label = "slide"
    )
    
    val blurAnimation = animateFloatAsState(
        targetValue = if (animationStarted) 0f else 10f,
        animationSpec = tween(durationMillis = 3000, easing = EaseInOutQuart),
        label = "blur"
    )
    
    // Parallax background layers
    val backgroundLayer1 = animateFloatAsState(
        targetValue = if (animationStarted) 1f else 0.8f,
        animationSpec = tween(durationMillis = 4000, easing = EaseInOutSine),
        label = "backgroundLayer1"
    )
    
    val backgroundLayer2 = animateFloatAsState(
        targetValue = if (animationStarted) 1f else 1.2f,
        animationSpec = tween(durationMillis = 3500, easing = EaseInOutSine),
        label = "backgroundLayer2"
    )

    LaunchedEffect(Unit) {
        delay(300)
        animationStarted = true
        delay(4000)
        onSplashFinished()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(
                        Color(0xFF1A237E), // Deep blue
                        Color(0xFF3F51B5), // Indigo
                        Color(0xFF9C27B0), // Purple
                        Color(0xFFE91E63), // Pink
                        Color(0xFFFF5722)  // Orange
                    ),
                    startY = 0f,
                    endY = screenHeight.value * 2
                )
            )
    ) {
        // Animated background layers for depth
        Box(
            modifier = Modifier
                .fillMaxSize()
                .scale(backgroundLayer1.value)
                .alpha(0.3f)
                .background(
                    brush = Brush.radialGradient(
                        colors = listOf(
                            Color.White.copy(alpha = 0.2f),
                            Color.Transparent
                        ),
                        radius = screenWidth.value * 1.5f
                    )
                )
        )
        
        Box(
            modifier = Modifier
                .fillMaxSize()
                .scale(backgroundLayer2.value)
                .alpha(0.2f)
                .background(
                    brush = Brush.radialGradient(
                        colors = listOf(
                            Color(0xFFFFEB3B).copy(alpha = 0.3f),
                            Color.Transparent
                        ),
                        radius = screenWidth.value
                    )
                )
        )

        // Main content
        Column(
            modifier = Modifier
                .fillMaxSize()
                .alpha(fadeIn.value),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            // App logo/icon placeholder with cinematic animations
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .scale(scaleAnimation.value)
                    .rotate(rotationAnimation.value)
                    .blur(radius = blurAnimation.value.dp)
                    .clip(RoundedCornerShape(30.dp))
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(
                                Color(0xFFFFEB3B),
                                Color(0xFFFF9800),
                                Color(0xFFFF5722)
                            )
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "☀️",
                    fontSize = 60.sp,
                    modifier = Modifier.alpha(fadeIn.value)
                )
            }
            
            Spacer(modifier = Modifier.height(40.dp))
            
            // App title with slide animation
            Column(
                modifier = Modifier.offset(x = (-slideAnimation.value).dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "Weather Matters",
                    fontSize = 36.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    textAlign = TextAlign.Center,
                    fontFamily = PoppinsFontFamily,
                    letterSpacing = 2.sp
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = "Professional Weather Experience",
                    fontSize = 16.sp,
                    color = Color.White.copy(alpha = 0.8f),
                    textAlign = TextAlign.Center,
                    fontFamily = InterFontFamily,
                    letterSpacing = 1.sp
                )
            }
        }
        
        // Bottom loading indicator
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.BottomCenter)
                .padding(40.dp)
        ) {
            LoadingIndicator(
                modifier = Modifier
                    .align(Alignment.Center)
                    .alpha(fadeIn.value)
            )
        }
    }
}

@Composable
fun LoadingIndicator(
    modifier: Modifier = Modifier
) {
    val infiniteTransition = rememberInfiniteTransition(label = "loading")
    
    val dots = (0..2).map { index ->
        infiniteTransition.animateFloat(
            initialValue = 0.3f,
            targetValue = 1f,
            animationSpec = infiniteRepeatable(
                animation = tween(durationMillis = 600),
                repeatMode = RepeatMode.Reverse,
                initialStartOffset = StartOffset(index * 200)
            ),
            label = "dot$index"
        )
    }
    
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        dots.forEach { dot ->
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .scale(dot.value)
                    .clip(RoundedCornerShape(6.dp))
                    .background(Color.White.copy(alpha = dot.value))
            )
        }
    }
}
