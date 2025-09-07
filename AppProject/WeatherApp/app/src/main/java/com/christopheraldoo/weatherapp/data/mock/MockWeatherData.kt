package com.christopheraldoo.weatherapp.data.mock

import com.christopheraldoo.weatherapp.data.model.*

object MockWeatherData {
      fun getMockWeatherResponse(location: String = "Jakarta, Indonesia"): WeatherResponse {
        val locationData = parseMockLocationData(location)
        
        return WeatherResponse(
            location = Location(
                name = locationData.name,
                region = locationData.region,
                country = locationData.country,
                lat = locationData.lat,
                lon = locationData.lon,
                tzId = locationData.timezone,
                localtimeEpoch = System.currentTimeMillis() / 1000,
                localtime = getCurrentLocalTime(locationData.timezone)
            ),
            current = CurrentWeather(
                lastUpdatedEpoch = System.currentTimeMillis() / 1000,
                lastUpdated = getCurrentLocalTime(locationData.timezone),
                tempC = locationData.temperature,
                tempF = celsiusToFahrenheit(locationData.temperature),
                isDay = if (isCurrentlyDay(locationData.timezone)) 1 else 0,
                condition = Condition(
                    text = locationData.weatherCondition,
                    icon = "//cdn.weatherapi.com/weather/64x64/day/116.png",
                    code = 1003
                ),
                windMph = 9.4,
                windKph = 15.1,
                windDegree = 180,
                windDir = "S",
                pressureMb = 1013.0,
                pressureIn = 29.91,
                precipMm = 0.0,
                precipIn = 0.0,
                humidity = locationData.humidity,
                cloud = 25,
                feelslikeC = locationData.temperature + 3,
                feelslikeF = celsiusToFahrenheit(locationData.temperature + 3),
                visKm = 10.0,
                visMiles = 6.0,
                uv = 8.0,
                gustMph = 12.1,
                gustKph = 19.4
            ),
            forecast = Forecast(
                forecastday = getMockForecastDays()
            )
        )
    }
    
    private fun getMockForecastDays(): List<ForecastDay> {
        val days = mutableListOf<ForecastDay>()
        val conditions = listOf(
            Condition("Sunny", "//cdn.weatherapi.com/weather/64x64/day/113.png", 1000),
            Condition("Partly cloudy", "//cdn.weatherapi.com/weather/64x64/day/116.png", 1003),
            Condition("Cloudy", "//cdn.weatherapi.com/weather/64x64/day/119.png", 1006),
            Condition("Light rain", "//cdn.weatherapi.com/weather/64x64/day/296.png", 1183),
            Condition("Heavy rain", "//cdn.weatherapi.com/weather/64x64/day/308.png", 1195),
            Condition("Thunderstorm", "//cdn.weatherapi.com/weather/64x64/day/200.png", 1087),
            Condition("Partly cloudy", "//cdn.weatherapi.com/weather/64x64/day/116.png", 1003)
        )
        
        val baseTemps = listOf(32.0, 28.0, 25.0, 23.0, 29.0, 31.0, 33.0)
        val rainChances = listOf(5, 15, 30, 80, 95, 60, 10)
        
        repeat(7) { index ->
            val condition = conditions[index]
            val baseTemp = baseTemps[index]
            val rainChance = rainChances[index]
            
            days.add(
                ForecastDay(
                    date = "2024-12-${12 + index}",
                    dateEpoch = (System.currentTimeMillis() / 1000) + (index * 24 * 60 * 60),
                    day = Day(
                        maxtempC = baseTemp + 3.0,
                        maxtempF = (baseTemp + 3.0) * 9 / 5 + 32,
                        mintempC = baseTemp - 5.0,
                        mintempF = (baseTemp - 5.0) * 9 / 5 + 32,
                        avgtempC = baseTemp.toDouble(),
                        avgtempF = baseTemp * 9.0 / 5 + 32,
                        maxwindMph = 12.0 + index,
                        maxwindKph = (12.0 + index) * 1.609,
                        totalprecipMm = if (rainChance > 50) 15.0 + index else 0.0,
                        totalprecipIn = if (rainChance > 50) (15.0 + index) / 25.4 else 0.0,
                        totalsnowCm = 0.0,
                        avgvisKm = 10.0,
                        avgvisMiles = 6.2,
                        avghumidity = (60 + index * 2).toDouble(),
                        dailyWillItRain = if (rainChance > 50) 1 else 0,
                        dailyChanceOfRain = rainChance,
                        dailyWillItSnow = 0,
                        dailyChanceOfSnow = 0,
                        condition = condition,
                        uv = 7.0 - index * 0.5
                    ),
                    astro = Astro(
                        sunrise = "06:${30 + index}0 AM",
                        sunset = "06:${30 - index}0 PM",
                        moonrise = "08:${15 + index * 10} PM",
                        moonset = "07:${45 - index * 5} AM",
                        moonPhase = listOf("New Moon", "Waxing Crescent", "First Quarter", "Waxing Gibbous", "Full Moon", "Waning Gibbous", "Last Quarter")[index],
                        moonIllumination = "${(index * 15) % 100}",
                        isMoonUp = 1,
                        isSunUp = 1
                    ),
                    hour = getMockHourlyData(condition, baseTemp, rainChance)
                )
            )
        }
        return days
    }
    
