package com.christopheraldoo.adminwafeoffood.dashboard.model

import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderStatus
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import java.text.NumberFormat
import java.util.*

// =================================================================================
// DASHBOARD STATISTICS MODELS
// =================================================================================

data class DashboardStatistics(
    val totalRevenueToday: Double = 0.0,
    val totalOrders: Int = 0,
    val pendingOrders: Int = 0,
    val completedOrders: Int = 0,
    val popularMenu: String = "No Data",
    val popularMenuCount: Int = 0,
    val recentOrders: List<Order> = emptyList(),
    val chartData: List<ChartData> = emptyList(),
    val weeklyRevenue: List<ChartData> = emptyList()
) {
    
    fun getFormattedRevenueToday(): String {
        return if (totalRevenueToday > 0) {
            val formatter = NumberFormat.getCurrencyInstance(Locale("id", "ID"))
            formatter.format(totalRevenueToday).replace("IDR", "Rp")
        } else {
            "Rp 0"
        }
    }
    
    fun getTotalOrdersText(): String = totalOrders.toString()
    
    fun getPopularMenuText(): String = popularMenu
    
    fun getPopularMenuOrderCount(): String = "$popularMenuCount orders"
    
    fun getPendingOrdersText(): String = "$pendingOrders pending"
    
    fun getCompletedOrdersText(): String = "$completedOrders completed"
    
    // Chart methods
    fun getTodaysSalesData(): List<ChartData> = chartData
    
    fun getWeeklySalesData(): List<ChartData> = weeklyRevenue
    
    fun getTotalTodayItems(): Int {
        return chartData.sumOf { it.value.toInt() }
    }
    
    fun getPeakHour(): String {
        val peak = chartData.maxByOrNull { it.value }
        return peak?.label ?: "No Data"
    }
}

data class ChartData(
    val label: String,
    val value: Double
)

// =================================================================================
// POPULAR MENU MODEL
// =================================================================================

data class PopularMenuItem(
    val menuId: String = "",
    val name: String = "",
    val orderCount: Int = 0,
    val totalRevenue: Double = 0.0
) {
    fun getFormattedRevenue(): String {
        return "Rp ${String.format("%,d", totalRevenue.toLong())}"
    }
}

// =================================================================================
// DASHBOARD RESPONSE MODELS
// =================================================================================

data class DashboardData(
    val statistics: DashboardStatistics = DashboardStatistics(),
    val isLoading: Boolean = false,
    val error: String? = null
)