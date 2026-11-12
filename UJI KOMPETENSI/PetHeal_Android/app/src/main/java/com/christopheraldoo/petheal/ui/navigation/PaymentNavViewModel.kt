package com.christopheraldoo.petheal.ui.navigation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.User
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.BookingRepository
import com.christopheraldoo.petheal.data.repository.BookingRefreshManager
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.MedicalRefreshManager
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PaymentNavState(
    val booking: Booking? = null,
    val user: User? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
    // Diisi hanya utk alur biaya tambahan rekam medis (recordId > 0).
    val medicalRecord: MedicalRecord? = null
) {
    /**
     * MULTI-CLINIC (2026-11-11): kop bukti pembayaran SELALU mengikuti
     * klinik milik transaksi — klinik booking untuk pembayaran booking, dan
     * klinik rekam medis untuk biaya tambahan. Sebelumnya nilai ini diambil
     * dari preferensi "klinik aktif", sehingga membayar di Klinik A bisa
     * mencetak kwitansi atas nama Klinik B (klinik terakhir dipakai).
     */
    val receiptClinicName: String?
        get() = (medicalRecord?.clinic ?: booking?.clinic)?.name

    val receiptClinicAddress: String?
        get() = (medicalRecord?.clinic ?: booking?.clinic)?.address

    val receiptClinicColor: String?
        get() = (medicalRecord?.clinic ?: booking?.clinic)?.primaryColor
}

@HiltViewModel
class PaymentNavViewModel @Inject constructor(
    private val bookingRepository: BookingRepository,
    private val authRepository: AuthRepository,
    private val bookingRefreshManager: BookingRefreshManager,
    private val medicalRefreshManager: MedicalRefreshManager,
    private val medicalRecordRepository: MedicalRecordRepository
) : ViewModel() {

    private val _state = MutableStateFlow(PaymentNavState())
    val state: StateFlow<PaymentNavState> = _state.asStateFlow()

    fun loadBookingAndUser(bookingId: Int) {
        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true, error = null)

            var booking: Booking? = null
            var user: User? = null

            // Load booking
            when (val bookingResult = bookingRepository.getBooking(bookingId)) {
                is Result.Success -> booking = bookingResult.data
                is Result.Error -> _state.value = _state.value.copy(error = bookingResult.message)
                else -> Unit
            }

            // Load user
            when (val userResult = authRepository.getProfile()) {
                is Result.Success -> user = userResult.data
                else -> Unit
            }

            _state.value = _state.value.copy(
                isLoading = false,
                booking = booking,
                user = user
            )
        }
    }

    /**
     * Muat rekam medis TERBARU untuk bukti biaya tambahan. forceRefresh =
     * true karena pembayaran baru saja mengubah extra_payment_* di server;
     * cache lama akan mencetak bukti dengan status belum terbayar.
     * Kegagalan bersifat best-effort: tanpa record, layar hasil tetap tampil
     * tanpa kartu bukti (tidak pernah ada angka karangan).
     */
    fun loadMedicalRecord(recordId: Int) {
        viewModelScope.launch {
            when (val result = medicalRecordRepository.getMedicalRecord(recordId, forceRefresh = true)) {
                is Result.Success -> _state.value = _state.value.copy(medicalRecord = result.data)
                else -> Unit
            }
        }
    }

    fun notifyBookingUpdated() {
        bookingRefreshManager.requestRefresh()
    }

    /**
     * Dipanggil setelah additional (extra) payment rekam medis sukses:
     * menginvalidasi sinyal medical agar list + detail melakukan
     * force re-fetch dan status terkunci → tersedia tanpa restart.
     */
    fun notifyMedicalUpdated() {
        bookingRefreshManager.requestRefresh()
        medicalRefreshManager.requestRefresh()
    }
}
