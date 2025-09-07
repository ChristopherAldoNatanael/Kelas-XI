package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.presentation.theme.InterFontFamily
import com.christopheraldoo.weatherapp.presentation.theme.GlassWhite
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.*

data class WorldTimeZone(
    val cityName: String,
    val timeZone: String,
    val countryCode: String,
    val emoji: String
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WorldClockCard(
    userLocation: String = "Jakarta",
    searchedLocations: List<String> = emptyList(),
    modifier: Modifier = Modifier
) {
    var currentTime by remember { mutableStateOf(System.currentTimeMillis()) }
    
    // Update time every second
    LaunchedEffect(Unit) {
        while (true) {
            delay(1000)
            currentTime = System.currentTimeMillis()
        }
    }
    
    val worldTimeZones = remember {
        getWorldTimeZones(userLocation, searchedLocations)
    }
    
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Schedule,
                    contentDescription = "World Clock",
                    tint = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = "World Clock",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                contentPadding = PaddingValues(horizontal = 4.dp)
            ) {
                items(worldTimeZones) { timeZone ->
                    WorldTimeCard(
                        timeZone = timeZone,
                        currentTime = currentTime
                    )
                }
            }
        }
    }
}

@Composable
fun WorldTimeCard(
    timeZone: WorldTimeZone,
    currentTime: Long,
    modifier: Modifier = Modifier
) {
    val timeFormat = SimpleDateFormat("HH:mm", Locale.getDefault())
    val dateFormat = SimpleDateFormat("MMM dd", Locale.getDefault())
    val timeZoneObj = TimeZone.getTimeZone(timeZone.timeZone)
    
    timeFormat.timeZone = timeZoneObj
    dateFormat.timeZone = timeZoneObj
    
    val time = timeFormat.format(Date(currentTime))
    val date = dateFormat.format(Date(currentTime))
    
    // Animate the time change
    val animatedScale by animateFloatAsState(
        targetValue = 1f,
        animationSpec = spring(
            dampingRatio = Spring.DampingRatioLowBouncy,
            stiffness = Spring.StiffnessLow
        ),
        label = "timeScale"
    )
    
    Card(
        modifier = modifier.width(120.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.7f)
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = timeZone.emoji,
                fontSize = 24.sp
            )
            
            Spacer(modifier = Modifier.height(4.dp))
            
            Text(
                text = timeZone.cityName,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Medium,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f),
                fontFamily = InterFontFamily
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = time,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.scale(animatedScale)
            )
            
            Text(
                text = date,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                fontFamily = InterFontFamily
            )
        }
    }
}

private fun getWorldTimeZones(
    userLocation: String,
    searchedLocations: List<String>
): List<WorldTimeZone> {
    val majorCities = listOf(
        WorldTimeZone("New York", "America/New_York", "US", "🗽"),
        WorldTimeZone("London", "Europe/London", "GB", "🏴󐁧󐁢󐁥󐁮󐁧󐁿"),
        WorldTimeZone("Paris", "Europe/Paris", "FR", "🗼"),
        WorldTimeZone("Tokyo", "Asia/Tokyo", "JP", "🗾"),
        WorldTimeZone("Sydney", "Australia/Sydney", "AU", "🇦🇺"),
        WorldTimeZone("Dubai", "Asia/Dubai", "AE", "🏜️"),
        WorldTimeZone("Singapore", "Asia/Singapore", "SG", "🦁"),
        WorldTimeZone("Jakarta", "Asia/Jakarta", "ID", "🇮🇩"),
        WorldTimeZone("Bangkok", "Asia/Bangkok", "TH", "🇹🇭"),
        WorldTimeZone("Mumbai", "Asia/Kolkata", "IN", "🇮🇳"),
        WorldTimeZone("Beijing", "Asia/Shanghai", "CN", "🇨🇳"),
        WorldTimeZone("Moscow", "Europe/Moscow", "RU", "🇷🇺"),
        WorldTimeZone("Cairo", "Africa/Cairo", "EG", "🏛️"),
        WorldTimeZone("São Paulo", "America/Sao_Paulo", "BR", "🇧🇷"),
        WorldTimeZone("Los Angeles", "America/Los_Angeles", "US", "🎬")
    )
    
    // Prioritize user location and searched locations
    val prioritizedCities = mutableListOf<WorldTimeZone>()
    
    // Add user location first if it exists in major cities
    majorCities.find { it.cityName.equals(userLocation, ignoreCase = true) }?.let {
        prioritizedCities.add(it)
    }
    
    // Add searched locations
    searchedLocations.forEach { searchedLocation ->
        majorCities.find { it.cityName.equals(searchedLocation, ignoreCase = true) }?.let { timeZone ->
            if (!prioritizedCities.contains(timeZone)) {
                prioritizedCities.add(timeZone)
            }
        }
    }
    
    // Add remaining major cities
    majorCities.forEach { timeZone ->
        if (!prioritizedCities.contains(timeZone)) {
            prioritizedCities.add(timeZone)
        }
    }
    
    return prioritizedCities.take(8) // Limit to 8 cities for better UI
}

fun getTimeZoneForCity(cityName: String): String {
    return when (cityName.lowercase()) {
        "new york", "nyc" -> "America/New_York"
        "london" -> "Europe/London"
        "paris" -> "Europe/Paris"
        "tokyo" -> "Asia/Tokyo"
        "sydney" -> "Australia/Sydney"
        "dubai" -> "Asia/Dubai"
        "singapore" -> "Asia/Singapore"
        "jakarta" -> "Asia/Jakarta"
        "bangkok" -> "Asia/Bangkok"
        "mumbai", "delhi" -> "Asia/Kolkata"
        "beijing", "shanghai" -> "Asia/Shanghai"
        "moscow" -> "Europe/Moscow"
        "cairo" -> "Africa/Cairo"
        "são paulo", "sao paulo" -> "America/Sao_Paulo"
        "los angeles", "la" -> "America/Los_Angeles"
        "chicago" -> "America/Chicago"
        "toronto" -> "America/Toronto"
        "berlin" -> "Europe/Berlin"
        "rome" -> "Europe/Rome"
        "madrid" -> "Europe/Madrid"
        "hong kong" -> "Asia/Hong_Kong"
        "kuala lumpur" -> "Asia/Kuala_Lumpur"
        "manila" -> "Asia/Manila"
        "seoul" -> "Asia/Seoul"
        "melbourne" -> "Australia/Melbourne"
        else -> "UTC" // Default fallback
    }
}
