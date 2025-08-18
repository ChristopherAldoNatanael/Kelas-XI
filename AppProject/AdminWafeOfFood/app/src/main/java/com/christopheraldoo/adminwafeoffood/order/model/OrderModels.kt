package com.christopheraldoo.adminwafeoffood.order.model

import java.text.SimpleDateFormat
import java.util.*

// =================================================================================
// ORDER MODELS - Firebase Realtime Database
// =================================================================================

data class Order(
    val id: String = "",
    val customerId: String = "",
    val customerName: String = "",
    val customerEmail: String = "",
    val customerPhone: String = "",
    val customerAddress: String = "",
    val items: List<OrderItem> = emptyList(),
    val totalAmount: Double = 0.0,
    val status: OrderStatus = OrderStatus.INCOMING,
    val paymentMethod: String = "",
    val paymentStatus: PaymentStatus = PaymentStatus.PENDING,
    val notes: String = "",
    val estimatedTime: Long = 0L,
    val createdAt: Long = System.currentTimeMillis(),
    val updatedAt: Long = System.currentTimeMillis()
) {
    // Empty constructor untuk Firebase
    constructor() : this("", "", "", "", "", "", emptyList(), 0.0, OrderStatus.INCOMING, "", PaymentStatus.PENDING, "", 0L, 0L, 0L)

    fun getFormattedTotal(): String {
        return "Rp ${String.format("%,.0f", totalAmount)}"
    }

    fun getFormattedCreatedDate(): String {
        val sdf = SimpleDateFormat("dd/MM/yyyy HH:mm", Locale.getDefault())
        return sdf.format(Date(createdAt))
    }

    fun getStatusDisplayName(): String {
        return when (status) {
            OrderStatus.INCOMING -> "Pesanan Masuk"
            OrderStatus.CONFIRMED -> "Dikonfirmasi"
            OrderStatus.IN_PROGRESS -> "Sedang Diproses"
            OrderStatus.READY -> "Siap Diambil"
            OrderStatus.COMPLETED -> "Selesai"
            OrderStatus.CANCELLED -> "Dibatalkan"
        }
    }

    fun getStatusColor(): String {
        return when (status) {
            OrderStatus.INCOMING -> "#FF9800"
            OrderStatus.CONFIRMED -> "#2196F3"
            OrderStatus.IN_PROGRESS -> "#22C55E"
            OrderStatus.READY -> "#4CAF50"
            OrderStatus.COMPLETED -> "#4CAF50"
            OrderStatus.CANCELLED -> "#F44336"
        }
    }

    fun getTotalItems(): Int = items.sumOf { it.quantity }

    fun isActive(): Boolean {
        return status in listOf(OrderStatus.INCOMING, OrderStatus.CONFIRMED, OrderStatus.IN_PROGRESS, OrderStatus.READY)
    }

    fun isCompleted(): Boolean {
        return status == OrderStatus.COMPLETED
    }

    fun isCancelled(): Boolean {
        return status == OrderStatus.CANCELLED
    }    // Convert to Map untuk Firebase
    fun toMap(): Map<String, Any> {
        return mapOf(
            "id" to id,
            "customerId" to customerId,
            "customerName" to customerName,
            "customerEmail" to customerEmail,
            "customerPhone" to customerPhone,
            "customerAddress" to customerAddress,
            "items" to items.map { it.toMap() },
            "totalAmount" to totalAmount,
            "status" to status.name,
            "paymentMethod" to paymentMethod,
            "paymentStatus" to paymentStatus.name,
            "notes" to notes,
            "estimatedTime" to estimatedTime,
            "createdAt" to createdAt,
            "updatedAt" to updatedAt
        )
    }    companion object {
        fun fromMap(map: Map<String, Any>, id: String = ""): Order? {
            return try {
                val itemsList = (map["items"] as? List<Map<String, Any>>)?.mapNotNull { 
                    OrderItem.fromMap(it) 
                } ?: emptyList()

                Order(
                    id = id.ifEmpty { map["id"] as? String ?: "" },
                    customerId = map["customerId"] as? String ?: "",
                    customerName = map["customerName"] as? String ?: "",
                    customerEmail = map["customerEmail"] as? String ?: "",
                    customerPhone = map["customerPhone"] as? String ?: "",
                    customerAddress = map["customerAddress"] as? String ?: "",
                    items = itemsList,
                    totalAmount = (map["totalAmount"] as? Number)?.toDouble() ?: 0.0,
                    status = try { 
                        OrderStatus.valueOf(map["status"] as? String ?: "INCOMING") 
                    } catch (e: Exception) { 
                        OrderStatus.INCOMING 
                    },
                    paymentMethod = map["paymentMethod"] as? String ?: "",
                    paymentStatus = try { 
                        PaymentStatus.valueOf(map["paymentStatus"] as? String ?: "PENDING") 
                    } catch (e: Exception) { 
                        PaymentStatus.PENDING 
                    },
                    notes = map["notes"] as? String ?: "",
                    estimatedTime = (map["estimatedTime"] as? Number)?.toLong() ?: 0L,
                    createdAt = (map["createdAt"] as? Number)?.toLong() ?: 0L,
                    updatedAt = (map["updatedAt"] as? Number)?.toLong() ?: 0L
                )
            } catch (e: Exception) {
                null
            }
        }
    }
}

