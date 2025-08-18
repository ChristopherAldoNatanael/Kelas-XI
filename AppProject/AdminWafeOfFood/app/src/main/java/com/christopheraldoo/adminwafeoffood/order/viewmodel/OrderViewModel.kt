package com.christopheraldoo.adminwafeoffood.order.viewmodel

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.adminwafeoffood.order.model.*
import com.christopheraldoo.adminwafeoffood.order.repository.OrderRepository
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.CancellationException

class OrderViewModel : ViewModel() {
    
    private val repository = OrderRepository()
    
    // StateFlow untuk UI
    private val _orderList = MutableStateFlow<List<Order>>(emptyList())
    val orderList: StateFlow<List<Order>> = _orderList.asStateFlow()
    
    private val _filteredOrderList = MutableStateFlow<List<Order>>(emptyList())
    val filteredOrderList: StateFlow<List<Order>> = _filteredOrderList.asStateFlow()
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()
    
    private val _currentFilter = MutableStateFlow<OrderStatus?>(null)
    val currentFilter: StateFlow<OrderStatus?> = _currentFilter.asStateFlow()
    
    companion object {
        private const val TAG = "OrderViewModel"
    }
    
    init {
        Log.d(TAG, "OrderViewModel initialized")
        loadOrders()
    }
    
    private fun loadOrders() {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Starting to load orders...")
                _isLoading.value = true
                
                repository.getAllOrders()
                    .catch { exception ->
                        // Filter out cancellation exceptions
                        if (exception !is CancellationException) {
                            Log.e(TAG, "Error in order flow", exception)
                            _error.value = "Error loading orders: ${exception.message}"
                        }
                        _orderList.value = emptyList()
                        _isLoading.value = false
                    }
                    .collect { orderList ->
                        try {
                            Log.d(TAG, "Received ${orderList.size} orders from repository")
                            _orderList.value = orderList
                            
                            // Apply current filter
                            applyCurrentFilter(orderList)
                            
                            _isLoading.value = false
                        } catch (e: Exception) {
                            if (e !is CancellationException) {
                                Log.e(TAG, "Error processing order list", e)
                            }
                        }
                    }
                    
            } catch (e: Exception) {
                if (e !is CancellationException) {
                    Log.e(TAG, "Error loading orders", e)
                    _error.value = "Error loading orders: ${e.message}"
                }
                _orderList.value = emptyList()
                _isLoading.value = false
            }
        }
    }
    
    private fun applyCurrentFilter(orderList: List<Order>) {
        try {
            val currentFilterValue = _currentFilter.value
            
            val filtered = if (currentFilterValue == null) {
                orderList
            } else {
                orderList.filter { it.status == currentFilterValue }
            }
            
            _filteredOrderList.value = filtered
            Log.d(TAG, "Applied filter ${currentFilterValue?.name ?: "ALL"}: ${filtered.size} orders")
        } catch (e: Exception) {
            Log.e(TAG, "Error applying filter", e)
        }
    }
    
    // Filter methods
    fun filterByStatus(status: OrderStatus?) {
        try {
            _currentFilter.value = status
            applyCurrentFilter(_orderList.value)
            Log.d(TAG, "Filter changed to: ${status?.name ?: "ALL"}")
        } catch (e: Exception) {
            Log.e(TAG, "Error changing filter", e)
        }
    }
    
    fun clearFilter() {
        filterByStatus(null)
    }
    
    // Order status update
    fun updateOrderStatus(orderId: String, newStatus: OrderStatus) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Updating order status: $orderId -> $newStatus")
                
                val response = repository.updateOrderStatus(orderId, newStatus)
                
                // Show feedback message
                _error.value = response.message
                
                if (response.result == OrderOperationResult.SUCCESS) {
                    Log.d(TAG, "Order status updated successfully")
                }
                
            } catch (e: Exception) {
                if (e !is CancellationException) {
                    Log.e(TAG, "Error updating order status", e)
                    _error.value = "Failed to update order status: ${e.message}"
                }
            }
        }
    }
    
    // Get order by ID
    suspend fun getOrderById(orderId: String): Order? {
        return try {
            repository.getOrderById(orderId)
        } catch (e: Exception) {
            Log.e(TAG, "Error getting order by ID", e)
            _error.value = "Error loading order details: ${e.message}"
            null
        }
    }
    
    // Clear error
    fun clearError() {
        _error.value = null
        Log.d(TAG, "Error cleared")
    }
    
    // Add test order (untuk testing)
    fun addTestOrder() {
        viewModelScope.launch {
            try {
                val testOrder = Order(
                    customerName = "Test Customer ${System.currentTimeMillis()}",
                    customerPhone = "081234567890",
                    customerAddress = "Jl. Test No. 123",
                    items = listOf(
                        OrderItem(
                            menuId = "test_menu_1",
                            menuName = "Nasi Goreng",
                            menuPrice = 25000.0,
                            quantity = 2,
                            subtotal = 50000.0
                        )
                    ),
                    totalAmount = 50000.0,
                    paymentMethod = "Cash",
                    notes = "Test order from admin"
                )
                
                val response = repository.addOrder(testOrder)
                _error.value = response.message
                
            } catch (e: Exception) {
                Log.e(TAG, "Error adding test order", e)
                _error.value = "Failed to add test order: ${e.message}"
            }
        }
    }
    
    // Delete order
    fun deleteOrder(orderId: String) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Deleting order: $orderId")
                
                val response = repository.deleteOrder(orderId)
                _error.value = response.message
                
            } catch (e: Exception) {
                Log.e(TAG, "Error deleting order", e)
                _error.value = "Failed to delete order: ${e.message}"
            }
        }
    }
}