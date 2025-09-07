package com.christopheraldoo.wavesoffood.ui.checkout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.wavesoffood.data.repository.OrderRepository
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.ui.cart.CartItem
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

/**
 * ViewModel untuk mengelola proses checkout
 */
class CheckoutViewModel : ViewModel() {
    
    private val orderRepository = OrderRepository.getInstance()
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage.asStateFlow()
    
    private val _orderCreated = MutableStateFlow<String?>(null)
    val orderCreated: StateFlow<String?> = _orderCreated.asStateFlow()
    
    /**
     * Membuat order baru
     */
    fun createOrder(
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
    ) {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            
            val result = orderRepository.createOrder(
                cartItems = cartItems,
                subtotal = subtotal,
                discount = discount,
                deliveryFee = deliveryFee,
                total = total,
                orderType = orderType,
                paymentMethod = paymentMethod,
                recipientName = recipientName,
                deliveryAddress = deliveryAddress,
                notes = notes
            )
            
            _isLoading.value = false
              result.fold(
                onSuccess = { orderId: String ->
                    _orderCreated.value = orderId
                },
                onFailure = { exception: Throwable ->
                    _errorMessage.value = "Failed to create order: ${exception.message}"
                }
            )
        }
    }
    
    /**
     * Clear error message
     */
    fun clearError() {
        _errorMessage.value = null
    }
    
    /**
     * Clear order created state
     */
    fun clearOrderCreated() {
        _orderCreated.value = null
    }
    
    /**
     * Reorder items from a previous order
     */
    fun reorderItems(order: Order): Flow<Result<Boolean>> = flow {
        try {
            emit(Result.success(true))
            // TODO: Implement cart integration to add previous order items
            // This would involve converting OrderItems back to CartItems
            // and adding them to the user's cart
        } catch (e: Exception) {
            emit(Result.failure(e))
        }
    }
    
    /**
     * Validate checkout form
     */
    fun validateCheckoutForm(
        recipientName: String,
        deliveryAddress: String,
        orderType: String,
        paymentMethod: String
    ): String? {
        return when {
            recipientName.isBlank() -> "Recipient name is required"
            recipientName.length < 2 -> "Recipient name must be at least 2 characters"
            deliveryAddress.isBlank() -> "Delivery address is required"
            deliveryAddress.length < 10 -> "Please enter a complete address"
            orderType.isBlank() -> "Please select order type"
            paymentMethod.isBlank() -> "Please select payment method"
            else -> null
        }
    }
}
