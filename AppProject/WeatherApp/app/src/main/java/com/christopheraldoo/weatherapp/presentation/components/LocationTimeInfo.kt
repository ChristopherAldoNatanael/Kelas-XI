package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.*

@Composable
fun LocationTimeInfo(
    locationName: String,
    timezone: String? = null,
    modifier: Modifier = Modifier
) {
    var currentTime by remember { mutableStateOf("") }
    var currentDate by remember { mutableStateOf("") }
    
    // Update time every minute
    LaunchedEffect(timezone) {
        while (true) {
            val now = Date()
            val timeZone = when {
                !timezone.isNullOrEmpty() -> TimeZone.getTimeZone(timezone)
                else -> getTimezoneFromLocationName(locationName)
            }
            
            val timeFormat = SimpleDateFormat("HH:mm", Locale.getDefault())
            val dateFormat = SimpleDateFormat("EEEE, MMMM dd", Locale.getDefault())
            
            timeFormat.timeZone = timeZone
            dateFormat.timeZone = timeZone
            
            currentTime = timeFormat.format(now)
            currentDate = dateFormat.format(now)
            
            delay(60000) // Update every minute
        }
    }
    
    Column(
        modifier = modifier,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = currentTime,
            style = MaterialTheme.typography.headlineSmall,
            color = Color.White,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = currentDate,
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White.copy(alpha = 0.95f)
        )
        
        // Show timezone info
        if (!timezone.isNullOrEmpty()) {
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = getTimezoneDisplayName(timezone),
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.85f)
            )
        }
    }
}

