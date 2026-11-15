package com.christopheraldoo.petheal.ui.screens.petshop

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopPickupPass
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
    /**
     * URL Snap yang dibuka di WebView IN-APP (bukan browser luar).
     * `null` = tidak ada halaman pembayaran yang aktif.
     */
    val paymentUrl: String? = null,
    /** Sedang memverifikasi status pembayaran ke server. */
    val isSyncingPayment: Boolean = false,
    /** Pickup pass hanya ada setelah pesanan siap diambil. */
    val pickupPass: PetShopPickupPass? = null,
    val pickupQr: Bitmap? = null,
    val isLoadingPass: Boolean = false
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
                is Result.Success -> {
                    _uiState.update {
                        it.copy(isLoading = false, order = result.data)
                    }
                    if (result.data.pickupPassAvailable) loadPickupPass()
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    /**
     * Muat QR Pickup Pass. Token tidak disimpan di disk dan QR hanya tampil
     * untuk pesanan yang benar-benar siap diambil.
     */
    fun loadPickupPass() {
        if (orderId <= 0 || _uiState.value.isLoadingPass) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoadingPass = true) }

            val pass = (repository.loadPickupPass(orderId) as? Result.Success)?.data

            if (pass == null) {
                _uiState.update { it.copy(isLoadingPass = false) }
                return@launch
            }

            val bitmap = (repository.loadPickupQr(orderId) as? Result.Success)?.data
                ?.let { bytes -> BitmapFactory.decodeByteArray(bytes, 0, bytes.size) }

            _uiState.update {
                it.copy(isLoadingPass = false, pickupPass = pass, pickupQr = bitmap)
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
     * Minta transaksi online. Halaman pembayaran dibuka IN-APP (WebView
     * Midtrans), bukan browser luar. Status TIDAK pernah dianggap lunas dari
     * sisi klien — setelah user membayar, [verifyPayment] memverifikasi ke
     * server lalu detail pesanan dimuat ulang dengan status terbaru.
     */
    fun payNow() {
        val order = _uiState.value.order ?: return
        if (!order.isAwaitingOnlinePayment || _uiState.value.isActionRunning) return

        viewModelScope.launch {
            _uiState.update { it.copy(isActionRunning = true, error = null, notice = null) }
            when (val result = repository.createPayment(order.id)) {
                is Result.Success -> {
                    val payment = result.data
                    val url = payment.redirectUrl?.takeIf { it.isNotBlank() }
                        ?: payment.snapToken?.takeIf { it.isNotBlank() }
                            ?.let { "${BuildConfig.MIDTRANS_SNAP_URL}$it" }

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

    /**
     * Verifikasi status pembayaran ke server lalu tutup halaman pembayaran.
     *
     * Dipanggil setelah halaman Snap selesai (callback/finish) maupun saat
     * user menutup halaman pembayaran. Sumber kebenaran tetap server
     * (Midtrans), bukan state lokal.
     */
    fun verifyPayment() {
        if (orderId <= 0 || _uiState.value.isSyncingPayment) return

        viewModelScope.launch {
            _uiState.update { it.copy(isSyncingPayment = true) }

            val status = (repository.syncPayment(orderId) as? Result.Success)?.data

            _uiState.update {
                it.copy(
                    isSyncingPayment = false,
                    // Verifikasi gagal → halaman pembayaran tetap dibuka agar
                    // user bisa mencoba lagi tanpa kehilangan transaksi.
                    paymentUrl = if (status == null) it.paymentUrl else null,
                    notice = when {
                        status == null ->
                            "Status pembayaran belum dapat dipastikan. Coba lagi atau gunakan \"Cek Status Pembayaran\"."
                        status.paymentStatus == PetShopOrder.PAYMENT_PAID ->
                            "Pembayaran berhasil. Status pesanan sudah diperbarui."
                        else ->
                            "Status pembayaran: ${status.paymentStatus}"
                    }
                )
            }

            // Muat ulang pesanan agar status pembayaran & pickup pass
            // langsung terlihat di halaman detail.
            (repository.loadOrder(orderId) as? Result.Success)?.let { loaded ->
                _uiState.update { it.copy(order = loaded.data) }
                if (loaded.data.pickupPassAvailable) loadPickupPass()
            }
        }
    }

    /**
     * Tutup halaman pembayaran tanpa memverifikasi (user menekan X dan
     * memang belum selesai membayar). Status tetap bisa dicek manual lewat
     * "Cek Status Pembayaran".
     */
    fun dismissPayment() {
        _uiState.update { it.copy(paymentUrl = null) }
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
                        if (loaded.data.pickupPassAvailable) loadPickupPass()
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isActionRunning = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isActionRunning = false) }
            }
        }
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }
}
