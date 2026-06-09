package com.christopheraldoo.petheal.data.model

import com.google.gson.annotations.SerializedName

// ============= MIDTRANS SNAP TOKEN MODELS =============

data class SnapTokenRequest(
    @SerializedName("transaction_details")
    val transactionDetails: TransactionDetails,
    @SerializedName("customer_details")
    val customerDetails: CustomerDetails? = null,
    @SerializedName("item_details")
    val itemDetails: List<ItemDetail>? = null,
    @SerializedName("enabled_payments")
    val enabledPayments: List<String>? = null,
    @SerializedName("credit_card")
    val creditCard: CreditCardConfig? = null,
    @SerializedName("callbacks")
    val callbacks: CallbackConfig? = null
)

data class TransactionDetails(
    @SerializedName("order_id")
    val orderId: String,
    @SerializedName("gross_amount")
    val grossAmount: Long
)

data class CustomerDetails(
    @SerializedName("first_name")
    val firstName: String? = null,
    @SerializedName("email")
    val email: String? = null,
    val phone: String? = null
)

data class ItemDetail(
    val id: String,
    val price: Long,
    @SerializedName("quantity")
    val quantity: Int,
    val name: String
)

data class CreditCardConfig(
    @SerializedName("secure")
    val secure: Boolean = true
)

data class CallbackConfig(
    @SerializedName("finish")
    val finish: String? = null
)

data class SnapTokenResponse(
    val success: Boolean,
    val message: String? = null,
    val data: SnapTokenData? = null
)

data class SnapTokenData(
    @SerializedName("token")
    val token: String? = null,
    @SerializedName("redirect_url")
    val redirectUrl: String? = null,
    @SerializedName("transaction_id")
    val transactionId: String? = null
)

data class PaymentPreflightResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PaymentPreflightData? = null
)

data class PaymentPreflightData(
    val ready: Boolean = false,
    val mode: String? = null,
    val checks: Map<String, PaymentPreflightCheck>? = null
)

data class PaymentPreflightCheck(
    val status: String? = null,
    val message: String? = null,
    @SerializedName("status_code")
    val statusCode: Int? = null
)

data class PaymentSyncRequest(
    @SerializedName("order_id")
    val orderId: String
)

// ============= MIDTRANS TRANSACTION STATUS MODELS =============

data class TransactionStatusResponse(
    @SerializedName("transaction_id")
    val transactionId: String? = null,
    @SerializedName("order_id")
    val orderId: String? = null,
    @SerializedName("gross_amount")
    val grossAmount: String? = null,
    @SerializedName("payment_type")
    val paymentType: String? = null,
    @SerializedName("transaction_time")
    val transactionTime: String? = null,
    @SerializedName("transaction_status")
    val transactionStatus: String? = null, // "capture", "settlement", "pending", "deny", "cancel", "expire", "failure"
    @SerializedName("fraud_status")
    val fraudStatus: String? = null,
    @SerializedName("status_code")
    val statusCode: String? = null,
    @SerializedName("status_message")
    val statusMessage: String? = null,
    @SerializedName("va_numbers")
    val vaNumbers: List<VaNumber>? = null
)

data class VaNumber(
    val bank: String? = null,
    @SerializedName("va_number")
    val vaNumber: String? = null
)

// ============= PAYMENT RESULT MODELS =============

/**
 * Represents the result of a Midtrans payment transaction.
 * status can be: "success", "failed", "pending", "cancelled"
 */
data class PaymentResult(
    val orderId: String,
    val transactionId: String?,
    val status: String,         // "success", "failed", "pending", "cancelled"
    val paymentType: String?,
    val grossAmount: Long,
    val message: String? = null
)

// ============= BOOKING PAYMENT STATUS MODEL =============

/**
 * Response model for booking payment status endpoint
 */
data class BookingPaymentStatusResponse(
    val success: Boolean,
    val message: String? = null,
    val data: BookingPaymentStatusData? = null
)

data class BookingPaymentStatusData(
    val id: Int,
    @SerializedName("payment_type")
    val paymentType: String? = null,  // "dp" or "full"
    @SerializedName("payment_status")
    val paymentStatus: String? = null,  // "dp_paid", "paid", "pending", "failed"
    @SerializedName("total_amount")
    val totalAmount: Double? = null,
    @SerializedName("dp_amount")
    val dpAmount: Double? = null,
    @SerializedName("paid_amount")
    val paidAmount: Double? = null,
    @SerializedName("remaining_amount")
    val remainingAmount: Double? = null,
    @SerializedName("payment_date")
    val paymentDate: String? = null
)
