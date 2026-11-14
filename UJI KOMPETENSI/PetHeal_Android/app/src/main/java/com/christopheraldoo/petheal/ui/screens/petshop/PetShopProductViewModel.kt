package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.data.model.PetShopVariant
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PetShopProductUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val product: PetShopProduct? = null,
    val selectedVariantId: Int? = null,
    val quantity: Int = 1,
    val cartCount: Int = 0,
    val notice: String? = null,
    val addedToCart: Boolean = false
) {
    val selectedVariant: PetShopVariant?
        get() = product?.variants?.firstOrNull { it.id == selectedVariantId }

    val canAddToCart: Boolean
        get() = selectedVariant?.let { it.isAvailable && it.availableStock >= quantity } == true
}

@HiltViewModel
class PetShopProductViewModel @Inject constructor(
    private val repository: PetShopRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    // Argumen navigasi bertipe IntType, jadi harus dibaca sebagai Int.
    // Membacanya sebagai String memicu ClassCastException saat layar dibuka.
    private val productId: Int = savedStateHandle.get<Int>("productId") ?: 0

    private val _uiState = MutableStateFlow(PetShopProductUiState())
    val uiState: StateFlow<PetShopProductUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            repository.cart.collect { lines ->
                _uiState.update { it.copy(cartCount = lines.sumOf { line -> line.quantity }) }
            }
        }
        load()
    }

    fun load() {
        if (productId <= 0) {
            _uiState.update { it.copy(isLoading = false, error = "Produk tidak ditemukan.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = repository.loadProduct(productId)) {
                is Result.Success -> {
                    val product = result.data
                    val first = product.purchasableVariants.firstOrNull() ?: product.variants.firstOrNull()
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            product = product,
                            selectedVariantId = first?.id,
                            quantity = 1
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    fun selectVariant(variantId: Int) {
        _uiState.update {
            val variant = it.product?.variants?.firstOrNull { v -> v.id == variantId }
            it.copy(
                selectedVariantId = variantId,
                // Jangan biarkan jumlah lama melebihi stok varian baru.
                quantity = it.quantity.coerceIn(1, maxOf(1, variant?.availableStock ?: 1))
            )
        }
    }

    fun incrementQuantity() {
        _uiState.update {
            val max = it.selectedVariant?.availableStock ?: 1
            it.copy(quantity = (it.quantity + 1).coerceAtMost(maxOf(1, max)))
        }
    }

    fun decrementQuantity() {
        _uiState.update { it.copy(quantity = (it.quantity - 1).coerceAtLeast(1)) }
    }

    fun addToCart() {
        val state = _uiState.value
        val product = state.product ?: return
        val variant = state.selectedVariant ?: return

        val error = repository.addToCart(product, variant, state.quantity)
        _uiState.update {
            it.copy(
                notice = error ?: "Ditambahkan ke keranjang.",
                addedToCart = error == null
            )
        }
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }

    fun consumeAddedToCart() {
        _uiState.update { it.copy(addedToCart = false) }
    }
}