    private fun getMockHourlyData(condition: Condition, baseTemp: Double, rainChance: Int): List<Hour> {
        val hours = mutableListOf<Hour>()
        repeat(24) { hour ->
            val tempVariation = when (hour) {
                in 6..8 -> -3 // Early morning cool
                in 9..11 -> 0 // Morning
                in 12..15 -> 4 // Hot afternoon
                in 16..18 -> 2 // Evening
                in 19..21 -> -1 // Night
                else -> -4 // Late night/early morning
            }
            
            hours.add(
                Hour(
                    timeEpoch = (System.currentTimeMillis() / 1000) + (hour * 60 * 60),
                    time = "2024-12-12 ${hour.toString().padStart(2, '0')}:00",
                    tempC = baseTemp + tempVariation.toDouble(),
                    tempF = (baseTemp + tempVariation) * 9.0 / 5 + 32,
                    isDay = if (hour in 6..18) 1 else 0,
                    condition = condition,
                    windMph = 8.0 + hour * 0.2,
                    windKph = (8.0 + hour * 0.2) * 1.609,
                    windDegree = 180 + hour * 5,
                    windDir = "S",
                    pressureMb = 1013.0 - hour * 0.5,
                    pressureIn = 29.91 - hour * 0.01,
                    precipMm = if (rainChance > 50 && hour in 13..17) 5.0 else 0.0,
                    precipIn = if (rainChance > 50 && hour in 13..17) 0.2 else 0.0,
                    humidity = 65 - hour,
                    cloud = 25 + hour,
                    feelslikeC = baseTemp + tempVariation + 2.0,
                    feelslikeF = (baseTemp + tempVariation + 2) * 9.0 / 5 + 32,
                    windchillC = baseTemp + tempVariation - 1.0,
                    windchillF = (baseTemp + tempVariation - 1) * 9.0 / 5 + 32,
                    heatindexC = baseTemp + tempVariation + 3.0,
                    heatindexF = (baseTemp + tempVariation + 3) * 9.0 / 5 + 32,
                    dewpointC = baseTemp + tempVariation - 8.0,
                    dewpointF = (baseTemp + tempVariation - 8) * 9.0 / 5 + 32,
                    willItRain = if (rainChance > 50 && hour in 13..17) 1 else 0,
                    chanceOfRain = if (hour in 13..17) rainChance else rainChance / 2,
                    willItSnow = 0,
                    chanceOfSnow = 0,
                    visKm = 10.0 - hour * 0.1,
                    visMiles = 6.0 - hour * 0.05,
                    gustMph = 10.0 + hour * 0.3,
                    gustKph = (10.0 + hour * 0.3) * 1.609,
                    uv = if (hour in 10..14) 8.0 - (hour - 12).coerceAtLeast(0) else 0.0
                )
            )
        }
        return hours
    }
    
    // Helper data class for location information
    private data class LocationData(
        val name: String,
        val region: String,
        val country: String,
        val lat: Double,
        val lon: Double,
        val timezone: String,
        val temperature: Double,
        val humidity: Int,
        val weatherCondition: String
    )
    
