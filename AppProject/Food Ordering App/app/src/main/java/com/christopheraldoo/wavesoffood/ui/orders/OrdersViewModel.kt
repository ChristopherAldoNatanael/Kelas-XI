package com.christopheraldoo.wavesoffood.ui.orders

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.data.repository.OrderRepository
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

/**
 * ViewModel untuk mengelola order history dan tracking
 */
class OrdersViewModel : ViewModel() {
    
    private val orderRepository = OrderRepository.getInstance()
    
    private val _orders = MutableStateFlow<List<Order>>(emptyList())
    val orders: StateFlow<List<Order>> = _orders.asStateFlow()
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage.asStateFlow()
    
    private val _selectedOrder = MutableStateFlow<Order?>(null)
    val selectedOrder: StateFlow<Order?> = _selectedOrder.asStateFlow()
    
    // Filter states
    private val _selectedStatus = MutableStateFlow<OrderStatus?>(null)
    val selectedStatus: StateFlow<OrderStatus?> = _selectedStatus.asStateFlow()
    
    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()
    
    // Filtered orders based on status and search
    val filteredOrders: StateFlow<List<Order>> = combine(
        orders,
        selectedStatus,
        searchQuery
    ) { ordersList, status, query ->
        var filtered = ordersList
        
        // Filter by status
        if (status != null) {
            filtered = filtered.filter { it.status == status }
        }
        
        // Filter by search query
        if (query.isNotEmpty()) {
            filtered = filtered.filter { order ->
                order.id.contains(query, ignoreCase = true) ||
                order.items.any { it.menuName.contains(query, ignoreCase = true) } ||
                order.recipientName.contains(query, ignoreCase = true)
            }
        }
        
        filtered
    }.stateIn(
        scope = viewModelScope,
        started = SharingStarted.WhileSubscribed(5000),
        initialValue = emptyList()
    )
    
    init {
        loadOrders()
    }
    
    /**
     * Load user orders from repository
     */
    fun loadOrders() {        viewModelScope.launch {
            Log.d("OrdersViewModel", "Loading orders...")
            _isLoading.value = true
            _errorMessage.value = null
            
            orderRepository.getUserOrders()
                .catch { exception: Throwable ->
                    Log.e("OrdersViewModel", "Error loading orders: ${exception.message}")
                    _errorMessage.value = "Failed to load orders: ${exception.message}"
                    _isLoading.value = false
                }
                .collect { ordersList: List<Order> ->
                    Log.d("OrdersViewModel", "Loaded ${ordersList.size} orders")
                    _isLoading.value = false
                    _orders.value = ordersList
                }
        }
    }
    
    /**
     * Refresh orders data
     */
    fun refreshOrders() {
        loadOrders()
    }
    
    /**
     * Get order by ID and set as selected
     */
    fun selectOrder(orderId: String) {
        viewModelScope.launch {
            _isLoading.value = true
            val result = orderRepository.getOrderById(orderId)
            _isLoading.value = false
              result.fold(
                onSuccess = { order: Order? ->
                    _selectedOrder.value = order
                },
                onFailure = { exception: Throwable ->
                    _errorMessage.value = "Failed to load order details: ${exception.message}"
                }
            )
        }
    }
    
    /**
     * Cancel an order
     */
    fun cancelOrder(orderId: String, reason: String = "") {
        viewModelScope.launch {            _isLoading.value = true
            val result = orderRepository.cancelOrder(orderId, reason)
            _isLoading.value = false
            
            result.fold(
                onSuccess = { _: Unit ->
                    // Refresh orders to show updated status
                    loadOrders()
                },
                onFailure = { exception: Throwable ->
                    _errorMessage.value = "Failed to cancel order: ${exception.message}"
                }
            )
        }
    }
    
    /**
     * Update delivery address
     */
    fun updateDeliveryAddress(orderId: String, newAddress: String) {
        viewModelScope.launch {
            _isLoading.value = true
            val result = orderRepository.updateDeliveryAddress(orderId, newAddress)
            _isLoading.value = false
              result.fold(
                onSuccess = { success: Boolean ->
                    // Refresh orders to show updated address
                    loadOrders()
                },
                onFailure = { exception: Throwable ->
                    _errorMessage.value = "Failed to update address: ${exception.message}"
                }
            )
        }
    }
    
    /**
     * Reorder items from a previous order by adding them to cart
     */
    fun reorderItems(order: Order) {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                val cartRepository = com.christopheraldoo.wavesoffood.ui.cart.CartRepository.getInstance()
                
                // Convert OrderItems back to CartItems and add to cart
                var successCount = 0
                for (orderItem in order.items) {
                    val result = cartRepository.addToCart(
                        menuId = orderItem.menuId,
                        menuName = orderItem.menuName,
                        menuImageUrl = orderItem.menuImageUrl,
                        price = orderItem.price,
                        quantity = orderItem.quantity,
                        category = orderItem.category
                    )
                    
                    if (result.isSuccess) {
                        successCount++
                    }
                }
                
                if (successCount == order.items.size) {
                    _errorMessage.value = "All items added to cart successfully!"
                } else if (successCount > 0) {
                    _errorMessage.value = "$successCount of ${order.items.size} items added to cart"
                } else {
                    _errorMessage.value = "Failed to add items to cart"
                }
                
            } catch (e: Exception) {
                _errorMessage.value = "Error during reorder: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    /**
     * Set filter status
     */
    fun setFilterStatus(status: OrderStatus?) {
        _selectedStatus.value = status
    }
    
    /**
     * Set search query
     */
    fun setSearchQuery(query: String) {
        _searchQuery.value = query
    }
    
    /**
     * Clear error message
     */
    fun clearError() {
        _errorMessage.value = null
    }
    
    /**
     * Clear selected order
     */
    fun clearSelectedOrder() {
        _selectedOrder.value = null
    }
      /**
     * Get orders count by status
     */
    fun getOrdersCountByStatus(): Map<OrderStatus, Int> {
        return orders.value.groupBy { order: Order -> order.status }.mapValues { entry: Map.Entry<OrderStatus, List<Order>> -> entry.value.size }
    }
    
    /**
     * Get total amount spent
     */
    fun getTotalAmountSpent(): Int {
        return orders.value.filter { order: Order -> order.status == OrderStatus.COMPLETED }.sumOf { order: Order -> order.total }
    }
      /**
     * Get recent orders (last 7 days)
     */
    fun getRecentOrders(): List<Order> {
        val sevenDaysAgo = System.currentTimeMillis() - (7 * 24 * 60 * 60 * 1000)
        return orders.value.filter { order: Order -> order.createdAt >= sevenDaysAgo }
    }
}
