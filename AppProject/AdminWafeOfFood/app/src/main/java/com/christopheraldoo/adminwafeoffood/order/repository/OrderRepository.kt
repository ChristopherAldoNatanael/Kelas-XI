package com.christopheraldoo.adminwafeoffood.order.repository

import android.util.Log
import com.christopheraldoo.adminwafeoffood.order.model.*
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.channels.awaitClose
import kotlin.coroutines.resume

class OrderRepository {
    
    private val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
    private val userOrdersRef = database.getReference("user_orders")
    private val auth = FirebaseAuth.getInstance()
    
    companion object {
        private const val TAG = "OrderRepository"
    }
    
    /**
     * Get all orders from all users (for admin view)
     * Struktur: user_orders/{userId}/{orderId}
     */
    fun getAllOrders(): Flow<List<Order>> = callbackFlow {
        Log.d(TAG, "🔍 Starting to get all orders from user_orders")
        
        // Check authentication first
        if (auth.currentUser == null) {
            Log.e(TAG, "❌ User not authenticated")
            trySend(emptyList<Order>()).isSuccess
            close(Exception("User not authenticated"))
            return@callbackFlow
        }
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    Log.d(TAG, "📊 Processing user_orders data - users count: ${snapshot.childrenCount}")
                    val orderList = mutableListOf<Order>()
                    
                    // Loop through all users
                    for (userSnapshot in snapshot.children) {                        val userId = userSnapshot.key ?: continue
                        Log.d(TAG, "👤 Processing orders for user: $userId")
                        
                        // Loop through orders for each user
                        for (orderSnapshot in userSnapshot.children) {
                            try {
                                val orderId = orderSnapshot.key ?: continue
                                val orderData = orderSnapshot.value as? Map<String, Any> ?: continue
                                
                                Log.d(TAG, "🍽️ Processing order: $orderId")
                                
                                // Convert user_orders structure to Order object
                                val order = mapUserOrderToOrder(orderData, orderId, userId)
                                order?.let { 
                                    orderList.add(it)
                                    Log.d(TAG, "✅ Added order: $orderId from user: ${it.customerName}")
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "❌ Error parsing order from user $userId", e)
                            }
                        }
                    }
                    
                    // Sort by creation date (newest first)
                    val sortedList = orderList.sortedByDescending { it.createdAt }
                    Log.d(TAG, "🎉 Successfully loaded ${sortedList.size} total orders from all users")
                    trySend(sortedList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "💥 Error in onDataChange", e)
                    trySend(emptyList<Order>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "🚫 Firebase listener cancelled: ${error.message}")
                when (error.code) {
                    DatabaseError.PERMISSION_DENIED -> {
                        Log.e(TAG, "🔒 Permission denied - check Firebase rules and authentication")
                    }
                    DatabaseError.NETWORK_ERROR -> {
                        Log.e(TAG, "🌐 Network error - check internet connection")
                    }
                    else -> {
                        Log.e(TAG, "❌ Database error: ${error.details}")
                    }
                }
                close(error.toException())
            }
        }

        try {
            userOrdersRef.addValueEventListener(listener)
            Log.d(TAG, "🎯 Firebase listener added successfully to user_orders")
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error adding Firebase listener", e)
            close(e)
        }
        
        awaitClose { 
            Log.d(TAG, "🔄 Removing Firebase listener from user_orders")
            userOrdersRef.removeEventListener(listener)
        }
    }
    
    /**
     * Convert user_orders structure to Order model
     */
    private fun mapUserOrderToOrder(orderData: Map<String, Any>, orderId: String, userId: String): Order? {
        return try {
            // Extract items
            val itemsData = orderData["items"] as? Map<String, Any> ?: emptyMap()
            val orderItems = itemsData.map { (_, itemData) ->
                val item = itemData as? Map<String, Any> ?: return@map null
                OrderItem(
                    menuId = item["menuId"] as? String ?: "",
                    menuName = item["itemName"] as? String ?: item["menuName"] as? String ?: "",
                    menuPrice = (item["price"] as? Number)?.toDouble() ?: 0.0,
                    quantity = (item["quantity"] as? Number)?.toInt() ?: 1,
                    notes = item["notes"] as? String ?: "",
                    subtotal = (item["total"] as? Number)?.toDouble() ?: 0.0
                )
            }.filterNotNull()
            
            // Map status dari user app ke admin app
            val userStatus = orderData["status"] as? String ?: "PENDING"
            val adminStatus = mapUserStatusToAdminStatus(userStatus)
            
            Order(
                id = orderId,
                customerId = userId,
                customerName = orderData["userName"] as? String ?: orderData["recipientName"] as? String ?: "Unknown",
                customerEmail = orderData["userEmail"] as? String ?: "",
                customerPhone = orderData["userPhone"] as? String ?: "",
                customerAddress = orderData["deliveryAddress"] as? String ?: "",
                items = orderItems,
                totalAmount = (orderData["total"] as? Number)?.toDouble() ?: 0.0,
                status = adminStatus,
                paymentMethod = orderData["paymentMethod"] as? String ?: "Unknown",
                paymentStatus = PaymentStatus.PENDING, // Default
                notes = orderData["notes"] as? String ?: "",
                estimatedTime = (orderData["estimatedDeliveryTime"] as? Number)?.toLong() ?: 0L,
                createdAt = (orderData["createdAt"] as? Number)?.toLong() ?: System.currentTimeMillis(),
                updatedAt = (orderData["updatedAt"] as? Number)?.toLong() ?: System.currentTimeMillis()
            )
        } catch (e: Exception) {
            Log.e(TAG, "Error mapping user order to Order object", e)
            null
        }
    }
    
    /**
     * Map status dari user app ke admin app
     */
    private fun mapUserStatusToAdminStatus(userStatus: String): OrderStatus {
        return when (userStatus.uppercase()) {
            "PENDING" -> OrderStatus.INCOMING
            "CONFIRMED" -> OrderStatus.CONFIRMED
            "PREPARING" -> OrderStatus.IN_PROGRESS
            "READY" -> OrderStatus.READY
            "COMPLETED" -> OrderStatus.COMPLETED
            "CANCELLED" -> OrderStatus.CANCELLED
            else -> OrderStatus.INCOMING
        }
    }
    
    /**
     * Update order status - harus update ke struktur user_orders
     */
    suspend fun updateOrderStatus(orderId: String, newStatus: OrderStatus): OrderOperationResponse {
        return try {
            Log.d(TAG, "🔄 Updating order status: $orderId -> $newStatus")
            
            // Check authentication
            if (auth.currentUser == null) {
                return OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ User not authenticated"
                )
            }
            
            // Find the order in user_orders structure
            val orderPath = findOrderPath(orderId) ?: return OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "❌ Order not found"
            )
            
            val updates = mapOf(
                "status" to mapAdminStatusToUserStatus(newStatus),
                "updatedAt" to System.currentTimeMillis()
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                userOrdersRef.child("${orderPath.userId}/${orderPath.orderId}").updateChildren(updates)
                    .addOnSuccessListener { 
                        Log.d(TAG, "✅ Order status updated successfully")
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "❌ Failed to update order status", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Status pesanan berhasil diupdate ke ${newStatus.name}"
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ Gagal mengupdate status pesanan"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error updating order status", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "❌ Error: ${e.message}"
            )
        }
    }
    
    /**
     * Map status dari admin app ke user app
     */
    private fun mapAdminStatusToUserStatus(adminStatus: OrderStatus): String {
        return when (adminStatus) {
            OrderStatus.INCOMING -> "PENDING"
            OrderStatus.CONFIRMED -> "CONFIRMED"
            OrderStatus.IN_PROGRESS -> "PREPARING"
            OrderStatus.READY -> "READY"
            OrderStatus.COMPLETED -> "COMPLETED"
            OrderStatus.CANCELLED -> "CANCELLED"
        }
    }
    
    /**
     * Find order path in user_orders structure
     */
    private suspend fun findOrderPath(orderId: String): OrderPath? {
        return suspendCancellableCoroutine { continuation ->
            userOrdersRef.addListenerForSingleValueEvent(object : ValueEventListener {
                override fun onDataChange(snapshot: DataSnapshot) {
                    for (userSnapshot in snapshot.children) {
                        val userId = userSnapshot.key ?: continue
                        if (userSnapshot.hasChild(orderId)) {
                            continuation.resume(OrderPath(userId, orderId))
                            return
                        }
                    }
                    continuation.resume(null)
                }
                
                override fun onCancelled(error: DatabaseError) {
                    Log.e(TAG, "Error finding order path", error.toException())
                    continuation.resume(null)
                }
            })
        }
    }
    
    /**
     * Delete order - harus dari struktur user_orders
     */
    suspend fun deleteOrder(orderId: String): OrderOperationResponse {
        return try {
            Log.d(TAG, "🗑️ Deleting order: $orderId")
            
            // Check authentication
            if (auth.currentUser == null) {
                return OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ User not authenticated"
                )
            }
            
            // Find the order path
            val orderPath = findOrderPath(orderId) ?: return OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "❌ Order not found"
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                userOrdersRef.child("${orderPath.userId}/${orderPath.orderId}").removeValue()
                    .addOnSuccessListener { 
                        Log.d(TAG, "✅ Order deleted successfully")
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "❌ Failed to delete order", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Pesanan berhasil dihapus"
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ Gagal menghapus pesanan"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error deleting order", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "❌ Error: ${e.message}"
            )
        }
    }
    
    /**
     * Get order by ID
     */
    suspend fun getOrderById(orderId: String): Order? {
        return try {
            val orderPath = findOrderPath(orderId) ?: return null
            
            suspendCancellableCoroutine { continuation ->
                userOrdersRef.child("${orderPath.userId}/${orderPath.orderId}")
                    .addListenerForSingleValueEvent(object : ValueEventListener {
                        override fun onDataChange(snapshot: DataSnapshot) {
                            try {
                                val orderData = snapshot.value as? Map<String, Any>
                                if (orderData != null) {
                                    val order = mapUserOrderToOrder(orderData, orderId, orderPath.userId)
                                    continuation.resume(order)
                                } else {
                                    continuation.resume(null)
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Error parsing order by ID", e)
                                continuation.resume(null)
                            }
                        }
                        
                        override fun onCancelled(error: DatabaseError) {
                            Log.e(TAG, "Error getting order by ID", error.toException())
                            continuation.resume(null)
                        }
                    })
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error in getOrderById", e)
            null
        }
    }
    
    /**
     * Filter orders by status
     */
    fun getOrdersByStatus(status: OrderStatus): Flow<List<Order>> = callbackFlow {
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val orderList = mutableListOf<Order>()
                    
                    // Loop through all users
                    for (userSnapshot in snapshot.children) {
                        val userId = userSnapshot.key ?: continue
                        
                        // Loop through orders for each user
                        for (orderSnapshot in userSnapshot.children) {
                            try {
                                val orderId = orderSnapshot.key ?: continue
                                val orderData = orderSnapshot.value as? Map<String, Any> ?: continue
                                
                                val order = mapUserOrderToOrder(orderData, orderId, userId)
                                order?.let { 
                                    if (it.status == status) {
                                        orderList.add(it)
                                    }
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Error parsing order for status filter", e)
                            }
                        }
                    }
                    
                    val sortedList = orderList.sortedByDescending { it.createdAt }
                    trySend(sortedList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in status filter", e)
                    trySend(emptyList<Order>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Firebase listener cancelled for status filter", error.toException())
                close(error.toException())
            }
        }

        userOrdersRef.addValueEventListener(listener)
        awaitClose { userOrdersRef.removeEventListener(listener) }
    }
    
    /**
     * Add new order (for testing purposes)
     */
    suspend fun addOrder(order: Order): OrderOperationResponse {
        return try {
            Log.d(TAG, "📦 Adding test order: ${order.id}")
            
            // Check authentication
            if (auth.currentUser == null) {
                return OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ User not authenticated"
                )
            }
            
            // For admin app, we'll add to a test user or current admin user
            val testUserId = "admin_test_user"
            val orderData = mapOf(
                "id" to order.id,
                "userId" to testUserId,
                "userEmail" to order.customerEmail,
                "userName" to order.customerName,
                "items" to order.items.mapIndexed { index, item ->
                    index.toString() to mapOf(
                        "menuId" to item.menuId,
                        "itemName" to item.menuName,
                        "price" to item.menuPrice,
                        "quantity" to item.quantity,
                        "total" to item.subtotal
                    )
                }.toMap(),
                "total" to order.totalAmount,
                "status" to mapAdminStatusToUserStatus(order.status),
                "paymentMethod" to order.paymentMethod,
                "recipientName" to order.customerName,
                "deliveryAddress" to order.customerAddress,
                "notes" to order.notes,
                "createdAt" to order.createdAt,
                "updatedAt" to order.updatedAt
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                userOrdersRef.child("$testUserId/${order.id}").setValue(orderData)
                    .addOnSuccessListener { 
                        Log.d(TAG, "✅ Test order added successfully")
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "❌ Failed to add test order", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Test order berhasil ditambahkan"
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "❌ Gagal menambahkan test order"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error adding test order", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "❌ Error: ${e.message}"
            )
        }
    }
    
    /**
     * Data class untuk menyimpan path order
     */
    data class OrderPath(val userId: String, val orderId: String)
}
