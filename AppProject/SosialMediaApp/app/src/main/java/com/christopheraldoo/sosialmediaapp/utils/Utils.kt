package com.christopheraldoo.sosialmediaapp.utils

import java.text.SimpleDateFormat
import java.util.*

/**
 * Object helper untuk utility functions
 * Berisi fungsi-fungsi bantuan yang digunakan di seluruh aplikasi
 */
object Utils {
    
    /**
     * Mengkonversi timestamp menjadi format waktu yang mudah dibaca
     * Contoh: "2h", "1d", "3m"
     */
    fun formatTimeAgo(timestamp: Long): String {
        val currentTime = System.currentTimeMillis()
        val timeDiff = currentTime - timestamp
        
        val seconds = timeDiff / 1000
        val minutes = seconds / 60
        val hours = minutes / 60
        val days = hours / 24
        
        return when {
            seconds < 60 -> "${seconds}s"
            minutes < 60 -> "${minutes}m"
            hours < 24 -> "${hours}h"
            days < 7 -> "${days}d"
            else -> {
                val date = Date(timestamp)
                SimpleDateFormat("MMM dd", Locale.getDefault()).format(date)
            }
        }
    }
    
    /**
     * Alias untuk formatTimeAgo untuk konsistensi
     */
    fun getRelativeTime(timestamp: Long): String {
        return formatTimeAgo(timestamp)
    }
    
    /**
     * Mengkonversi angka menjadi format yang mudah dibaca
     * Contoh: 1200 -> "1.2K", 1500000 -> "1.5M"
     */
    fun formatCount(count: Int): String {
        return when {
            count < 1000 -> count.toString()
            count < 1000000 -> String.format("%.1fK", count / 1000.0).replace(".0", "")
            else -> String.format("%.1fM", count / 1000000.0).replace(".0", "")
        }
    }
    
    /**
     * Generate inisial dari nama untuk placeholder profile image
     */
    fun getInitials(displayName: String): String {
        return displayName.split(" ")
            .take(2)
            .map { it.first().uppercaseChar() }
            .joinToString("")
    }
}