    private fun parseMockLocationData(location: String): LocationData {
        val locationLower = location.lowercase()
        
        return when {
            // Indonesia
            locationLower.contains("jakarta") -> LocationData(
                name = "Jakarta", region = "Jakarta Special Capital Region", country = "Indonesia",
                lat = -6.21, lon = 106.85, timezone = "Asia/Jakarta",
                temperature = 32.0, humidity = 75, weatherCondition = "Partly cloudy"
            )
            locationLower.contains("surabaya") -> LocationData(
                name = "Surabaya", region = "East Java", country = "Indonesia",
                lat = -7.25, lon = 112.75, timezone = "Asia/Jakarta",
                temperature = 31.0, humidity = 78, weatherCondition = "Mostly sunny"
            )
            locationLower.contains("bandung") -> LocationData(
                name = "Bandung", region = "West Java", country = "Indonesia",
                lat = -6.90, lon = 107.61, timezone = "Asia/Jakarta",
                temperature = 24.0, humidity = 70, weatherCondition = "Cloudy"
            )
            
            // Major Global Cities
            locationLower.contains("london") -> LocationData(
                name = "London", region = "England", country = "United Kingdom",
                lat = 51.52, lon = -0.11, timezone = "Europe/London",
                temperature = 8.0, humidity = 65, weatherCondition = "Overcast"
            )
            locationLower.contains("new york") -> LocationData(
                name = "New York", region = "New York", country = "United States of America",
                lat = 40.71, lon = -74.01, timezone = "America/New_York",
                temperature = 12.0, humidity = 60, weatherCondition = "Clear"
            )
            locationLower.contains("tokyo") -> LocationData(
                name = "Tokyo", region = "Tokyo Prefecture", country = "Japan",
                lat = 35.69, lon = 139.69, timezone = "Asia/Tokyo",
                temperature = 15.0, humidity = 55, weatherCondition = "Partly cloudy"
            )
            locationLower.contains("paris") -> LocationData(
                name = "Paris", region = "Ile-de-France", country = "France",
                lat = 48.86, lon = 2.35, timezone = "Europe/Paris",
                temperature = 10.0, humidity = 68, weatherCondition = "Light rain"
            )
            locationLower.contains("sydney") -> LocationData(
                name = "Sydney", region = "New South Wales", country = "Australia",
                lat = -33.87, lon = 151.21, timezone = "Australia/Sydney",
                temperature = 22.0, humidity = 65, weatherCondition = "Sunny"
            )
            locationLower.contains("dubai") -> LocationData(
                name = "Dubai", region = "Dubai", country = "United Arab Emirates",
                lat = 25.20, lon = 55.27, timezone = "Asia/Dubai",
                temperature = 28.0, humidity = 45, weatherCondition = "Sunny"
            )
            locationLower.contains("singapore") -> LocationData(
                name = "Singapore", region = "Central Singapore", country = "Singapore",
                lat = 1.35, lon = 103.82, timezone = "Asia/Singapore",
                temperature = 30.0, humidity = 80, weatherCondition = "Thundery outbreaks"
            )
            locationLower.contains("mumbai") -> LocationData(
                name = "Mumbai", region = "Maharashtra", country = "India",
                lat = 19.07, lon = 72.88, timezone = "Asia/Kolkata",
                temperature = 29.0, humidity = 72, weatherCondition = "Humid"
            )
            locationLower.contains("moscow") -> LocationData(
                name = "Moscow", region = "Moscow", country = "Russia",
                lat = 55.76, lon = 37.62, timezone = "Europe/Moscow",
                temperature = -2.0, humidity = 85, weatherCondition = "Snow"
            )
            locationLower.contains("berlin") -> LocationData(
                name = "Berlin", region = "Berlin", country = "Germany",
                lat = 52.52, lon = 13.40, timezone = "Europe/Berlin",
                temperature = 6.0, humidity = 70, weatherCondition = "Cloudy"
            )
            locationLower.contains("madrid") -> LocationData(
                name = "Madrid", region = "Madrid", country = "Spain",
                lat = 40.42, lon = -3.70, timezone = "Europe/Madrid",
                temperature = 14.0, humidity = 55, weatherCondition = "Clear"
            )
            locationLower.contains("rome") -> LocationData(
                name = "Rome", region = "Lazio", country = "Italy",
                lat = 41.90, lon = 12.50, timezone = "Europe/Rome",
                temperature = 16.0, humidity = 60, weatherCondition = "Partly cloudy"
            )
            
            // Default fallback
            else -> {
                val parts = location.split(",")
                val cityName = parts.firstOrNull()?.trim() ?: "Unknown City"
                val countryName = parts.lastOrNull()?.trim() ?: "Unknown Country"
                
                LocationData(
                    name = cityName, region = cityName, country = countryName,
                    lat = 0.0, lon = 0.0, timezone = "UTC",
                    temperature = 20.0, humidity = 65, weatherCondition = "Clear"
                )
            }
        }
    }
    
    private fun getCurrentLocalTime(timezone: String): String {
        return try {
            val timeZone = java.util.TimeZone.getTimeZone(timezone)
            val calendar = java.util.Calendar.getInstance(timeZone)
            val formatter = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm", java.util.Locale.getDefault())
            formatter.timeZone = timeZone
            formatter.format(calendar.time)
        } catch (e: Exception) {
            "2024-12-12 15:30" // Fallback
        }
    }
    
    private fun isCurrentlyDay(timezone: String): Boolean {
        return try {
            val timeZone = java.util.TimeZone.getTimeZone(timezone)
            val calendar = java.util.Calendar.getInstance(timeZone)
            val hour = calendar.get(java.util.Calendar.HOUR_OF_DAY)
            hour in 6..18
        } catch (e: Exception) {
            true // Default to day
        }
    }
    
    private fun celsiusToFahrenheit(celsius: Double): Double {
        return celsius * 9.0 / 5.0 + 32.0
    }
}
