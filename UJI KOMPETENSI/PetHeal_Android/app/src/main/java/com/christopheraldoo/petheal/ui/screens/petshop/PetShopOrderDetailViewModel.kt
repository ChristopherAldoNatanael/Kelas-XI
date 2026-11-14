package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PetShopOrderDetailUiState(
    val isLoading: Boolean = true,
    val isActionRunning: Boolean = false,
    val error: String? = null,
    val order: PetShopOrder? = null,
    val notice: String? = null,
    /** URL Snap yang harus dibuka browser. Tidak pernah dianggap sukses. */
    val paymentUrl: String? = null
)

@HiltViewModel
class PetShopOrderDetailViewModel @Inject constructor(
    private val repository: PetShopRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    // Sama seperti productId: argumen rute Int harus dibaca sebagai Int.
    private val orderId: Int = savedStateHandle.get<Int>("orderId") ?: 0

    private val _uiState = MutableStateFlow(PetShopOrderDetailUiState())
    val uiState: StateFlow<PetShopOrderDetailUiState> = _uiState.asStateFlow()

    init {
        load()
    }

    fun load() {
        if (orderId <= 0) {
            _uiState.update { it.copy(isLoading = false, error = "Pesanan tidak ditemukan.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = repository.loadOrder(orderId)) {
                is Result.Success -> _uiState.update {
                    it.copy(isLoading = false, order = result.data)
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    fun refreshSilently() {
        if (orderId <= 0) return
        viewModelScope.launch {
            (repository.loadOrder(orderId) as? Result.Success)?.let { loaded ->
                _uiState.update { it.copy(order = loaded.data) }
            }
        }
    }

    fun cancelOrder(reason: String?) {
        val order = _uiState.value.order ?: return
        if (!order.canCancel || _uiState.value.isActionRunning) return

        viewModelScope.launch {
            _uiState.update { it.copy(isActionRunning = true, error = null, notice = null) }
            when (val result = repository.cancelOrder(order.id, reason)) {
                is Result.Success -> _uiState.update {
                    it.copy(
                        isActionRunning = false,
                        order = result.data,
                        notice = "Pesanan dibatalkan."
                    )
                }
                is Result.Error -> _uiState.update {
                    it.copy(isActionRunning = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isActionRunning = false) }
            }
        }
    }

    /**
     * Minta transaksi online. Setelah URL dikembalikan, aplikasi membuka
     * halaman pembayaran; status tetap diverifikasi server (webhook) dan bisa
     * dicek ulang lewat [checkPaymentStatus].
     */
    fun payNow() {
        val order = _uiState.value.order ?: return
        if (!order.isAwaitingOnlinePayment || _uiState.value.isActionRunning) return

        viewModelScope.launch {
            _uiState.update { it.copy(isActionRunning = true, error = null, notice = null) }
            when (val result = repository.createPayment(order.id)) {
                is Result.Success -> {
                    val url = result.data.redirectUrl
                    _uiState.update {
                        it.copy(
                            isActionRunning = false,
                            paymentUrl = url,
                            notice = if (url.isNullOrBlank()) {
                                "Tautan pembayaran tidak tersedia. Coba lagi nanti."
                            } else {
                                null
                            }
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isActionRunning = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isActionRunning = false) }
            }
        }
    }

    /** Sinkronkan status pembayaran + pesanan dari server (bukan asumsi UI). */
    fun checkPaymentStatus() {
        if (orderId <= 0 || _uiState.value.isActionRunning) return

        viewModelScope.launch {
            _uiState.update { it.copy(isActionRunning = true, notice = null) }
            when (val result = repository.paymentStatus(orderId)) {
                is Result.Success -> {
                    val status = result.data
                    _uiState.update {
                        it.copy(
                            isActionRunning = false,
                            notice = if (status.paymentStatus == PetShopOrder.PAYMENT_PAID) {
                                "Pembayaran diterima. Terima kasih!"
                            } else {
                                "Status pembayaran: ${status.paymentStatus}"
                            }
                        )
                    }
                    (repository.loadOrder(orderId) as? Result.Success)?.let { loaded ->
                        _uiState.update { it.copy(order = loaded.data) }
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isActionRunning = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isActionRunning = false) }
            }
        }
    }

    fun consumePaymentUrl() {
        _uiState.update { it.copy(paymentUrl = null) }
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }
}
