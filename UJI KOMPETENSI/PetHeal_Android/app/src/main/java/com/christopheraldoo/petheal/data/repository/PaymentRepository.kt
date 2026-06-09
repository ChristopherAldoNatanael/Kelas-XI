package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton

private const val TAG = "PaymentRepository"

@Singleton
class PaymentRepository @Inject constructor(
    private val apiService: ApiService
) {
    private fun extractErrorMessage(response: retrofit2.Response<*>): String? {
        val errorBody = runCatching { response.errorBody()?.string() }.getOrNull()?.trim().orEmpty()
        if (errorBody.isBlank()) return null

        return runCatching {
            if (errorBody.startsWith("{")) {
                val json = JSONObject(errorBody)
                when {
                    json.optString("detail").isNotBlank() -> json.optString("detail")
                    json.optString("message").isNotBlank() -> json.optString("message")
                    json.optString("error").isNotBlank() -> json.optString("error")
                    else -> errorBody
                }
            } else {
                errorBody
            }
        }.getOrDefault(errorBody)
    }

    /**
     * Create a Midtrans Snap token for a booking payment.
     */
    suspend fun checkPaymentPreflight(): Result<PaymentPreflightData> {
        return try {
            val response = apiService.checkPaymentPreflight()
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data?.ready == true) {
                Result.Success(body.data)
            } else {
                Result.Error(
                    extractErrorMessage(response)
                        ?: body?.message
                        ?: "Payment setup is not ready. Please check Midtrans configuration."
                )
            }
        } catch (e: Exception) {
            Log.e(TAG, "Payment preflight exception", e)
            Result.Error("Unable to verify payment setup: ${e.message}")
        }
    }

    suspend fun createSnapToken(request: SnapTokenRequest): Result<SnapTokenData> {
        return try {
            if (BuildConfig.DEBUG) {
                Log.d(TAG, "Creating snap token for order: ${request.transactionDetails.orderId}")
            }
            val response = apiService.createSnapToken(request)
            Log.d(TAG, "Response code: ${response.code()}, isSuccessful: ${response.isSuccessful}")

            val rawBody = response.body()
            Log.d(TAG, "Response body: success=${rawBody?.success}, message=${rawBody?.message}")

            if (response.isSuccessful) {
                if (rawBody?.success == true) {
                    val data = rawBody.data
                    if (data != null && (data.token != null || data.redirectUrl != null)) {
                        Log.d(TAG, "Snap token created successfully")
                        Result.Success(data)
                    } else {
                        Result.Error("Failed to create payment token: No token or redirect URL received")
                    }
                } else {
                    Result.Error(rawBody?.message ?: "Backend returned error")
                }
            } else {
                val detailMessage = extractErrorMessage(response)

                val errorMsg = when (response.code()) {
                    401 -> "Authentication failed. Please login again."
                    422 -> "Invalid payment data. Please check your booking details."
                    502 -> "Payment gateway configuration error. Please contact support."
                    500 -> detailMessage ?: "Server error. Please try again later."
                    else -> detailMessage ?: rawBody?.message ?: "Failed to create payment token (HTTP ${response.code()})"
                }
                Log.e(TAG, "HTTP error: $errorMsg, code: ${response.code()}")
                Result.Error(errorMsg)
            }
        } catch (e: java.net.UnknownHostException) {
            Result.Error("Cannot connect to server. Please check your internet connection.")
        } catch (e: java.net.SocketTimeoutException) {
            Result.Error("Connection timeout. Please try again.")
        } catch (e: Exception) {
            Log.e(TAG, "Exception creating snap token", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Get the transaction status from Midtrans via the backend.
     */
    suspend fun getTransactionStatus(orderId: String): Result<TransactionStatusResponse> {
        return try {
            Log.d(TAG, "Checking transaction status for order: $orderId")
            val response = apiService.getTransactionStatus(orderId)

            if (response.isSuccessful && response.body() != null) {
                val statusResponse = response.body()!!
                Log.d(TAG, "Transaction status: ${statusResponse.transactionStatus}")
                Result.Success(statusResponse)
            } else {
                Log.e(TAG, "getTransactionStatus failed: HTTP ${response.code()}")
                Result.Error("Failed to get transaction status")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getTransactionStatus exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Synchronize booking payment status with the backend after Midtrans success.
     */
    suspend fun syncBookingPaymentStatus(orderId: String): Result<BookingPaymentStatusResponse> {
        return try {
            Log.d(TAG, "Synchronizing booking payment status for order: $orderId")
            val response = apiService.syncPaymentStatus(PaymentSyncRequest(orderId))

            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()!!)
            } else {
                val error = extractErrorMessage(response) ?: response.body()?.message ?: "Failed to sync payment status"
                Log.e(TAG, "syncBookingPaymentStatus failed: HTTP ${response.code()} - $error")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "syncBookingPaymentStatus exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Create a Snap token for remaining payment (after DP).
     */
    suspend fun createRemainingPaymentSnapToken(bookingId: Int): Result<SnapTokenData> {
        return try {
            Log.d(TAG, "Creating remaining payment snap token for booking: $bookingId")
            val response = apiService.createRemainingPaymentSnapToken(bookingId)

            if (response.isSuccessful) {
                val rawBody = response.body()
                if (rawBody?.success == true && rawBody.data != null) {
                    Result.Success(rawBody.data)
                } else {
                    Result.Error(rawBody?.message ?: "Failed to create remaining payment token")
                }
            } else {
                val detailMessage = extractErrorMessage(response)
                Log.e(TAG, "createRemainingPaymentSnapToken failed: HTTP ${response.code()}")
                Result.Error(detailMessage ?: "Failed to create remaining payment token")
            }
        } catch (e: Exception) {
            Log.e(TAG, "createRemainingPaymentSnapToken exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Get booking payment status.
     */
    suspend fun getBookingPaymentStatus(bookingId: Int): Result<BookingPaymentStatusResponse> {
        return try {
            Log.d(TAG, "Getting payment status for booking: $bookingId")
            val response = apiService.getBookingPaymentStatus(bookingId)

            if (response.isSuccessful && response.body() != null) {
                Result.Success(response.body()!!)
            } else {
                Log.e(TAG, "getBookingPaymentStatus failed: HTTP ${response.code()}")
                Result.Error("Failed to get payment status")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getBookingPaymentStatus exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Helper: Check if transaction status indicates successful payment
     */
    fun isPaymentSuccessful(status: String?): Boolean {
        return status == "settlement" || status == "capture"
    }

    /**
     * Helper: Check if transaction status indicates pending payment
     */
    fun isPaymentPending(status: String?): Boolean {
        return status == "pending" || status == "authorize"
    }

    /**
     * Helper: Map raw transaction status string to app-friendly status
     */
    fun mapTransactionStatus(rawStatus: String?): String {
        return when (rawStatus?.lowercase()) {
            "settlement", "capture" -> "success"
            "pending", "authorize" -> "pending"
            "cancel", "expire", "deny", "failure" -> "failed"
            else -> "unknown"
        }
    }
}
