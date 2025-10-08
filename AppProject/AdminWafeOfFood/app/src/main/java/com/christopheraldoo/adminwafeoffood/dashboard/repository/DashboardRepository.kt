package com.christopheraldoo.adminwafeoffood.dashboard.repository

import android.util.Log
import com.christopheraldoo.adminwafeoffood.dashboard.model.DashboardStatistics
import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderStatus
import com.christopheraldoo.adminwafeoffood.utils.FirebaseUtils
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.*
import kotlinx.coroutines.suspendCancellableCoroutine
import java.text.SimpleDateFormat
import java.util.*
import kotlin.coroutines.resume

class DashboardRepository {
    
    private val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
    private val userOrdersRef = database.getReference("user_orders")
    private val menusRef = database.getReference("menus")
    private val auth = FirebaseAuth.getInstance()
    
    companion object {
        private const val TAG = "DashboardRepository"
    }
    
    /**
     * Get dashboard statistics with proper error handling
     */
    suspend fun getDashboardStatistics(): Result<DashboardStatistics> {
        return try {
            Log.d(TAG, "🔍 Getting dashboard statistics...")
              // Check authentication first
            if (!FirebaseUtils.isUserAuthenticated()) {
                Log.e(TAG, "❌ User not authenticated")
                return Result.failure(Exception("User not authenticated. Please login."))
            }
            
            Log.d(TAG, "✅ Fetching data from Firebase...")
            
            // Get all orders from user_orders structure
            val allOrders = getAllOrdersFromUserOrders()
            Log.d(TAG, "📦 Retrieved ${allOrders.size} total orders")
            
            // Calculate today's revenue - perbaiki perhitungan
            val todayRevenue = calculateTodayRevenue(allOrders)
            
            // Count orders by status
            val totalOrders = allOrders.size
            val activeOrders = allOrders.count { it.status in listOf(OrderStatus.INCOMING, OrderStatus.CONFIRMED, OrderStatus.IN_PROGRESS, OrderStatus.READY) }
            val completedOrders = allOrders.count { it.status == OrderStatus.COMPLETED }
            
            // Get recent orders (last 5)
            val recentOrders = allOrders
                .sortedByDescending { it.createdAt }
                .take(5)
              val statistics = DashboardStatistics(
                totalRevenueToday = todayRevenue,
                totalOrders = totalOrders,
                completedOrders = completedOrders,
                pendingOrders = activeOrders,
                recentOrders = recentOrders
            )
              Log.d(TAG, "✅ Dashboard statistics calculated successfully")
            Log.d(TAG, "📊 Revenue today: Rp ${String.format("%,.0f", statistics.totalRevenueToday)}")
            Log.d(TAG, "📊 Total orders: $totalOrders")
            Log.d(TAG, "📊 Active orders: $activeOrders")
            Log.d(TAG, "📊 Recent orders: ${recentOrders.size}")
            
            Result.success(statistics)
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error getting dashboard statistics", e)
            Result.failure(e)
        }
    }
    
    /**
     * Get all orders from user_orders structure
     */
    private suspend fun getAllOrdersFromUserOrders(): List<Order> {
        return suspendCancellableCoroutine { continuation ->
            userOrdersRef.addListenerForSingleValueEvent(object : ValueEventListener {
                override fun onDataChange(snapshot: DataSnapshot) {
                    try {
                        val orderList = mutableListOf<Order>()
                        
                        Log.d(TAG, "👥 Processing orders from ${snapshot.childrenCount} users")
                        
                        // Loop through all users
                        for (userSnapshot in snapshot.children) {
                            val userId = userSnapshot.key ?: continue
                            Log.d(TAG, "👤 Processing user: $userId with ${userSnapshot.childrenCount} orders")
                            
                            // Loop through orders for each user
                            for (orderSnapshot in userSnapshot.children) {
                                try {
                                    val orderId = orderSnapshot.key ?: continue
                                    val orderData = orderSnapshot.value as? Map<String, Any> ?: continue
                                      val order = mapUserOrderToOrder(orderData, orderId, userId)
                                    order?.let { 
                                        orderList.add(it)
                                        Log.d(TAG, "📦 Added order: $orderId (Rp ${String.format("%,.0f", it.totalAmount)})")
                                    }
                                } catch (e: Exception) {
                                    Log.w(TAG, "⚠️ Skipping invalid order in user $userId", e)
                                }
                            }
                        }
                        
                        Log.d(TAG, "✅ Successfully processed ${orderList.size} total orders")
                        continuation.resume(orderList)
                        
                    } catch (e: Exception) {
                        Log.e(TAG, "💥 Error processing user orders", e)
                        continuation.resume(emptyList())
                    }
                }
                  override fun onCancelled(error: DatabaseError) {
                    Log.e(TAG, "🚫 Database error: ${error.message}")
                    Log.e(TAG, "🔍 Error details: ${error.details}")
                    continuation.resume(emptyList())
                }
            })
        }
    }
    
