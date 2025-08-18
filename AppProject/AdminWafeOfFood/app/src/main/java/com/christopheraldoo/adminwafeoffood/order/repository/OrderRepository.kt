package com.christopheraldoo.adminwafeoffood.order.repository

import android.util.Log
import com.christopheraldoo.adminwafeoffood.order.model.*
import com.google.firebase.database.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.channels.awaitClose
import kotlin.coroutines.resume

class OrderRepository {
    // Gunakan URL yang sama dengan menu
    private val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app")
    private val orderRef = database.getReference("orders")
    
    companion object {
        private const val TAG = "OrderRepository"
    }

    // Get all orders as Flow - REAL TIME
    fun getAllOrders(): Flow<List<Order>> = callbackFlow {
        Log.d(TAG, "Setting up Firebase listener for orders")
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    Log.d(TAG, "Firebase onDataChange - orders count: ${snapshot.childrenCount}")
                    
                    val orderList = mutableListOf<Order>()
                    for (childSnapshot in snapshot.children) {
                        try {
                            val orderData = childSnapshot.value as? Map<String, Any>
                            orderData?.let { data ->
                                val order = Order.fromMap(data, childSnapshot.key ?: "")
                                order?.let { 
                                    orderList.add(it)
                                    Log.d(TAG, "Parsed order: ${it.customerName} - ${it.status}")
                                }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing individual order item", e)
                        }
                    }
                    
                    // Sort by createdAt (newest first)
                    val sortedList = orderList.sortedByDescending { it.createdAt }
                    Log.d(TAG, "Emitting ${sortedList.size} orders")
                    
                    // Emit data
                    trySend(sortedList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in onDataChange", e)
                    trySend(emptyList<Order>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Firebase listener cancelled: ${error.message}")
                close(error.toException())
            }
        }

        try {
            orderRef.addValueEventListener(listener)
            Log.d(TAG, "Firebase listener added successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error adding Firebase listener", e)
            close(e)
        }
        
        awaitClose { 
            Log.d(TAG, "Removing Firebase listener")
            orderRef.removeEventListener(listener)
        }
    }

    // Update order status
    suspend fun updateOrderStatus(orderId: String, newStatus: OrderStatus): OrderOperationResponse {
        return try {
            Log.d(TAG, "Updating order status: $orderId -> $newStatus")
            
            val updates = mapOf(
                "status" to newStatus.name,
                "updatedAt" to System.currentTimeMillis()
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                orderRef.child(orderId).updateChildren(updates)
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to update order status", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Status pesanan berhasil diupdate"
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "Gagal mengupdate status pesanan"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating order status", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Get order by ID
    suspend fun getOrderById(orderId: String): Order? {
        return try {
            suspendCancellableCoroutine { continuation ->
                orderRef.child(orderId).addListenerForSingleValueEvent(object : ValueEventListener {
                    override fun onDataChange(snapshot: DataSnapshot) {
                        try {
                            val orderData = snapshot.value as? Map<String, Any>
                            if (orderData != null) {
                                val order = Order.fromMap(orderData, snapshot.key ?: "")
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

    // Add new order (untuk testing - biasanya dari user app)
    suspend fun addOrder(order: Order): OrderOperationResponse {
        return try {
            Log.d(TAG, "Adding new order: ${order.customerName}")
            
            val orderId = orderRef.push().key ?: return OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "Gagal generate ID pesanan"
            )
            
            val currentTime = System.currentTimeMillis()
            val orderToSave = order.copy(
                id = orderId,
                createdAt = currentTime,
                updatedAt = currentTime
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                orderRef.child(orderId).setValue(orderToSave.toMap())
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to add order", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                Log.d(TAG, "Order added successfully with ID: $orderId")
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Pesanan berhasil ditambahkan",
                    data = orderToSave
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "Gagal menyimpan pesanan ke database"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error adding order", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Delete order (untuk testing/admin)
    suspend fun deleteOrder(orderId: String): OrderOperationResponse {
        return try {
            Log.d(TAG, "Deleting order: $orderId")
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                orderRef.child(orderId).removeValue()
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to delete order", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                Log.d(TAG, "Order deleted successfully: $orderId")
                OrderOperationResponse(
                    result = OrderOperationResult.SUCCESS,
                    message = "✅ Pesanan berhasil dihapus"
                )
            } else {
                OrderOperationResponse(
                    result = OrderOperationResult.ERROR,
                    message = "Gagal menghapus pesanan"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error deleting order", e)
            OrderOperationResponse(
                result = OrderOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Filter orders by status
    fun getOrdersByStatus(status: OrderStatus): Flow<List<Order>> = callbackFlow {
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val orderList = mutableListOf<Order>()
                    for (childSnapshot in snapshot.children) {
                        try {
                            val orderData = childSnapshot.value as? Map<String, Any>
                            orderData?.let { data ->
                                val order = Order.fromMap(data, childSnapshot.key ?: "")
                                order?.let { 
                                    if (it.status == status) {
                                        orderList.add(it)
                                    }
                                }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing order for status filter", e)
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

        orderRef.addValueEventListener(listener)
        awaitClose { orderRef.removeEventListener(listener) }
    }
}