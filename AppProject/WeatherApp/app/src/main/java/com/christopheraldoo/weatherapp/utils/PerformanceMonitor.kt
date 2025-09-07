package com.christopheraldoo.weatherapp.utils

import android.content.Context
import android.util.Log
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.system.measureTimeMillis

private val Context.performanceDataStore by preferencesDataStore(name = "performance_metrics")

@Singleton
class PerformanceMonitor @Inject constructor(
    private val context: Context
) {
    companion object {
        private const val TAG = "WeatherApp_Performance"
        
        // Performance metric keys
        private val API_RESPONSE_TIME = longPreferencesKey("api_response_time")
        private val CACHE_HIT_RATE = floatPreferencesKey("cache_hit_rate")
        private val NETWORK_REQUESTS_COUNT = longPreferencesKey("network_requests_count")
        private val CACHE_HITS_COUNT = longPreferencesKey("cache_hits_count")
        private val APP_START_TIME = longPreferencesKey("app_start_time")
        private val LOCATION_ACCESS_TIME = longPreferencesKey("location_access_time")
    }
    
    private val dataStore = context.performanceDataStore
    
    // API Performance Tracking
    suspend fun <T> measureApiCall(
        operation: String,
        apiCall: suspend () -> T
    ): T {
        val startTime = System.currentTimeMillis()
        return try {
            val result = apiCall()
            val duration = System.currentTimeMillis() - startTime
            
            Log.d(TAG, "$operation completed in ${duration}ms")
            recordApiResponseTime(duration)
            incrementNetworkRequestCount()
            
            result
        } catch (e: Exception) {
            val duration = System.currentTimeMillis() - startTime
            Log.e(TAG, "$operation failed after ${duration}ms", e)
            throw e
        }
    }
    
    // Cache Performance Tracking
    suspend fun recordCacheHit() {
        dataStore.edit { preferences ->
            val currentHits = preferences[CACHE_HITS_COUNT] ?: 0L
            preferences[CACHE_HITS_COUNT] = currentHits + 1
        }
        updateCacheHitRate()
        Log.d(TAG, "Cache hit recorded")
    }
    
    suspend fun recordCacheMiss() {
        incrementNetworkRequestCount()
        updateCacheHitRate()
        Log.d(TAG, "Cache miss recorded")
    }
    
    // App Performance Metrics
    suspend fun recordAppStartTime(startTimeMillis: Long) {
        val currentTime = System.currentTimeMillis()
        val startupDuration = currentTime - startTimeMillis
        
        dataStore.edit { preferences ->
            preferences[APP_START_TIME] = startupDuration
        }
        
        Log.d(TAG, "App started in ${startupDuration}ms")
    }
    
    suspend fun recordLocationAccessTime(accessTimeMillis: Long) {
        dataStore.edit { preferences ->
            preferences[LOCATION_ACCESS_TIME] = accessTimeMillis
        }
        
        Log.d(TAG, "Location accessed in ${accessTimeMillis}ms")
    }
    
    // Performance Metrics Getters
    fun getApiResponseTime(): Flow<Long> {
        return dataStore.data.map { preferences ->
            preferences[API_RESPONSE_TIME] ?: 0L
        }
    }
    
    fun getCacheHitRate(): Flow<Float> {
        return dataStore.data.map { preferences ->
            preferences[CACHE_HIT_RATE] ?: 0f
        }
    }
    
    fun getNetworkRequestsCount(): Flow<Long> {
        return dataStore.data.map { preferences ->
            preferences[NETWORK_REQUESTS_COUNT] ?: 0L
        }
    }
    
    fun getAppStartTime(): Flow<Long> {
        return dataStore.data.map { preferences ->
            preferences[APP_START_TIME] ?: 0L
        }
    }
    
    suspend fun getPerformanceReport(): PerformanceReport {
        val preferences = dataStore.data.first()
        
        return PerformanceReport(
            averageApiResponseTime = preferences[API_RESPONSE_TIME] ?: 0L,
            cacheHitRate = preferences[CACHE_HIT_RATE] ?: 0f,
            totalNetworkRequests = preferences[NETWORK_REQUESTS_COUNT] ?: 0L,
            totalCacheHits = preferences[CACHE_HITS_COUNT] ?: 0L,
            appStartTime = preferences[APP_START_TIME] ?: 0L,
            locationAccessTime = preferences[LOCATION_ACCESS_TIME] ?: 0L
        )
    }
    
    // Performance Optimization Recommendations
    suspend fun getOptimizationRecommendations(): List<OptimizationRecommendation> {
        val report = getPerformanceReport()
        val recommendations = mutableListOf<OptimizationRecommendation>()
        
        // API Response Time Analysis
        if (report.averageApiResponseTime > 5000) {
            recommendations.add(
                OptimizationRecommendation(
                    type = OptimizationType.API_PERFORMANCE,
                    title = "Slow API Response",
                    description = "API calls are taking longer than expected. Consider using cached data when available.",
                    priority = RecommendationPriority.HIGH
                )
            )
        }
        
        // Cache Hit Rate Analysis
        if (report.cacheHitRate < 0.7f) {
            recommendations.add(
                OptimizationRecommendation(
                    type = OptimizationType.CACHE_EFFICIENCY,
                    title = "Low Cache Hit Rate",
                    description = "Cache hit rate is below 70%. Consider increasing cache retention time.",
                    priority = RecommendationPriority.MEDIUM
                )
            )
        }
        
        // App Startup Time Analysis
        if (report.appStartTime > 3000) {
            recommendations.add(
                OptimizationRecommendation(
                    type = OptimizationType.STARTUP_TIME,
                    title = "Slow App Startup",
                    description = "App is taking longer than 3 seconds to start. Consider lazy initialization.",
                    priority = RecommendationPriority.HIGH
                )
            )
        }
        
        // Location Access Time Analysis
        if (report.locationAccessTime > 10000) {
            recommendations.add(
                OptimizationRecommendation(
                    type = OptimizationType.LOCATION_ACCESS,
                    title = "Slow Location Access",
                    description = "Location services are taking too long. Consider using last known location.",
                    priority = RecommendationPriority.MEDIUM
                )
            )
        }
        
        return recommendations
    }
    
    // Clear performance data
    suspend fun clearPerformanceData() {
        dataStore.edit { preferences ->
            preferences.clear()
        }
        Log.d(TAG, "Performance data cleared")
    }
    
    private suspend fun recordApiResponseTime(responseTime: Long) {
        dataStore.edit { preferences ->
            val currentAverage = preferences[API_RESPONSE_TIME] ?: 0L
            val newAverage = if (currentAverage == 0L) {
                responseTime
            } else {
                (currentAverage + responseTime) / 2
            }
            preferences[API_RESPONSE_TIME] = newAverage
        }
    }
    
    private suspend fun incrementNetworkRequestCount() {
        dataStore.edit { preferences ->
            val currentCount = preferences[NETWORK_REQUESTS_COUNT] ?: 0L
            preferences[NETWORK_REQUESTS_COUNT] = currentCount + 1
        }
    }
    
    private suspend fun updateCacheHitRate() {
        dataStore.edit { preferences ->
            val cacheHits = preferences[CACHE_HITS_COUNT] ?: 0L
            val networkRequests = preferences[NETWORK_REQUESTS_COUNT] ?: 0L
            val totalRequests = cacheHits + networkRequests
            
            val hitRate = if (totalRequests > 0) {
                cacheHits.toFloat() / totalRequests.toFloat()
            } else {
                0f
            }
            
            preferences[CACHE_HIT_RATE] = hitRate
        }
    }
}

