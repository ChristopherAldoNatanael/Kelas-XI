package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import android.util.Log
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import kotlinx.coroutines.delay
import retrofit2.Response
import java.io.IOException
import java.net.SocketTimeoutException

/**
 * Enhanced API Service dengan retry mechanism dan circuit breaker untuk mencegah server crash
 */
class EnhancedApiService(private val context: Context) {
    
    private val apiService = RetrofitClient.createApiService(context)
    private var circuitBreakerOpen = false
    private var lastFailureTime = 0L
    private var failureCount = 0
    
    companion object {
        private const val MAX_RETRIES = 2
        private const val RETRY_DELAY = 1000L // 1 second
        private const val CIRCUIT_BREAKER_THRESHOLD = 5
        private const val CIRCUIT_BREAKER_TIMEOUT = 30000L // 30 seconds
        private const val TAG = "EnhancedApiService"
    }
    
    /**
     * Get jadwal hari ini dengan ultra lightweight endpoint
     */
    suspend fun getJadwalHariIni(): Response<ApiResponse<List<JadwalHariIni>>> {
        return executeWithRetry { 
            apiService.siswaJadwalHariIni()
        }
    }
    
    /**
     * Get riwayat kehadiran dengan pagination
     */
    suspend fun getRiwayatKehadiran(
        page: Int = 1, 
        limit: Int = 10
    ): Response<ApiResponse<PaginatedResponse<List<RiwayatKehadiran>>>> {
        return executeWithRetry { 
            apiService.siswaRiwayatKehadiran(page, limit)
        }
    }
    
    /**
     * Get my schedule dengan timeout protection
     */
    suspend fun getMySchedule(page: Int = 1): Response<ApiResponse<PaginatedResponse<List<Schedule>>>> {
        return executeWithRetry { 
            apiService.getMyClassSchedule(page)
        }
    }
    
    /**
     * Submit kehadiran
     */
    suspend fun submitKehadiran(request: KehadiranRequest): Response<ApiResponse<Any>> {
        return executeWithRetry { 
            apiService.submitKehadiran(request)
        }
    }
    
    /**
     * Get today status
     */
    suspend fun getTodayStatus(): Response<ApiResponse<TodayStatusResponse>> {
        return executeWithRetry { 
            apiService.getTodayStatus()
        }
    }
    
    /**
     * Execute API call dengan retry mechanism dan circuit breaker
     */
    private suspend inline fun <T> executeWithRetry(
        crossinline apiCall: suspend () -> Response<T>
    ): Response<T> {
        
        // Check circuit breaker
        if (circuitBreakerOpen) {
            val currentTime = System.currentTimeMillis()
            if (currentTime - lastFailureTime < CIRCUIT_BREAKER_TIMEOUT) {
                Log.w(TAG, "Circuit breaker is OPEN, rejecting request")
                throw CircuitBreakerOpenException("Circuit breaker is open. Try again later.")
            } else {
                // Half-open state - allow one request
                circuitBreakerOpen = false
                failureCount = 0
                Log.i(TAG, "Circuit breaker moving to HALF-OPEN state")
            }
        }
        
        var lastException: Exception? = null
        
        repeat(MAX_RETRIES + 1) { attempt ->
            try {
                Log.d(TAG, "API call attempt ${attempt + 1}")
                
                val response = apiCall()
                
                // Success - reset failure count
                if (response.isSuccessful) {
                    failureCount = 0
                    Log.d(TAG, "API call successful on attempt ${attempt + 1}")
                    return response
                }
                
                // Server error - count as failure
                if (response.code() >= 500) {
                    recordFailure()
                    Log.w(TAG, "Server error ${response.code()} on attempt ${attempt + 1}")
                } else {
                    // Client error - don't retry
                    Log.w(TAG, "Client error ${response.code()}, not retrying")
                    return response
                }
                
            } catch (e: SocketTimeoutException) {
                recordFailure()
                lastException = e
                Log.w(TAG, "Timeout on attempt ${attempt + 1}: ${e.message}")
                
            } catch (e: IOException) {
                recordFailure()
                lastException = e
                Log.w(TAG, "Network error on attempt ${attempt + 1}: ${e.message}")
                
                // Try backup URL
                if (attempt == 0) {
                    RetrofitClient.markConnectionFailureAndFlipBaseUrl(context)
                }
                
            } catch (e: Exception) {
                recordFailure()
                lastException = e
                Log.e(TAG, "Unexpected error on attempt ${attempt + 1}: ${e.message}")
            }
            
            // Wait before retry (except on last attempt)
            if (attempt < MAX_RETRIES) {
                val delayTime = RETRY_DELAY * (attempt + 1) // Exponential backoff
                Log.d(TAG, "Waiting ${delayTime}ms before retry...")
                delay(delayTime)
            }
        }
        
        // All retries failed
        Log.e(TAG, "All retries failed. Last exception: ${lastException?.message}")
        throw lastException ?: Exception("API call failed after $MAX_RETRIES retries")
    }
    
    /**
     * Record failure dan check circuit breaker threshold
     */
    private fun recordFailure() {
        failureCount++
        lastFailureTime = System.currentTimeMillis()
        
        if (failureCount >= CIRCUIT_BREAKER_THRESHOLD) {
            circuitBreakerOpen = true
            Log.w(TAG, "Circuit breaker OPENED due to $failureCount failures")
        }
    }
    
    /**
     * Reset circuit breaker (untuk testing atau manual reset)
     */
    fun resetCircuitBreaker() {
        circuitBreakerOpen = false
        failureCount = 0
        lastFailureTime = 0L
        Log.i(TAG, "Circuit breaker manually reset")
    }
    
    /**
     * Check circuit breaker status
     */
    fun isCircuitBreakerOpen(): Boolean = circuitBreakerOpen
}

/**
 * Custom exception untuk circuit breaker
 */
class CircuitBreakerOpenException(message: String) : Exception(message)

/**
 * Data classes untuk lightweight endpoints
 */
data class JadwalHariIni(
    val periode: Int,
    val waktu: String,
    val mapel: String?,
    val guru: String?
)

data class RiwayatKehadiran(
    val tanggal: String,
    val guru_hadir: Boolean,
    val mapel: String?,
    val periode: Int
)

data class PaginatedResponse<T>(
    val data: T,
    val pagination: PaginationMeta
)

data class PaginationMeta(
    val current_page: Int,
    val total: Int,
    val has_more: Boolean
)
