package com.christopheraldoo.adminwafeoffood.dashboard.repository

import android.util.Log
import com.christopheraldoo.adminwafeoffood.dashboard.model.*
import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderStatus
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.google.firebase.database.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.channels.awaitClose
import java.text.SimpleDateFormat
import java.util.*

class DashboardRepository {
    
    // Gunakan URL yang sama dengan order dan menu
    private val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app")
    private val orderRef = database.getReference("orders")
    private val menuRef = database.getReference("menu") // PERBAIKI: Ganti ke "menu" bukan "menus"
    
    companion object {
        private const val TAG = "DashboardRepository"
    }

    // Get dashboard statistics - REAL TIME
    fun getDashboardStatistics(): Flow<DashboardStatistics> = combine(
        getAllOrders(),
        getAllMenus()
    ) { orders, menus ->        try {
            Log.d(TAG, "Processing dashboard statistics - Orders: ${orders.size}, Menus: ${menus.size}")
            
            // Calculate today's data
            val today = getTodayStartTimestamp()
            val todayOrders = orders.filter { it.createdAt >= today }
            Log.d(TAG, "Today orders: ${todayOrders.size}, from total: ${orders.size}")
            
            // Total revenue today (only completed orders)
            val todayCompletedOrders = todayOrders.filter { it.status == OrderStatus.COMPLETED }
            val totalRevenueToday = todayCompletedOrders.sumOf { it.totalAmount }
            Log.d(TAG, "Today completed orders: ${todayCompletedOrders.size}, Revenue: $totalRevenueToday")
            
            // FALLBACK: If no today orders, calculate from all completed orders for demo
            val fallbackRevenue = if (totalRevenueToday == 0.0) {
                val allCompletedOrders = orders.filter { it.status == OrderStatus.COMPLETED }
                val revenue = allCompletedOrders.sumOf { it.totalAmount }
                Log.d(TAG, "Using fallback revenue from all completed orders: $revenue (${allCompletedOrders.size} orders)")
                revenue
            } else {
                totalRevenueToday
            }
            
            // Total orders
            val totalOrders = orders.size
            
            // Pending orders
            val pendingOrders = orders.count { order ->
                order.status in listOf(
                    OrderStatus.INCOMING,
                    OrderStatus.CONFIRMED,
                    OrderStatus.IN_PROGRESS,
                    OrderStatus.READY
                )
            }
              // Completed orders
            val completedOrders = orders.count { it.status == OrderStatus.COMPLETED }
            
            // Recent orders (last 5)
            val recentOrders = orders                .sortedByDescending { it.createdAt }
                .take(5)
              
            DashboardStatistics(
                totalRevenueToday = fallbackRevenue, // Use fallback revenue
                totalOrders = totalOrders,
                pendingOrders = pendingOrders,
                completedOrders = completedOrders,
                recentOrders = recentOrders
            )
            
        } catch (e: Exception) {
            Log.e(TAG, "Error processing dashboard statistics", e)
            DashboardStatistics()
        }
    }