// Data Classes
data class PerformanceReport(
    val averageApiResponseTime: Long,
    val cacheHitRate: Float,
    val totalNetworkRequests: Long,
    val totalCacheHits: Long,
    val appStartTime: Long,
    val locationAccessTime: Long
) {
    val totalRequests: Long get() = totalNetworkRequests + totalCacheHits
    
    fun getPerformanceGrade(): PerformanceGrade {
        val score = calculatePerformanceScore()
        return when {
            score >= 90 -> PerformanceGrade.EXCELLENT
            score >= 80 -> PerformanceGrade.GOOD
            score >= 70 -> PerformanceGrade.AVERAGE
            score >= 60 -> PerformanceGrade.POOR
            else -> PerformanceGrade.CRITICAL
        }
    }
    
    private fun calculatePerformanceScore(): Int {
        var score = 100
        
        // API response time penalty
        if (averageApiResponseTime > 3000) score -= 20
        else if (averageApiResponseTime > 2000) score -= 10
        else if (averageApiResponseTime > 1000) score -= 5
        
        // Cache hit rate bonus/penalty
        score += (cacheHitRate * 20).toInt() - 10
        
        // App start time penalty
        if (appStartTime > 3000) score -= 15
        else if (appStartTime > 2000) score -= 10
        else if (appStartTime > 1000) score -= 5
        
        // Location access penalty
        if (locationAccessTime > 10000) score -= 10
        else if (locationAccessTime > 5000) score -= 5
        
        return score.coerceIn(0, 100)
    }
}

enum class PerformanceGrade(val displayName: String, val color: String) {
    EXCELLENT("Excellent", "#2ECC71"),
    GOOD("Good", "#3498DB"),
    AVERAGE("Average", "#F39C12"),
    POOR("Poor", "#E74C3C"),
    CRITICAL("Critical", "#8E44AD")
}

data class OptimizationRecommendation(
    val type: OptimizationType,
    val title: String,
    val description: String,
    val priority: RecommendationPriority
)

enum class OptimizationType {
    API_PERFORMANCE,
    CACHE_EFFICIENCY,
    STARTUP_TIME,
    LOCATION_ACCESS,
    MEMORY_USAGE,
    BATTERY_OPTIMIZATION
}

enum class RecommendationPriority {
    LOW,
    MEDIUM,
    HIGH,
    CRITICAL
}

// Extension functions for easy performance measurement
suspend fun <T> PerformanceMonitor.measureTime(
    operationName: String,
    operation: suspend () -> T
): T {
    return measureApiCall(operationName, operation)
}

// Utility functions for performance analysis
object PerformanceUtils {
    fun formatResponseTime(milliseconds: Long): String {
        return when {
            milliseconds < 1000 -> "${milliseconds}ms"
            milliseconds < 60000 -> "${milliseconds / 1000}.${(milliseconds % 1000) / 100}s"
            else -> "${milliseconds / 60000}m ${(milliseconds % 60000) / 1000}s"
        }
    }
    
    fun formatCacheHitRate(rate: Float): String {
        return "${(rate * 100).toInt()}%"
    }
    
    fun isPerformanceAcceptable(report: PerformanceReport): Boolean {
        return report.averageApiResponseTime < 5000 &&
                report.cacheHitRate > 0.6f &&
                report.appStartTime < 4000
    }
}
