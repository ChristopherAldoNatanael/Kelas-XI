package com.christopheraldoo.wavesoffood.data.repository

import android.util.Log
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderItem
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.ui.cart.CartItem
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.*
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.tasks.await
import java.util.*

/**
 * Repository untuk mengelola data order/pesanan di Firebase
 */
class OrderRepository {
    
    private val database = FirebaseDatabase.getInstance()
    private val ordersRef = database.getReference("orders")
    private val userOrdersRef = database.getReference("user_orders")
    private val auth = FirebaseAuth.getInstance()
    
    /**
     * Membuat order baru dari cart items
     */
    suspend fun createOrder(
        cartItems: List<CartItem>,
        subtotal: Int,
        discount: Int,
        deliveryFee: Int,
        total: Int,
        orderType: String,
        paymentMethod: String,
        recipientName: String,
        deliveryAddress: String,
        notes: String = ""
    ): Result<String> {
        return try {
            val currentUser = auth.currentUser 
                ?: return Result.failure(Exception("User not authenticated"))
            
            val orderId = ordersRef.push().key 
                ?: return Result.failure(Exception("Failed to generate order ID"))
            
            // Convert CartItem to OrderItem
            val orderItems = cartItems.map { cartItem ->
                OrderItem(
                    menuId = cartItem.menuId,
                    menuName = cartItem.menuName,
                    menuImageUrl = cartItem.menuImageUrl,
                    price = cartItem.price,
                    quantity = cartItem.quantity,
                    totalPrice = cartItem.price * cartItem.quantity,
                    category = cartItem.category
                )
            }
            
            val order = Order(
                id = orderId,
                userId = currentUser.uid,
                userEmail = currentUser.email ?: "",
                userName = currentUser.displayName ?: recipientName,
                userPhone = currentUser.phoneNumber ?: "",
                items = orderItems,
                subtotal = subtotal,
                discount = discount,
                deliveryFee = deliveryFee,
                total = total,
                orderType = when(orderType.uppercase()) {
                    "DINE IN" -> com.christopheraldoo.wavesoffood.data.model.OrderType.DINE_IN
                    else -> com.christopheraldoo.wavesoffood.data.model.OrderType.TAKE_AWAY
                },
                paymentMethod = when(paymentMethod.uppercase()) {
                    "QRIS" -> com.christopheraldoo.wavesoffood.data.model.PaymentMethod.QRIS
                    "BANK TRANSFER" -> com.christopheraldoo.wavesoffood.data.model.PaymentMethod.BANK_TRANSFER
                    "CREDIT CARD" -> com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CREDIT_CARD
                    else -> com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CASH
                },
                status = OrderStatus.PENDING,
                recipientName = recipientName,
                deliveryAddress = deliveryAddress,
                notes = notes,
                estimatedDeliveryTime = System.currentTimeMillis() + (30 * 60 * 1000) // 30 minutes
            )
            
            // Save to orders
            ordersRef.child(orderId).setValue(order).await()
            
            // Save to user_orders for easy retrieval
            userOrdersRef.child(currentUser.uid).child(orderId).setValue(order).await()
            
            Result.success(orderId)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Mendapatkan semua order untuk user yang sedang login
     */
    fun getUserOrders(): Flow<List<Order>> = callbackFlow {
        val currentUser = auth.currentUser
        Log.d("OrderRepository", "getUserOrders called, currentUser: ${currentUser?.uid}")
        if (currentUser == null) {
            Log.w("OrderRepository", "No authenticated user")
            trySend(emptyList())
            close()
            return@callbackFlow
        }
        
        val listener = userOrdersRef.child(currentUser.uid)
            .orderByChild("createdAt")
            .addValueEventListener(object : ValueEventListener {
                override fun onDataChange(snapshot: DataSnapshot) {
                    Log.d("OrderRepository", "Firebase onDataChange, children count: ${snapshot.childrenCount}")
                    val orders = mutableListOf<Order>()
                    
                    for (orderSnapshot in snapshot.children.reversed()) {
                        try {
                            val order = orderSnapshot.getValue<Order>()
                            order?.let { 
                                Log.d("OrderRepository", "Parsed order: ${it.id}")
                                orders.add(it) 
                            }
                        } catch (e: Exception) {
                            Log.e("OrderRepository", "Error parsing order: ${e.message}")
                            // Skip invalid orders
                            continue
                        }
                    }
                    
                    Log.d("OrderRepository", "Sending ${orders.size} orders to flow")
                    trySend(orders)
                }
                
                override fun onCancelled(error: DatabaseError) {
                    Log.e("OrderRepository", "Firebase query cancelled: ${error.message}")
                    close(error.toException())
                }
            })
        
        awaitClose {
            Log.d("OrderRepository", "Removing Firebase listener")
            userOrdersRef.child(currentUser.uid).removeEventListener(listener)
        }
    }
    
    /**
     * Mendapatkan order berdasarkan ID
     */
    suspend fun getOrderById(orderId: String): Result<Order> {
        return try {
            val snapshot = ordersRef.child(orderId).get().await()
            val order = snapshot.getValue<Order>()
            if (order != null) {
                Result.success(order)
            } else {
                Result.failure(Exception("Order not found"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Update status order
     */
    suspend fun updateOrderStatus(orderId: String, newStatus: OrderStatus): Result<Unit> {
        return try {
            val updates = mapOf<String, Any>(
                "status" to newStatus,
                "updatedAt" to System.currentTimeMillis(),
                "completedAt" to if (newStatus == OrderStatus.COMPLETED) System.currentTimeMillis() else 0L
            )
            
            ordersRef.child(orderId).updateChildren(updates).await()
            
            // Also update in user_orders
            val currentUser = auth.currentUser
            if (currentUser != null) {
                userOrdersRef.child(currentUser.uid).child(orderId).updateChildren(updates).await()
            }
            
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Cancel order
     */
    suspend fun cancelOrder(orderId: String, reason: String = ""): Result<Unit> {
        return try {
            val updates = mapOf<String, Any>(
                "status" to OrderStatus.CANCELLED,
                "updatedAt" to System.currentTimeMillis(),
                "notes" to if (reason.isNotEmpty()) "Cancelled: $reason" else "Cancelled by user"
            )
            
            ordersRef.child(orderId).updateChildren(updates).await()
            
            // Also update in user_orders
            val currentUser = auth.currentUser
            if (currentUser != null) {
                userOrdersRef.child(currentUser.uid).child(orderId).updateChildren(updates).await()
            }
              Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Update delivery address for an order
     */
    suspend fun updateDeliveryAddress(orderId: String, newAddress: String): Result<Boolean> {
        return try {
            val currentUser = auth.currentUser 
                ?: return Result.failure(Exception("User not authenticated"))
            
            val updates = mapOf<String, Any>(
                "deliveryAddress" to newAddress,
                "updatedAt" to System.currentTimeMillis()
            )
            
            // Update in main orders collection
            ordersRef.child(orderId).updateChildren(updates).await()
            
            // Update in user_orders collection
            userOrdersRef.child(currentUser.uid).child(orderId).updateChildren(updates).await()
            
            Result.success(true)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Get order statistics for user
     */
    suspend fun getUserOrderStats(): Result<Map<String, Int>> {
        return try {
            val currentUser = auth.currentUser 
                ?: return Result.failure(Exception("User not authenticated"))
            
            val snapshot = userOrdersRef.child(currentUser.uid).get().await()
            val stats = mutableMapOf<String, Int>()
            
            var totalOrders = 0
            var completedOrders = 0
            var totalSpent = 0
            var pendingOrders = 0
            
            for (orderSnapshot in snapshot.children) {
                val order = orderSnapshot.getValue<Order>()
                order?.let {
                    totalOrders++
                    totalSpent += it.total
                    
                    when (it.status) {
                        OrderStatus.COMPLETED -> completedOrders++
                        OrderStatus.PENDING, OrderStatus.CONFIRMED, OrderStatus.PREPARING, 
                        OrderStatus.READY, OrderStatus.DELIVERING -> pendingOrders++
                        else -> {}
                    }
                }
            }
              stats["totalOrders"] = totalOrders
            stats["completedOrders"] = completedOrders
            stats["totalSpent"] = totalSpent
            stats["pendingOrders"] = pendingOrders
            
            Result.success(stats.toMap())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    /**
     * Test untuk memeriksa apakah ada data order di Firebase
     */
    fun testOrderData() {
        val currentUser = auth.currentUser
        Log.d("OrderRepository", "Testing order data for user: ${currentUser?.uid}")
        
        if (currentUser != null) {
            userOrdersRef.child(currentUser.uid).get().addOnSuccessListener { snapshot ->
                Log.d("OrderRepository", "Test result - exists: ${snapshot.exists()}, children: ${snapshot.childrenCount}")
                if (snapshot.exists()) {
                    for (child in snapshot.children) {
                        Log.d("OrderRepository", "Order key: ${child.key}")
                    }
                }
            }.addOnFailureListener { error ->
                Log.e("OrderRepository", "Test failed: ${error.message}")
            }
        } else {
            Log.w("OrderRepository", "No authenticated user for testing")
        }
    }
    
    companion object {
        @Volatile
        private var INSTANCE: OrderRepository? = null
        
        fun getInstance(): OrderRepository {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: OrderRepository().also { INSTANCE = it }
            }
        }
    }
}
