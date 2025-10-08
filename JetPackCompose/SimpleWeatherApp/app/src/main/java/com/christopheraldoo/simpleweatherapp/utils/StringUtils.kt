package com.christopheraldoo.simpleweatherapp.utils

import java.text.SimpleDateFormat
import java.util.*

/**
 * Extension functions for working with strings in the app
 */

/**
 * Capitalizes the first letter of the string
 */
fun String.capitalize(): String {
    return this.replaceFirstChar {
        if (it.isLowerCase())
            it.titlecase(Locale.getDefault())
        else
            it.toString()
    }
}

/**
 * Formats temperature with the appropriate unit symbol
 */
fun formatTemperature(value: Double, useImperial: Boolean): String {
    val temp = value.toInt()
    val unit = if (useImperial) "°F" else "°C"
    return "$temp$unit"
}

/**
 * Formats a timestamp into a human-readable time
 */
fun formatTime(timestamp: Long): String {
    val dateFormat = SimpleDateFormat("HH:mm", Locale.getDefault())
    return dateFormat.format(Date(timestamp * 1000))
}

/**
 * Formats a timestamp into a day name (e.g., "Monday")
 */
fun formatDayName(timestamp: Long): String {
    val dateFormat = SimpleDateFormat("EEEE", Locale.getDefault())
    return dateFormat.format(Date(timestamp * 1000))
}

/**
 * Formats a timestamp into a date string (e.g., "May 15")
 */
fun formatDate(timestamp: Long): String {
    val dateFormat = SimpleDateFormat("MMM d", Locale.getDefault())
    return dateFormat.format(Date(timestamp * 1000))
}
