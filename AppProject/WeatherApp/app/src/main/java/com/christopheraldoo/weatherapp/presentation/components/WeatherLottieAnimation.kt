package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.foundation.layout.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.airbnb.lottie.compose.*

@Composable
fun WeatherLottieAnimation(
    condition: String,
    modifier: Modifier = Modifier,
    size: Int = 200,
    isPlaying: Boolean = true
) {
    val animationUrl = getWeatherAnimationUrl(condition)
    
    Box(
        modifier = modifier.size(size.dp),
        contentAlignment = Alignment.Center
    ) {
        LottieAnimationFromUrl(
            url = animationUrl,
            isPlaying = isPlaying,
            modifier = Modifier.fillMaxSize()
        )
    }
}

@Composable
private fun LottieAnimationFromUrl(
    url: String,
    isPlaying: Boolean,
    modifier: Modifier = Modifier
) {
    // Fallback to raw resource animations for demo purposes
    val composition by rememberLottieComposition(
        LottieCompositionSpec.RawRes(getLocalAnimationRes(url))
    )
    val progress by animateLottieCompositionAsState(
        composition = composition,
        isPlaying = isPlaying,
        iterations = LottieConstants.IterateForever,
        speed = 1f
    )

    LottieAnimation(
        composition = composition,
        progress = { progress },
        modifier = modifier
    )
}

private fun getWeatherAnimationUrl(condition: String): String {
    return when (condition.lowercase()) {
        "clear", "sunny" -> "https://assets3.lottiefiles.com/packages/lf20_kbtxnr1j.json"
        "partly cloudy", "cloudy" -> "https://assets3.lottiefiles.com/packages/lf20_ydo83c2c.json"
        "overcast" -> "https://assets3.lottiefiles.com/packages/lf20_uwf7kqqb.json"
        "rain", "light rain", "moderate rain", "heavy rain" -> "https://assets3.lottiefiles.com/packages/lf20_1tJYCE.json"
        "thunderstorm", "storm" -> "https://assets3.lottiefiles.com/packages/lf20_r7fmc0nw.json"
        "snow", "light snow", "heavy snow", "blizzard" -> "https://assets3.lottiefiles.com/packages/lf20_rlysj4jy.json"
        "mist", "fog" -> "https://assets3.lottiefiles.com/packages/lf20_m5uqgj59.json"
        "windy" -> "https://assets3.lottiefiles.com/packages/lf20_reiLaZ.json"
        else -> "https://assets3.lottiefiles.com/packages/lf20_kbtxnr1j.json" // Default sunny
    }
}

private fun getLocalAnimationRes(url: String): Int {
    // Since we can't access external URLs in this demo,
    // we'll use Android's built-in animation resources
    return when {
        url.contains("kbtxnr1j") -> android.R.drawable.ic_partial_secure // Sunny fallback
        url.contains("ydo83c2c") -> android.R.drawable.ic_dialog_info // Cloudy fallback
        url.contains("uwf7kqqb") -> android.R.drawable.ic_dialog_alert // Overcast fallback
        url.contains("1tJYCE") -> android.R.drawable.ic_popup_reminder // Rain fallback
        url.contains("r7fmc0nw") -> android.R.drawable.ic_dialog_alert // Storm fallback
        url.contains("rlysj4jy") -> android.R.drawable.stat_notify_sync // Snow fallback
        url.contains("m5uqgj59") -> android.R.drawable.ic_menu_view // Fog fallback
        url.contains("reiLaZ") -> android.R.drawable.ic_menu_rotate // Wind fallback
        else -> android.R.drawable.ic_partial_secure
    }
}

@Composable
fun CompactWeatherAnimation(
    condition: String,
    modifier: Modifier = Modifier,
    size: Int = 80
) {
    WeatherLottieAnimation(
        condition = condition,
        modifier = modifier,
        size = size,
        isPlaying = true
    )
}

@Composable
fun HeroWeatherAnimation(
    condition: String,
    modifier: Modifier = Modifier,
    size: Int = 300
) {
    WeatherLottieAnimation(
        condition = condition,
        modifier = modifier,
        size = size,
        isPlaying = true
    )
}

// Enhanced weather animation with state management
@Composable
fun StatefulWeatherAnimation(
    condition: String,
    isDay: Boolean,
    modifier: Modifier = Modifier,
    size: Int = 200
) {
    val adjustedCondition = when {
        !isDay && condition.lowercase() == "clear" -> "clear_night"
        !isDay && condition.lowercase().contains("partly") -> "partly_cloudy_night"
        else -> condition
    }
    
    WeatherLottieAnimation(
        condition = adjustedCondition,
        modifier = modifier,
        size = size,
        isPlaying = true
    )
}
