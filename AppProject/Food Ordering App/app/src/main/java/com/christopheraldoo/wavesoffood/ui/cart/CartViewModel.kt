package com.christopheraldoo.wavesoffood.ui.cart

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.wavesoffood.ui.cart.CartRepository
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class CartViewModel : ViewModel() {
    
    private val cartRepository = CartRepository.getInstance()
    private val auth = FirebaseAuth.getInstance()
    
    private val _cartItems = MutableStateFlow<List<CartItem>>(emptyList())
    val cartItems: StateFlow<List<CartItem>> = _cartItems.asStateFlow()
    
    private val _cartTotal = MutableStateFlow(0)
    val cartTotal: StateFlow<Int> = _cartTotal.asStateFlow()
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage.asStateFlow()

    init {
        loadCartData()
    }

    fun loadCartData() {
        val user = auth.currentUser
        if (user != null) {
            viewModelScope.launch {
                _isLoading.value = true
                cartRepository.getUserCart().collect { result ->
                    result.fold(
                        onSuccess = { items ->
                            _cartItems.value = items
                            _cartTotal.value = items.sumOf { it.calculateTotalPrice() }
                            _isLoading.value = false
                        },
                        onFailure = { error ->
                            _errorMessage.value = error.message
                            _isLoading.value = false
                        }
                    )
                }
            }
        }
    }

    fun updateCartItemQuantity(itemId: String, newQuantity: Int) {
        val user = auth.currentUser
        if (user != null) {
            viewModelScope.launch {
                cartRepository.updateCartItemQuantity(itemId, newQuantity).fold(
                    onSuccess = {
                        loadCartData()
                    },
                    onFailure = { error ->
                        _errorMessage.value = error.message
                    }
                )
            }
        }
    }

    fun removeFromCart(itemId: String) {
        val user = auth.currentUser
        if (user != null) {
            viewModelScope.launch {
                cartRepository.removeFromCart(itemId).fold(
                    onSuccess = {
                        loadCartData()
                    },
                    onFailure = { error ->
                        _errorMessage.value = error.message
                    }
                )
            }
        }
    }

    fun clearCart() {
        val user = auth.currentUser
        if (user != null) {
            viewModelScope.launch {
                cartRepository.clearUserCart().fold(
                    onSuccess = {
                        loadCartData()
                    },
                    onFailure = { error ->
                        _errorMessage.value = error.message
                    }
                )
            }
        }
    }
    
    fun refreshCart() {
        // Alias for loadCartData to explicitly refresh cart
        loadCartData()
    }
}
