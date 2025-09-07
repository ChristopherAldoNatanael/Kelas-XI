package com.christopheraldoo.wavesoffood.ui.order

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.data.repository.OrderRepository
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

/**
 * ViewModel untuk mengelola order history
 */
class OrderViewModel : ViewModel() {
    
    private val orderRepository = OrderRepository()
    
    // UI State
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage.asStateFlow()
    
    private val _orders = MutableStateFlow<List<Order>>(emptyList())
    val orders: StateFlow<List<Order>> = _orders.asStateFlow()
    
    private val _orderStats = MutableStateFlow<Map<String, Int>>(emptyMap())
    val orderStats: StateFlow<Map<String, Int>> = _orderStats.asStateFlow()
    
    init {
        loadOrders()
        loadOrderStats()
    }
    
    /**
     * Load user orders
     */
    private fun loadOrders() {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _errorMessage.value = null
                
                orderRepository.getUserOrders()
                    .catch { error ->
                        _errorMessage.value = error.message ?: "Failed to load orders"
                        _orders.value = emptyList()
                    }
                    .collect { orderList ->
                        _orders.value = orderList
                    }
            } finally {
                _isLoading.value = false
            }
        }
    }
    
    /**
     * Load order statistics
     */
    private fun loadOrderStats() {
        viewModelScope.launch {
            try {
                val result = orderRepository.getUserOrderStats()
                result.onSuccess { stats ->
                    _orderStats.value = stats
                }.onFailure { error ->
                    _errorMessage.value = error.message ?: "Failed to load statistics"
                }
            } catch (e: Exception) {
                _errorMessage.value = e.message ?: "Failed to load statistics"
            }
        }
    }
    
    /**
     * Cancel order
     */
    fun cancelOrder(orderId: String, reason: String = "") {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _errorMessage.value = null
                
                val result = orderRepository.cancelOrder(orderId, reason)
                result.onSuccess {
                    // Orders will be updated automatically through Flow
                }.onFailure { error ->
                    _errorMessage.value = error.message ?: "Failed to cancel order"
                }
            } catch (e: Exception) {
                _errorMessage.value = e.message ?: "Failed to cancel order"
            } finally {
                _isLoading.value = false
            }
        }
    }
    
    /**
     * Refresh orders
     */
    fun refreshOrders() {
        loadOrders()
        loadOrderStats()
    }
    
    /**
     * Clear error message
     */
    fun clearError() {
        _errorMessage.value = null
    }
    
    /**
     * Get filtered orders by status
     */
    fun getOrdersByStatus(status: OrderStatus): StateFlow<List<Order>> {
        return _orders.map { orders ->
            orders.filter { it.status == status }
        }.stateIn(
            scope = viewModelScope,
            started = SharingStarted.WhileSubscribed(5000),
            initialValue = emptyList()
        )
    }
    
    /**
     * Get pending orders count
     */
    fun getPendingOrdersCount(): StateFlow<Int> {
        return _orders.map { orders ->
            orders.count { order ->
                order.status in listOf(
                    OrderStatus.PENDING,
                    OrderStatus.CONFIRMED,
                    OrderStatus.PREPARING,
                    OrderStatus.READY,
                    OrderStatus.DELIVERING
                )
            }
        }.stateIn(
            scope = viewModelScope,
            started = SharingStarted.WhileSubscribed(5000),
            initialValue = 0
        )
    }
}
