package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.WbCloudy
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

@Composable
fun WeatherIcon(
    iconUrl: String,
    contentDescription: String,
    modifier: Modifier = Modifier,
    size: Dp = 64.dp
) {
    // Simple weather icon using material icons
    // In real app, you would load image from iconUrl using AsyncImage from Coil
    Icon(
        imageVector = Icons.Default.WbCloudy,
        contentDescription = contentDescription,
        modifier = modifier.size(size),
        tint = Color.White.copy(alpha = 0.98f)
    )
}