data class OrderItem(
    val menuId: String = "",
    val menuName: String = "",
    val menuPrice: Double = 0.0,
    val quantity: Int = 0,
    val notes: String = "",
    val subtotal: Double = 0.0
) {
    // Empty constructor untuk Firebase
    constructor() : this("", "", 0.0, 0, "", 0.0)

    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,.0f", menuPrice)}"
    }

    fun getFormattedSubtotal(): String {
        return "Rp ${String.format("%,.0f", subtotal)}"
    }

    fun toMap(): Map<String, Any> {
        return mapOf(
            "menuId" to menuId,
            "menuName" to menuName,
            "menuPrice" to menuPrice,
            "quantity" to quantity,
            "notes" to notes,
            "subtotal" to subtotal
        )
    }

    companion object {
        fun fromMap(map: Map<String, Any>): OrderItem? {
            return try {
                OrderItem(
                    menuId = map["menuId"] as? String ?: "",
                    menuName = map["menuName"] as? String ?: "",
                    menuPrice = (map["menuPrice"] as? Number)?.toDouble() ?: 0.0,
                    quantity = (map["quantity"] as? Number)?.toInt() ?: 0,
                    notes = map["notes"] as? String ?: "",
                    subtotal = (map["subtotal"] as? Number)?.toDouble() ?: 0.0
                )
            } catch (e: Exception) {
                null
            }
        }
    }
}

enum class OrderStatus {
    INCOMING,
    CONFIRMED,
    IN_PROGRESS,
    READY,
    COMPLETED,
    CANCELLED
}

enum class PaymentStatus {
    PENDING,
    PAID,
    REFUNDED,
    FAILED
}

// =================================================================================
// OPERATION RESULT MODELS
// =================================================================================

enum class OrderOperationResult {
    SUCCESS,
    ERROR,
    LOADING,
    VALIDATION_ERROR
}

data class OrderOperationResponse(
    val result: OrderOperationResult,
    val message: String = "",
    val data: Order? = null
)

// =================================================================================
// STATISTICS MODEL
// =================================================================================

data class OrderStatistics(
    val totalOrders: Int = 0,
    val pendingOrders: Int = 0,
    val completedOrders: Int = 0,
    val totalRevenue: Double = 0.0,
    val averageOrderValue: Double = 0.0
) {
    fun getFormattedRevenue(): String {
        return "Rp ${String.format("%,.0f", totalRevenue)}"
    }

    fun getFormattedAverageOrder(): String {
        return "Rp ${String.format("%,.0f", averageOrderValue)}"
    }

    fun getCompletionRate(): Float {
        return if (totalOrders > 0) {
            (completedOrders.toFloat() / totalOrders.toFloat()) * 100
        } else {
            0f
        }
    }
}