package com.christopheraldoo.wavesoffood.data.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.christopheraldoo.wavesoffood.R

/**
 * Data class untuk representasi order/pesanan
 */
@Parcelize
data class Order(
    val id: String = "",
    val userId: String = "",
    val userEmail: String = "",
    val userName: String = "",
    val userPhone: String = "",
    val items: List<OrderItem> = emptyList(),
    val subtotal: Int = 0,
    val discount: Int = 0,
    val deliveryFee: Int = 0,
    val total: Int = 0,
    val orderType: OrderType = OrderType.TAKE_AWAY,
    val paymentMethod: PaymentMethod = PaymentMethod.CASH,
    val status: OrderStatus = OrderStatus.PENDING,
    val recipientName: String = "",
    val deliveryAddress: String = "",
    val notes: String = "",
    val createdAt: Long = System.currentTimeMillis(),
    val updatedAt: Long = System.currentTimeMillis(),
    val estimatedDeliveryTime: Long = 0L,
    val completedAt: Long = 0L
) : Parcelable {
    
    /**
     * Get formatted total price
     */
    fun getFormattedTotal(): String {
        return "Rp ${String.format("%,d", total)}"
    }
    
    /**
     * Get formatted subtotal
     */
    fun getFormattedSubtotal(): String {
        return "Rp ${String.format("%,d", subtotal)}"
    }
    
    /**
     * Get formatted delivery fee
     */
    fun getFormattedDeliveryFee(): String {
        return "Rp ${String.format("%,d", deliveryFee)}"
    }
    
    /**
     * Get formatted discount
     */
    fun getFormattedDiscount(): String {
        return "Rp ${String.format("%,d", discount)}"
    }
    
    /**
     * Get order status color hex
     */
    fun getStatusColorHex(): String {
        return when (status) {
            OrderStatus.PENDING -> "#FF9800"
            OrderStatus.CONFIRMED -> "#2196F3"
            OrderStatus.PREPARING -> "#9C27B0"
            OrderStatus.READY -> "#8BC34A"
            OrderStatus.DELIVERING -> "#1976D2"
            OrderStatus.COMPLETED -> "#4CAF50"
            OrderStatus.CANCELLED -> "#F44336"
        }
    }
    
    /**
     * Get order status text
     */
    fun getStatusText(): String {
        return when (status) {
            OrderStatus.PENDING -> "Menunggu Konfirmasi"
            OrderStatus.CONFIRMED -> "Dikonfirmasi"
            OrderStatus.PREPARING -> "Sedang Disiapkan"
            OrderStatus.READY -> "Siap Diambil"
            OrderStatus.DELIVERING -> "Sedang Dikirim"
            OrderStatus.COMPLETED -> "Selesai"
            OrderStatus.CANCELLED -> "Dibatalkan"
        }
    }
    
    /**
     * Get estimated delivery time formatted
     */
    fun getFormattedDeliveryTime(): String {
        if (estimatedDeliveryTime == 0L) return "TBD"
        val date = java.util.Date(estimatedDeliveryTime)
        val format = java.text.SimpleDateFormat("HH:mm, dd MMM yyyy", java.util.Locale.getDefault())
        return format.format(date)
    }
    
    /**
     * Get order created time formatted
     */
    fun getFormattedCreatedTime(): String {
        val date = java.util.Date(createdAt)
        val format = java.text.SimpleDateFormat("dd MMM yyyy, HH:mm", java.util.Locale.getDefault())
        return format.format(date)
    }
    
    /**
     * Check if order can be cancelled
     */
    fun canBeCancelled(): Boolean {
        return status == OrderStatus.PENDING || status == OrderStatus.CONFIRMED
    }
    
    /**
     * Check if order is active
     */
    fun isActive(): Boolean {
        return status != OrderStatus.COMPLETED && status != OrderStatus.CANCELLED
    }
}

@Parcelize
data class OrderItem(
    val menuId: String = "",
    val menuName: String = "",
    val menuImageUrl: String = "",
    val price: Int = 0,
    val quantity: Int = 1,
    val totalPrice: Int = 0,
    val category: String = "",
    val notes: String = ""
) : Parcelable {
    
    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,d", price)}"
    }
    
    fun getFormattedTotalPrice(): String {
        return "Rp ${String.format("%,d", totalPrice)}"
    }
}

enum class OrderType {
    TAKE_AWAY,
    DINE_IN
}

enum class PaymentMethod(val displayName: String) {
    CASH("Tunai"),
    QRIS("QRIS"),
    BANK_TRANSFER("Transfer Bank"),
    CREDIT_CARD("Kartu Kredit")
}

enum class OrderStatus {
    PENDING,
    CONFIRMED,
    PREPARING,
    READY,
    DELIVERING,
    COMPLETED,
    CANCELLED
}
