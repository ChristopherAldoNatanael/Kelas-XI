package com.christopheraldoo.petheal.ui.screens.payment

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.repository.PaymentRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

private const val TAG = "PaymentViewModel"

data class PaymentUiState(
    val isLoading: Boolean = false,
    val snapToken: String? = null,
    val snapRedirectUrl: String? = null,
    val transactionId: String? = null,
    val orderId: String? = null, // Store the order ID when Snap token is created
    val error: String? = null,
    val paymentResult: PaymentResult? = null,
    val isPaymentCompleted: Boolean = false,
    val isPaymentCanceled: Boolean = false
)

@HiltViewModel
class PaymentViewModel @Inject constructor(
    private val paymentRepository: PaymentRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PaymentUiState())
    val uiState: StateFlow<PaymentUiState> = _uiState.asStateFlow()
    
    private var currentOrderId: String? = null
    private var retryCount: Int = 0

    /**
     * Initiate payment by creating a Midtrans Snap token.
     * @param booking The booking to pay for
     * @param user The current user
     * @param isDpPayment Whether this is a DP (Down Payment) or full payment
     * @param totalAmount The amount to charge (in IDR)
     * @param bookingId The booking ID for the order ID
     */
    fun initiatePayment(
        booking: Booking,
        user: User?,
        isDpPayment: Boolean,
        totalAmount: Double,
        bookingId: Int
    ) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            // Generate order ID
            val orderId = "BOOKING-${bookingId}-${System.currentTimeMillis()}"

            // Calculate gross amount (ensure minimum 1 IDR for Midtrans)
            val grossAmount = totalAmount.toLong().coerceAtLeast(1)

            // DEBUG LOGGING - Remove after testing
            if (BuildConfig.DEBUG) {
                Log.d(TAG, "========== PAYMENT INITIATED ==========")
                Log.d(TAG, "Booking ID: $bookingId")
                Log.d(TAG, "Payment Type: ${if (isDpPayment) "DP (50%)" else "FULL (100%)"}")
                Log.d(TAG, "totalAmount parameter: $totalAmount")
                Log.d(TAG, "booking.dpAmount: ${booking.dpAmount}")
                Log.d(TAG, "booking.totalAmount: ${booking.totalAmount}")
                Log.d(TAG, "grossAmount sent to Midtrans: $grossAmount")
                Log.d(TAG, "=======================================")
            }

            // Build item details
            val paymentLabel = if (isDpPayment) "Down Payment - ${booking.pet?.name ?: "Pet"}" else "Full Payment - ${booking.pet?.name ?: "Pet"}"
            val itemDetails = listOf(
                ItemDetail(
                    id = "BOOKING-$bookingId",
                    price = grossAmount,
                    quantity = 1,
                    name = paymentLabel
                )
            )

            // Build customer details
            val customerDetails = CustomerDetails(
                firstName = user?.name ?: "Customer",
                email = user?.email,
                phone = user?.phone
            )

            // Build snap token request
            val request = SnapTokenRequest(
                transactionDetails = TransactionDetails(
                    orderId = orderId,
                    grossAmount = grossAmount
                ),
                customerDetails = customerDetails,
                itemDetails = itemDetails,
                enabledPayments = listOf(
                    "credit_card",
                    "gopay",
                    "shopeepay",
                    "bca_va",
                    "bni_va",
                    "bri_va",
                    "mandiri_va",
                    "permata_va",
                    "cimb_va",
                    "qris"
                ),
                creditCard = CreditCardConfig(secure = true)
            )

            Log.d(TAG, "Creating snap token for booking $bookingId, amount: $grossAmount, orderId: $orderId")

            when (val result = paymentRepository.createSnapToken(request)) {
                is Result.Success -> {
                    val token = result.data.token
                    val redirectUrl = result.data.redirectUrl
                    val transactionId = result.data.transactionId
                    Log.d(TAG, "Snap token created. Token: ${token?.take(10)}..., redirectUrl: $redirectUrl, orderId: $orderId")

                    // Store the order ID so it can be used when the payment callback is received
                    currentOrderId = orderId
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        snapToken = token,
                        snapRedirectUrl = redirectUrl,
                        transactionId = transactionId,
                        orderId = orderId // Store in UI state for WebView to use
                    )
                }
                is Result.Error -> {
                    Log.e(TAG, "Failed to create snap token: ${result.message}")
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                else -> Unit
            }
        }
    }

    /**
     * Handle the payment result from Midtrans.
     * @param orderId The order ID of the transaction
     * @param transactionStatus The status returned by Midtrans
     * @param paymentType The payment type used
     */
    fun handlePaymentResult(
        orderId: String,
        transactionStatus: String?,
        paymentType: String?
    ) {
        currentOrderId = orderId
        retryCount = 0
        
        viewModelScope.launch {
            val mappedStatus = paymentRepository.mapTransactionStatus(transactionStatus)
            val isSuccess = paymentRepository.isPaymentSuccessful(transactionStatus)
            val isPending = paymentRepository.isPaymentPending(transactionStatus)

            Log.d(TAG, "Payment result - orderId: $orderId, status: $transactionStatus, mapped: $mappedStatus")

            val appStatus = when {
                isSuccess -> "success"
                isPending -> "pending"
                else -> "failed"
            }

            // If status is unknown, poll the backend for transaction status
            if (transactionStatus == null || mappedStatus == "unknown") {
                Log.w(TAG, "Transaction status unknown, polling backend for orderId: $orderId")
                checkTransactionStatusWithFallback(orderId)
                return@launch
            }

            _uiState.value = _uiState.value.copy(
                paymentResult = PaymentResult(
                    orderId = orderId,
                    transactionId = null,
                    status = appStatus,
                    paymentType = paymentType,
                    grossAmount = 0,
                    message = when (appStatus) {
                        "success" -> "Payment successful! Your booking is confirmed."
                        "pending" -> "Payment is pending. Please complete the payment soon."
                        else -> "Payment failed. Please try again."
                    }
                ),
                isPaymentCompleted = true
            )
        }
    }

    /**
     * Check transaction status with automatic retry on failure.
     * Used as fallback when payment result doesn't contain status.
     * @param orderId The order ID to check
     * @param retryCount Current retry count (default: 3)
     */
    private fun checkTransactionStatusWithFallback(
        orderId: String,
        retryCount: Int = 0
    ) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            when (val result = paymentRepository.getTransactionStatus(orderId)) {
                is Result.Success -> {
                    val status = result.data.transactionStatus
                    val isSuccess = paymentRepository.isPaymentSuccessful(status)
                    val isPending = paymentRepository.isPaymentPending(status)

                    val appStatus = when {
                        isSuccess -> "success"
                        isPending -> "pending"
                        else -> "failed"
                    }

                    Log.d(TAG, "Transaction status poll success - status: $status, appStatus: $appStatus")

                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        paymentResult = PaymentResult(
                            orderId = orderId,
                            transactionId = result.data.transactionId,
                            status = appStatus,
                            paymentType = result.data.paymentType,
                            grossAmount = result.data.grossAmount?.toLongOrNull() ?: 0,
                            message = when (appStatus) {
                                "success" -> "Payment successful! Your booking is confirmed."
                                "pending" -> "Payment is pending. Please complete the payment soon."
                                else -> "Payment failed or expired."
                            }
                        ),
                        isPaymentCompleted = true
                    )
                }
                is Result.Error -> {
                    if (retryCount < 3) {
                        Log.w(TAG, "Transaction status check failed, retrying ($retryCount/3): ${result.message}")
                        // Retry after 2 seconds
                        kotlinx.coroutines.delay(2000)
                        checkTransactionStatusWithFallback(orderId, retryCount + 1)
                    } else {
                        Log.e(TAG, "Transaction status check failed after retries: ${result.message}")
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            paymentResult = PaymentResult(
                                orderId = orderId,
                                transactionId = null,
                                status = "failed",
                                paymentType = null,
                                grossAmount = 0,
                                message = "Unable to verify payment status. Please check your bookings."
                            ),
                            isPaymentCompleted = true
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    /**
     * Check the transaction status from Midtrans.
     * @param orderId The order ID to check
     */
    fun checkTransactionStatus(orderId: String) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            when (val result = paymentRepository.getTransactionStatus(orderId)) {
                is Result.Success -> {
                    val status = result.data.transactionStatus
                    val isSuccess = paymentRepository.isPaymentSuccessful(status)
                    val isPending = paymentRepository.isPaymentPending(status)

                    val appStatus = when {
                        isSuccess -> "success"
                        isPending -> "pending"
                        else -> "failed"
                    }

                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        paymentResult = PaymentResult(
                            orderId = orderId,
                            transactionId = result.data.transactionId,
                            status = appStatus,
                            paymentType = result.data.paymentType,
                            grossAmount = result.data.grossAmount?.toLongOrNull() ?: 0,
                            message = when (appStatus) {
                                "success" -> "Payment successful! Your booking is confirmed."
                                "pending" -> "Payment is pending. Please complete the payment soon."
                                else -> "Payment failed or expired."
                            }
                        ),
                        isPaymentCompleted = true
                    )
                }
                is Result.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                else -> Unit
            }
        }
    }

    /**
     * Clear the payment state and reset the UI.
     */
    fun clearPaymentState() {
        _uiState.value = PaymentUiState()
        currentOrderId = null
        retryCount = 0
    }

    /**
     * Dismiss the error message.
     */
    fun dismissError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
    
    /**
     * Cancel payment (user pressed back during payment).
     */
    fun cancelPayment() {
        Log.d(TAG, "Payment canceled by user")
        _uiState.value = _uiState.value.copy(
            isPaymentCanceled = true,
            isPaymentCompleted = false
        )
    }
    
    /**
     * Check payment status when user exits Midtrans (presses back/exit button).
     * This polls the backend to detect if payment was completed successfully.
     * @param bookingId The booking ID to construct order ID
     */
    fun checkPaymentStatusOnExit(bookingId: Int) {
        val orderId = currentOrderId ?: run {
            Log.w(TAG, "No order ID found, navigating back")
            return
        }
        
        Log.d(TAG, "Checking payment status on exit for orderId: $orderId")
        viewModelScope.launch {
            // Show loading while checking
            _uiState.value = _uiState.value.copy(isLoading = true)
            
            // Poll backend for transaction status
            when (val result = paymentRepository.getTransactionStatus(orderId)) {
                is Result.Success -> {
                    val status = result.data.transactionStatus
                    val isSuccess = paymentRepository.isPaymentSuccessful(status)
                    val isPending = paymentRepository.isPaymentPending(status)
                    
                    Log.d(TAG, "Payment status on exit: $status, success=$isSuccess, pending=$isPending")
                    
                    val appStatus = when {
                        isSuccess -> "success"
                        isPending -> "pending"
                        else -> "failed"
                    }
                    
                    // Show result dialog with the detected status
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        paymentResult = PaymentResult(
                            orderId = orderId,
                            transactionId = result.data.transactionId,
                            status = appStatus,
                            paymentType = result.data.paymentType,
                            grossAmount = result.data.grossAmount?.toLongOrNull() ?: 0,
                            message = when (appStatus) {
                                "success" -> "Payment successful! Your booking is confirmed."
                                "pending" -> "Payment is pending. Please complete the payment soon."
                                else -> "Payment was not completed. You can try again."
                            }
                        ),
                        isPaymentCompleted = true
                    )
                }
                is Result.Error -> {
                    Log.e(TAG, "Failed to get payment status: ${result.message}")
                    // If we can't check status, assume failed
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        paymentResult = PaymentResult(
                            orderId = orderId,
                            transactionId = null,
                            status = "failed",
                            paymentType = null,
                            grossAmount = 0,
                            message = "Unable to verify payment status. Please check your bookings."
                        ),
                        isPaymentCompleted = true
                    )
                }
                else -> Unit
            }
        }
    }
    
    /**
     * Manually refresh booking status after payment by polling backend.
     * Use this when callback doesn't contain transaction status.
     */
    fun refreshBookingStatus(bookingId: Int) {
        val orderId = currentOrderId ?: "BOOKING-$bookingId"
        Log.d(TAG, "Refreshing booking status for orderId: $orderId")
        checkTransactionStatusWithFallback(orderId, retryCount = 0)
    }
    
    /**
     * Fallback method for WebView-based payment flow.
     * Used when Midtrans SDK fails or is unavailable.
     */
    fun initiatePaymentFallback(
        booking: Booking,
        user: User?,
        isDpPayment: Boolean,
        totalAmount: Double,
        bookingId: Int
    ) {
        // Reuse the same initiatePayment logic
        initiatePayment(booking, user, isDpPayment, totalAmount, bookingId)
    }

    /**
     * Initiate payment for remaining balance (after DP).
     * @param bookingId The booking ID to pay remaining amount for
     * @param user The current user
     */
    fun initiateRemainingPayment(bookingId: Int, user: User?) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            // Generate order ID for remaining payment
            val orderId = "BOOKING-$bookingId-REMAINING-${System.currentTimeMillis()}"

            Log.d(TAG, "Creating snap token for remaining payment, booking: $bookingId, orderId: $orderId")

            when (val result = paymentRepository.createRemainingPaymentSnapToken(bookingId)) {
                is Result.Success -> {
                    val token = result.data.token
                    val redirectUrl = result.data.redirectUrl
                    val transactionId = result.data.transactionId
                    Log.d(TAG, "Snap token created for remaining payment. Token: ${token?.take(10)}..., redirectUrl: $redirectUrl")

                    // Store the order ID so it can be used when the payment callback is received
                    currentOrderId = orderId
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        snapToken = token,
                        snapRedirectUrl = redirectUrl,
                        transactionId = transactionId,
                        orderId = orderId
                    )
                }
                is Result.Error -> {
                    Log.e(TAG, "Failed to create snap token for remaining payment: ${result.message}")
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                else -> Unit
            }
        }
    }
}