    /**
     * Map user_orders structure to Order model
     */
    private fun mapUserOrderToOrder(orderData: Map<String, Any>, orderId: String, userId: String): Order? {
        return try {
            // Extract items
            val itemsData = orderData["items"] as? Map<String, Any> ?: emptyMap()
            val orderItems = itemsData.map { (_, itemData) ->
                val item = itemData as? Map<String, Any> ?: return@map null
                com.christopheraldoo.adminwafeoffood.order.model.OrderItem(
                    menuId = item["menuId"] as? String ?: "",
                    menuName = item["itemName"] as? String ?: item["menuName"] as? String ?: "",
                    menuPrice = (item["price"] as? Number)?.toDouble() ?: 0.0,
                    quantity = (item["quantity"] as? Number)?.toInt() ?: 1,
                    notes = item["notes"] as? String ?: "",
                    subtotal = (item["total"] as? Number)?.toDouble() ?: 0.0
                )
            }.filterNotNull()
            
            // Map status
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
                paymentStatus = com.christopheraldoo.adminwafeoffood.order.model.PaymentStatus.PENDING,
                notes = orderData["notes"] as? String ?: "",
                estimatedTime = (orderData["estimatedDeliveryTime"] as? Number)?.toLong() ?: 0L,
                createdAt = (orderData["createdAt"] as? Number)?.toLong() ?: System.currentTimeMillis(),
                updatedAt = (orderData["updatedAt"] as? Number)?.toLong() ?: System.currentTimeMillis()
            )
        } catch (e: Exception) {
            Log.w(TAG, "⚠️ Error mapping order $orderId", e)
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
     * Calculate today's revenue from orders
     */    private fun calculateTodayRevenue(orders: List<Order>): Double {
        return try {
            // Hitung SEMUA revenue dari order yang COMPLETED, bukan cuma hari ini
            val totalRevenue = orders
                .filter { order ->
                    order.status == OrderStatus.COMPLETED
                }
                .sumOf { it.totalAmount }
            
            Log.d(TAG, "💰 Total revenue from all completed orders: Rp ${String.format("%,.0f", totalRevenue)}")
            Log.d(TAG, "📊 Completed orders count: ${orders.count { it.status == OrderStatus.COMPLETED }}")
            
            // Kalau mau hari ini aja, uncomment ini:
            /*
            val calendar = Calendar.getInstance()
            val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(calendar.time)
            
            val todayRevenue = orders
                .filter { order ->
                    try {
                        val orderDate = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
                            .format(Date(order.createdAt))
                        orderDate == today && order.status == OrderStatus.COMPLETED
                    } catch (e: Exception) {
                        Log.w(TAG, "Error parsing order date", e)
                        false
                    }
                }
                .sumOf { it.totalAmount }
            */
            
            totalRevenue
            
        } catch (e: Exception) {
            Log.e(TAG, "Error calculating today revenue", e)
            0.0
        }
    }
    
    /**
     * Get menu count (optional for dashboard)
     */
    suspend fun getMenuCount(): Int {
        return suspendCancellableCoroutine { continuation ->
            menusRef.addListenerForSingleValueEvent(object : ValueEventListener {
                override fun onDataChange(snapshot: DataSnapshot) {
                    val count = snapshot.childrenCount.toInt()
                    Log.d(TAG, "📋 Menu count: $count")
                    continuation.resume(count)
                }
                
                override fun onCancelled(error: DatabaseError) {
                    Log.e(TAG, "Error getting menu count: ${error.message}")
                    continuation.resume(0)
                }
            })
        }
    }
}