    // Get all orders
    private fun getAllOrders(): Flow<List<Order>> = callbackFlow {
        Log.d(TAG, "Setting up Firebase listener for orders (dashboard)")
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val orderList = mutableListOf<Order>()
                    for (childSnapshot in snapshot.children) {
                        try {
                            val orderData = childSnapshot.value as? Map<String, Any>
                            orderData?.let { data ->
                                val order = Order.fromMap(data, childSnapshot.key ?: "")
                                order?.let { orderList.add(it) }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing order item for dashboard", e)
                        }
                    }
                    
                    Log.d(TAG, "Dashboard - Emitting ${orderList.size} orders")
                    trySend(orderList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in orders onDataChange (dashboard)", e)
                    trySend(emptyList<Order>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Orders listener cancelled (dashboard): ${error.message}")
                close(error.toException())
            }
        }

        orderRef.addValueEventListener(listener)
        awaitClose { 
            Log.d(TAG, "Removing orders listener (dashboard)")
            orderRef.removeEventListener(listener) 
        }
    }

    // Get all menus
    private fun getAllMenus(): Flow<List<MenuItem>> = callbackFlow {
        Log.d(TAG, "Setting up Firebase listener for menus (dashboard)")
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val menuList = mutableListOf<MenuItem>()
                    for (childSnapshot in snapshot.children) {
                        try {
                            val menuData = childSnapshot.value as? Map<String, Any>
                            menuData?.let { data ->
                                val menuItem = MenuItem.fromMap(data, childSnapshot.key ?: "")
                                menuItem?.let { menuList.add(it) }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu item for dashboard", e)
                        }
                    }
                    
                    Log.d(TAG, "Dashboard - Emitting ${menuList.size} menus")
                    trySend(menuList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in menus onDataChange (dashboard)", e)
                    trySend(emptyList<MenuItem>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Menus listener cancelled (dashboard): ${error.message}")
                close(error.toException())
            }
        }

        menuRef.addValueEventListener(listener)
        awaitClose { 
            Log.d(TAG, "Removing menus listener (dashboard)")
            menuRef.removeEventListener(listener) 
        }
    }    // Calculate popular menu
    private fun calculatePopularMenu(orders: List<Order>, menus: List<MenuItem>): PopularMenuItem {
        return try {
            Log.d(TAG, "Calculating popular menu from ${orders.size} orders and ${menus.size} menus")
            
            val menuItemCount = mutableMapOf<String, Int>()
            val menuRevenue = mutableMapOf<String, Double>()
            
            // Count orders for each menu item
            orders.forEach { order ->
                Log.d(TAG, "Processing order ${order.id} with ${order.items.size} items")
                order.items.forEach { orderItem ->
                    val menuId = orderItem.menuId
                    val currentCount = menuItemCount[menuId] ?: 0
                    val currentRevenue = menuRevenue[menuId] ?: 0.0
                    
                    menuItemCount[menuId] = currentCount + orderItem.quantity
                    menuRevenue[menuId] = currentRevenue + orderItem.subtotal
                    
                    Log.d(TAG, "Menu ID: $menuId, New Count: ${currentCount + orderItem.quantity}")
                }
            }
            
            Log.d(TAG, "Menu counts: $menuItemCount")
            Log.d(TAG, "Available menus: ${menus.map { "${it.id}:${it.name}" }}")
            
            // Find most popular menu
            val mostPopularMenuId = menuItemCount.maxByOrNull { it.value }?.key
            
            if (mostPopularMenuId != null) {
                val menuName = menus.find { it.id == mostPopularMenuId }?.name ?: "Unknown Menu"
                val orderCount = menuItemCount[mostPopularMenuId] ?: 0
                val totalRevenue = menuRevenue[mostPopularMenuId] ?: 0.0
                
                Log.d(TAG, "Most popular menu: ID=$mostPopularMenuId, Name=$menuName, Count=$orderCount")
                
                PopularMenuItem(
                    menuId = mostPopularMenuId,
                    name = menuName,
                    orderCount = orderCount,
                    totalRevenue = totalRevenue
                )
            } else {
                Log.d(TAG, "No popular menu found")
                PopularMenuItem(name = "No Data")
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error calculating popular menu", e)
            PopularMenuItem(name = "No Data")
        }
    }// Calculate today's chart data (hourly)
    private fun calculateTodayChartData(todayOrders: List<Order>): List<ChartData> {
        return try {
            val hourlyData = mutableMapOf<Int, Double>()
            
            // Initialize hours with some demo data if no orders
            for (hour in 6..23) { // Restaurant hours 6 AM to 11 PM
                hourlyData[hour] = 0.0
            }
            
            // Calculate revenue by hour
            val completedTodayOrders = todayOrders.filter { it.status == OrderStatus.COMPLETED }
            Log.d(TAG, "Processing ${completedTodayOrders.size} completed orders for hourly chart")
            
            if (completedTodayOrders.isNotEmpty()) {
                completedTodayOrders.forEach { order ->
                    val calendar = Calendar.getInstance()
                    calendar.timeInMillis = order.createdAt
                    val hour = calendar.get(Calendar.HOUR_OF_DAY)
                    
                    if (hour in 6..23) {
                        hourlyData[hour] = (hourlyData[hour] ?: 0.0) + order.totalAmount
                        Log.d(TAG, "Hour $hour: +${order.totalAmount} = ${hourlyData[hour]}")
                    }
                }
            } else {
                // Add some sample data points for demo
                hourlyData[8] = 150000.0  // 8 AM
                hourlyData[12] = 250000.0 // 12 PM
                hourlyData[19] = 180000.0 // 7 PM
                Log.d(TAG, "Using demo hourly data since no orders found")
            }
            
            // Convert to ChartData (only non-zero or significant hours)
            val result = hourlyData.filter { it.value > 0 || it.key in listOf(8, 12, 18, 20) }
                .map { (hour, revenue) ->
                    val timeLabel = String.format("%02d:00", hour)
                    ChartData(timeLabel, revenue)
                }.sortedBy { it.label }
            
            Log.d(TAG, "Generated ${result.size} hourly chart points")
            result
            
        } catch (e: Exception) {
            Log.e(TAG, "Error calculating today's chart data", e)
            // Return demo data as fallback
            listOf(
                ChartData("08:00", 150000.0),
                ChartData("12:00", 250000.0),
                ChartData("19:00", 180000.0)
            )
        }
    }    // Calculate weekly revenue (last 7 days)
    private fun calculateWeeklyRevenue(orders: List<Order>): List<ChartData> {
        return try {
            val calendar = Calendar.getInstance()
            val weeklyRevenue = mutableListOf<ChartData>()
            val completedOrders = orders.filter { it.status == OrderStatus.COMPLETED }
            
            Log.d(TAG, "Calculating weekly revenue from ${completedOrders.size} completed orders")
            
            // Get revenue for each of the last 7 days
            for (i in 6 downTo 0) {
                calendar.time = Date()
                calendar.add(Calendar.DAY_OF_YEAR, -i)
                calendar.set(Calendar.HOUR_OF_DAY, 0)
                calendar.set(Calendar.MINUTE, 0)
                calendar.set(Calendar.SECOND, 0)
                calendar.set(Calendar.MILLISECOND, 0)
                val dayStart = calendar.timeInMillis
                
                calendar.set(Calendar.HOUR_OF_DAY, 23)
                calendar.set(Calendar.MINUTE, 59)
                calendar.set(Calendar.SECOND, 59)
                calendar.set(Calendar.MILLISECOND, 999)
                val dayEnd = calendar.timeInMillis
                
                val dayRevenue = completedOrders
                    .filter { it.createdAt >= dayStart && it.createdAt <= dayEnd }
                    .sumOf { it.totalAmount }
                
                // Format day label
                calendar.time = Date()
                calendar.add(Calendar.DAY_OF_YEAR, -i)
                val dayFormatter = SimpleDateFormat("EEE", Locale.getDefault())
                val dayLabel = dayFormatter.format(calendar.time)
                
                // Add some demo data if no real data exists and we're in demo mode
                val finalRevenue = if (dayRevenue == 0.0 && completedOrders.isEmpty()) {
                    when (i) {
                        0 -> 200000.0  // Today
                        1 -> 180000.0  // Yesterday  
                        2 -> 220000.0  // 2 days ago
                        3 -> 160000.0  // 3 days ago
                        4 -> 190000.0  // 4 days ago
                        5 -> 210000.0  // 5 days ago
                        6 -> 170000.0  // 6 days ago
                        else -> 150000.0
                    }
                } else {
                    dayRevenue
                }
                
                weeklyRevenue.add(ChartData(dayLabel, finalRevenue))
                Log.d(TAG, "Day $dayLabel: Revenue = $finalRevenue")
            }
            
            Log.d(TAG, "Generated ${weeklyRevenue.size} weekly chart points")
            weeklyRevenue
              } catch (e: Exception) {
            Log.e(TAG, "Error calculating weekly revenue", e)
            // Return demo data as fallback
            listOf(
                ChartData("Mon", 150000.0),
                ChartData("Tue", 180000.0),
                ChartData("Wed", 200000.0),
                ChartData("Thu", 170000.0),
                ChartData("Fri", 220000.0),
                ChartData("Sat", 190000.0),
                ChartData("Sun", 210000.0)
            )
        }
    }

    // Get today's start timestamp
    private fun getTodayStartTimestamp(): Long {
        val calendar = Calendar.getInstance()
        calendar.set(Calendar.HOUR_OF_DAY, 0)
        calendar.set(Calendar.MINUTE, 0)
        calendar.set(Calendar.SECOND, 0)
        calendar.set(Calendar.MILLISECOND, 0)
        return calendar.timeInMillis
    }
}