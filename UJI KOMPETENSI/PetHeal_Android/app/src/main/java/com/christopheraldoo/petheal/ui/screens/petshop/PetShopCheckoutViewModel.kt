package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.repository.PetShopCartLine
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PetShopCheckoutUiState(
    val lines: List<PetShopCartLine> = emptyList(),
    val notes: String = "",
    val phone: String = "",
    val paymentMethod: String = PetShopOrder.METHOD_PAY_AT_CLINIC,
    /**
     * true  = klinik punya Midtrans sendiri (atau central fallback aktif).
     * false = server eksplisit menolak online payment untuk klinik ini
     *         (misalnya: konfigurasi klinik bermasalah & fallback dimatikan).
     * Default true: Android tidak tahu kondisi server sampai order gagal.
     */
    val isOnlineAvailable: Boolean = true,
    val isSubmitting: Boolean = false,
    val error: String? = null,
    val notice: String? = null,
    val placedOrder: PetShopOrder? = null
) {
    val subtotal: Long get() = lines.sumOf { it.lineTotal }
    val itemCount: Int get() = lines.sumOf { it.quantity }
    val clinicName: String get() = lines.firstOrNull()?.clinicName.orEmpty()
    val isEmpty: Boolean get() = lines.isEmpty()
}

@HiltViewModel
class PetShopCheckoutViewModel @Inject constructor(
    private val repository: PetShopRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PetShopCheckoutUiState())
    val uiState: StateFlow<PetShopCheckoutUiState> = _uiState.asStateFlow()

    /** Pilihan user tidak boleh ditimpa saat isi keranjang berubah. */
    private var paymentMethodChosen = false

    init {
        viewModelScope.launch {
            repository.cart.collect { lines ->
                _uiState.update { state ->
                    state.copy(
                        lines = lines,
                        // isOnlineAvailable tetap true (default). Akan diset false
                        // hanya bila server mengembalikan code=online_payment_unavailable
                        // di placeOrder(). Klinik tanpa Midtrans sendiri tetap bisa
                        // bayar online via akun pusat (central fallback).
                        paymentMethod = if (!paymentMethodChosen) PetShopOrder.METHOD_ONLINE
                        else state.paymentMethod
                    )
                }
            }
        }
    }

    fun onNotesChange(value: String) {
        if (value.length <= 500) _uiState.update { it.copy(notes = value) }
    }

    fun onPhoneChange(value: String) {
        if (value.length <= 20) _uiState.update { it.copy(phone = value) }
    }

    fun onPaymentMethodSelected(method: String) {
        paymentMethodChosen = true
        _uiState.update { it.copy(paymentMethod = method) }
    }

    fun updateQuantity(variantId: Int, quantity: Int) {
        val error = repository.updateQuantity(variantId, quantity)
        if (error != null) _uiState.update { it.copy(notice = error) }
    }

    fun removeLine(variantId: Int) = repository.removeFromCart(variantId)

    fun placeOrder() {
        val state = _uiState.value
        if (state.isSubmitting || state.isEmpty) return

        if (state.phone.isNotBlank() && !state.phone.matches(Regex("^[0-9+\\-\\s()]{8,20}$"))) {
            _uiState.update { it.copy(error = "Nomor telepon tidak valid.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isSubmitting = true, error = null, notice = null) }

            when (val result = repository.placeOrder(
                paymentMethod = state.paymentMethod,
                notes = state.notes,
                customerPhone = state.phone
            )) {
                is Result.Success -> _uiState.update {
                    it.copy(isSubmitting = false, placedOrder = result.data)
                }
                is Result.Error -> {
                    if (result.code == "online_payment_unavailable") {
                        // Server menolak online payment → paksa ke bayar di klinik
                        // dan beritahu user dengan pesan yang jelas.
                        _uiState.update {
                            it.copy(
                                isSubmitting = false,
                                isOnlineAvailable = false,
                                paymentMethod = PetShopOrder.METHOD_PAY_AT_CLINIC,
                                notice = "Pembayaran online tidak tersedia untuk klinik ini. Metode diubah ke bayar di klinik."
                            )
                        }
                    } else {
                        _uiState.update { it.copy(isSubmitting = false, error = result.message) }
                    }
                }
                else -> _uiState.update { it.copy(isSubmitting = false) }
            }
        }
    }

    fun consumePlacedOrder() {
        _uiState.update { it.copy(placedOrder = null) }
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }
}