private fun getTimezoneFromLocationName(locationName: String): TimeZone {
    val lowerName = locationName.lowercase()
    
    val timezoneId = when {
        // Indonesia
        lowerName.contains("jakarta") -> "Asia/Jakarta"
        lowerName.contains("surabaya") -> "Asia/Jakarta"
        lowerName.contains("bandung") -> "Asia/Jakarta"
        lowerName.contains("medan") -> "Asia/Jakarta"
        lowerName.contains("semarang") -> "Asia/Jakarta"
        lowerName.contains("makassar") -> "Asia/Makassar"
        lowerName.contains("palembang") -> "Asia/Jakarta"
        lowerName.contains("yogyakarta") -> "Asia/Jakarta"
        lowerName.contains("denpasar") -> "Asia/Makassar"
        lowerName.contains("balikpapan") -> "Asia/Makassar"
        
        // Major Global Cities
        lowerName.contains("london") -> "Europe/London"
        lowerName.contains("new york") || lowerName.contains("nyc") -> "America/New_York"
        lowerName.contains("tokyo") -> "Asia/Tokyo"
        lowerName.contains("paris") -> "Europe/Paris"
        lowerName.contains("sydney") -> "Australia/Sydney"
        lowerName.contains("dubai") -> "Asia/Dubai"
        lowerName.contains("singapore") -> "Asia/Singapore"
        lowerName.contains("bangkok") -> "Asia/Bangkok"
        lowerName.contains("mumbai") || lowerName.contains("delhi") -> "Asia/Kolkata"
        lowerName.contains("beijing") || lowerName.contains("shanghai") -> "Asia/Shanghai"
        lowerName.contains("moscow") -> "Europe/Moscow"
        lowerName.contains("cairo") -> "Africa/Cairo"
        lowerName.contains("são paulo") || lowerName.contains("sao paulo") -> "America/Sao_Paulo"
        lowerName.contains("los angeles") || lowerName.contains("la") -> "America/Los_Angeles"
        lowerName.contains("chicago") -> "America/Chicago"
        lowerName.contains("toronto") -> "America/Toronto"
        lowerName.contains("vancouver") -> "America/Vancouver"
        lowerName.contains("mexico city") -> "America/Mexico_City"
        lowerName.contains("rio de janeiro") -> "America/Sao_Paulo"
        lowerName.contains("buenos aires") -> "America/Argentina/Buenos_Aires"
        lowerName.contains("berlin") -> "Europe/Berlin"
        lowerName.contains("rome") -> "Europe/Rome"
        lowerName.contains("madrid") -> "Europe/Madrid"
        lowerName.contains("amsterdam") -> "Europe/Amsterdam"
        lowerName.contains("vienna") -> "Europe/Vienna"
        lowerName.contains("zurich") -> "Europe/Zurich"
        lowerName.contains("stockholm") -> "Europe/Stockholm"
        lowerName.contains("oslo") -> "Europe/Oslo"
        lowerName.contains("copenhagen") -> "Europe/Copenhagen"
        lowerName.contains("helsinki") -> "Europe/Helsinki"
        lowerName.contains("warsaw") -> "Europe/Warsaw"
        lowerName.contains("prague") -> "Europe/Prague"
        lowerName.contains("budapest") -> "Europe/Budapest"
        lowerName.contains("brussels") -> "Europe/Brussels"
        lowerName.contains("miami") -> "America/New_York"
        lowerName.contains("san francisco") -> "America/Los_Angeles"
        lowerName.contains("lagos") -> "Africa/Lagos"
        lowerName.contains("nairobi") -> "Africa/Nairobi"
        lowerName.contains("tel aviv") -> "Asia/Jerusalem"
        lowerName.contains("istanbul") -> "Europe/Istanbul"
        lowerName.contains("riyadh") -> "Asia/Riyadh"
        lowerName.contains("doha") -> "Asia/Qatar"
        lowerName.contains("melbourne") -> "Australia/Melbourne"
        lowerName.contains("perth") -> "Australia/Perth"
        lowerName.contains("brisbane") -> "Australia/Brisbane"
        lowerName.contains("auckland") -> "Pacific/Auckland"
        lowerName.contains("wellington") -> "Pacific/Auckland"
        lowerName.contains("seoul") -> "Asia/Seoul"
        lowerName.contains("manila") -> "Asia/Manila"
        lowerName.contains("ho chi minh") -> "Asia/Ho_Chi_Minh"
        lowerName.contains("kuala lumpur") -> "Asia/Kuala_Lumpur"
        
        // By country
        lowerName.contains("indonesia") -> "Asia/Jakarta"
        lowerName.contains("united states") || lowerName.contains("usa") -> "America/New_York"
        lowerName.contains("united kingdom") || lowerName.contains("uk") -> "Europe/London"
        lowerName.contains("japan") -> "Asia/Tokyo"
        lowerName.contains("france") -> "Europe/Paris"
        lowerName.contains("germany") -> "Europe/Berlin"
        lowerName.contains("australia") -> "Australia/Sydney"
        lowerName.contains("singapore") -> "Asia/Singapore"
        lowerName.contains("thailand") -> "Asia/Bangkok"
        lowerName.contains("malaysia") -> "Asia/Kuala_Lumpur"
        lowerName.contains("philippines") -> "Asia/Manila"
        lowerName.contains("vietnam") -> "Asia/Ho_Chi_Minh"
        lowerName.contains("china") -> "Asia/Shanghai"
        lowerName.contains("india") -> "Asia/Kolkata"
        lowerName.contains("russia") -> "Europe/Moscow"
        lowerName.contains("brazil") -> "America/Sao_Paulo"
        lowerName.contains("argentina") -> "America/Argentina/Buenos_Aires"
        lowerName.contains("canada") -> "America/Toronto"
        lowerName.contains("mexico") -> "America/Mexico_City"
        lowerName.contains("egypt") -> "Africa/Cairo"
        lowerName.contains("south africa") -> "Africa/Johannesburg"
        lowerName.contains("nigeria") -> "Africa/Lagos"
        lowerName.contains("kenya") -> "Africa/Nairobi"
        lowerName.contains("israel") -> "Asia/Jerusalem"
        lowerName.contains("turkey") -> "Europe/Istanbul"
        lowerName.contains("saudi arabia") -> "Asia/Riyadh"
        lowerName.contains("qatar") -> "Asia/Qatar"
        lowerName.contains("uae") || lowerName.contains("emirates") -> "Asia/Dubai"
        lowerName.contains("new zealand") -> "Pacific/Auckland"
        lowerName.contains("south korea") -> "Asia/Seoul"
        lowerName.contains("italy") -> "Europe/Rome"
        lowerName.contains("spain") -> "Europe/Madrid"
        lowerName.contains("netherlands") -> "Europe/Amsterdam"
        lowerName.contains("austria") -> "Europe/Vienna"
        lowerName.contains("switzerland") -> "Europe/Zurich"
        lowerName.contains("sweden") -> "Europe/Stockholm"
        lowerName.contains("norway") -> "Europe/Oslo"
        lowerName.contains("denmark") -> "Europe/Copenhagen"
        lowerName.contains("finland") -> "Europe/Helsinki"
        lowerName.contains("poland") -> "Europe/Warsaw"
        lowerName.contains("czech") -> "Europe/Prague"
        lowerName.contains("hungary") -> "Europe/Budapest"
        lowerName.contains("belgium") -> "Europe/Brussels"
        
        else -> TimeZone.getDefault().id
    }
    
    return TimeZone.getTimeZone(timezoneId)
}

private fun getTimezoneDisplayName(timezoneId: String): String {
    val timeZone = TimeZone.getTimeZone(timezoneId)
    val now = Date()
    val offset = timeZone.getOffset(now.time)
    val hours = offset / (1000 * 60 * 60)
    val minutes = kotlin.math.abs(offset / (1000 * 60)) % 60
    
    return when {
        hours > 0 -> "UTC+$hours${if (minutes > 0) ":${String.format("%02d", minutes)}" else ""}"
        hours < 0 -> "UTC$hours${if (minutes > 0) ":${String.format("%02d", minutes)}" else ""}"
        else -> "UTC"
    }
}
