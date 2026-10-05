package com.christopheraldoo.petheal.ui.screens.doctor

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.DoctorReview
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.data.repository.BookingRepository
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.data.model.Pet
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import javax.inject.Inject

data class DoctorsUiState(
    val doctors: List<Doctor> = emptyList(),
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val error: String? = null,
    val notice: String? = null,
    val searchQuery: String = ""
) {
    val filtered: List<Doctor>
        get() = if (searchQuery.isBlank()) doctors
                else doctors.filter {
                    it.name?.contains(searchQuery, ignoreCase = true) == true ||
                    it.specialization?.contains(searchQuery, ignoreCase = true) == true
                }
}

data class DoctorDetailUiState(
    val doctor: Doctor? = null,
    val pets: List<Pet> = emptyList(),
    val slots: List<TimeSlot> = emptyList(),
    val selectedDate: String = LocalDate.now().plusDays(1)
        .format(DateTimeFormatter.ofPattern("yyyy-MM-dd")),
    val reviews: List<DoctorReview> = emptyList(),
    val reviewableBookings: List<Booking> = emptyList(),
    val averageRating: Double = 0.0,
    val totalReviews: Int = 0,
    val isLoading: Boolean = false,
    val isSlotsLoading: Boolean = false,
    val isReviewsLoading: Boolean = false,
    val isSubmittingReview: Boolean = false,
    val reviewMessage: String? = null,
    val error: String? = null
)

@HiltViewModel
class DoctorsViewModel @Inject constructor(
    private val doctorRepository: DoctorRepository,
    private val petRepository: PetRepository,
    private val bookingRepository: BookingRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _listState = MutableStateFlow(DoctorsUiState())
    val listState: StateFlow<DoctorsUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(DoctorDetailUiState())
    val detailState: StateFlow<DoctorDetailUiState> = _detailState.asStateFlow()

    init {
        loadDoctors()
        // Layar list & detail memakai instance ViewModel berbeda. Ketika
        // detail mem-patch rating di repository, list ikut sinkron dari
        // snapshot cache tanpa network call / tanpa refresh manual.
        viewModelScope.launch {
            doctorRepository.ratingVersion.collect { version ->
                if (version == 0L) return@collect
                val snapshot = doctorRepository.snapshotDoctors()
                if (snapshot.isNotEmpty()) {
                    _listState.value = _listState.value.copy(doctors = snapshot)
                }
            }
        }
    }

    fun loadDoctors() {
        viewModelScope.launch {
            // Tahap 1: tampilkan cache langsung (jika ada) — UI sudah isi data dalam milidetik
            when (val cached = doctorRepository.getDoctors(forceRefresh = false)) {
                is Result.Success -> {
                    _listState.value = _listState.value.copy(
                        doctors = cached.data,
                        isLoading = cached.data.isEmpty() // loading hanya jika cache kosong
                    )
                    // Jika cache sudah ada, lanjut refresh background tanpa blocking UI
                    if (cached.data.isNotEmpty()) {
                        revalidateRatings(cached.data)
                        refreshDoctorsInBackground()
                        return@launch
                    }
                }
                else -> _listState.value = _listState.value.copy(isLoading = true)
            }

            // Tahap 2: fetch dari network (hanya jika cache kosong)
            when (val result = doctorRepository.getDoctors(forceRefresh = true)) {
                is Result.Success -> {
                    _listState.value = _listState.value.copy(
                        doctors = result.data, isLoading = false
                    )
                    revalidateRatings(result.data)
                }
                is Result.Error -> _listState.value = _listState.value.copy(
                    isLoading = false, error = result.message
                )
                else -> _listState.value = _listState.value.copy(isLoading = false)
            }
        }
    }

    /** Refresh diam-diam di background tanpa mengubah loading state */
    private fun refreshDoctorsInBackground() {
        viewModelScope.launch {
            when (val result = doctorRepository.getDoctors(forceRefresh = true)) {
                is Result.Success -> {
                    if (result.data.isNotEmpty()) {
                        _listState.value = _listState.value.copy(doctors = result.data)
                        revalidateRatings(result.data)
                    }
                }
                else -> Unit // Biarkan — cache lama tetap tampil
            }
        }
    }

    /**
     * Periksa pasangan rating tiap baris di background: kalau ada
     * rata-rata > 0 dengan jumlah 0, betulkan dari server. Hasil patch
     * masuk lewat ratingVersion sehingga UI ter-update sendiri.
     */
    private fun revalidateRatings(doctors: List<Doctor>) {
        viewModelScope.launch {
            runCatching { doctorRepository.revalidateSuspiciousRatings(doctors) }
        }
    }

    fun onSearchChange(q: String) {
        _listState.value = _listState.value.copy(searchQuery = q)
    }

    /**
     * Refresh paksa dari network (dipakai pull-to-refresh & tombol retry).
     * List lama tetap tampil selama memuat; gagal refresh tidak menghapus
     * data yang sudah ada.
     */
    fun refreshDoctors() {
        if (_listState.value.isRefreshing) return
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isRefreshing = true, error = null, notice = null)
            when (val result = doctorRepository.getDoctors(forceRefresh = true)) {
                is Result.Success -> {
                    _listState.value = _listState.value.copy(
                        doctors = result.data,
                        isRefreshing = false,
                        notice = "Daftar dokter diperbarui · ${
                            java.time.LocalTime.now().format(
                                java.time.format.DateTimeFormatter.ofPattern("HH:mm")
                            )
                        }"
                    )
                    revalidateRatings(result.data)
                }
                is Result.Error -> _listState.value = _listState.value.copy(
                    isRefreshing = false,
                    // Hanya tampilkan error fullscreen bila belum ada data.
                    error = if (_listState.value.doctors.isEmpty()) result.message else null,
                    notice = if (_listState.value.doctors.isNotEmpty())
                        "Gagal memperbarui, menampilkan data terakhir" else null
                )
                else -> _listState.value = _listState.value.copy(isRefreshing = false)
            }
        }
    }

    fun clearNotice() {
        _listState.value = _listState.value.copy(notice = null)
    }    fun loadDoctorDetail(doctorId: Int) {
        viewModelScope.launch {
            _detailState.value = DoctorDetailUiState(isLoading = true)

            // Load doctor info (akan pakai cache per-ID jika ada — instan)
            when (val result = doctorRepository.getDoctor(doctorId)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    doctor = result.data, isLoading = false
                )
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = result.message
                )
                else -> _detailState.value = _detailState.value.copy(isLoading = false)
            }

            // Load user pets (parallel — tidak blocking)
            when (val result = petRepository.getPets()) {
                is Result.Success -> _detailState.value = _detailState.value.copy(pets = result.data)
                else -> Unit
            }

            // Load slots untuk tanggal default
            loadSlots(doctorId, _detailState.value.selectedDate)
            // BERURUTAN: reviews dulu (authoritative), baru hitung booking
            // yang bisa dinilai. Kalau paralel, booking yang SUDAH direview
            // lolos ke dialog → submit ditolak server (409) → user melihat
            // "failed to submit review" padahal bukan kesalahannya.
            val reviewedIds = fetchReviews(doctorId)
            loadReviewableBookings(doctorId, knownReviewedIds = reviewedIds)
        }
    }

    fun onDateSelected(doctorId: Int, date: String) {
        _detailState.value = _detailState.value.copy(selectedDate = date, slots = emptyList())
        loadSlots(doctorId, date)
    }

    private fun loadSlots(doctorId: Int, date: String) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isSlotsLoading = true)
            when (val result = doctorRepository.getDoctorSlots(doctorId, date)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    slots = result.data, isSlotsLoading = false
                )
                is Result.Error   -> _detailState.value = _detailState.value.copy(
                    isSlotsLoading = false
                )
                else -> _detailState.value = _detailState.value.copy(isSlotsLoading = false)
            }
        }
    }

    private fun loadReviews(doctorId: Int) {
        viewModelScope.launch { fetchReviews(doctorId) }
    }

    /**
     * Muat reviews dan kembalikan ID booking yang sudah direview.
     * Suspend agar caller bisa berurutan: hitung reviewable SETELAH data
     * ini tiba, bukan dari state yang mungkin masih kosong.
     */
    private suspend fun fetchReviews(doctorId: Int): Set<Int> {
        _detailState.value = _detailState.value.copy(isReviewsLoading = true)
        return when (val result = doctorRepository.getDoctorReviews(doctorId)) {
            is Result.Success -> {
                val reviews = result.data.reviews.orEmpty()
                _detailState.value = _detailState.value.copy(
                    reviews = reviews,
                    averageRating = result.data.averageRating ?: 0.0,
                    totalReviews = result.data.totalReviews ?: 0,
                    isReviewsLoading = false
                )
                reviews.mapNotNull { it.bookingId }.toSet()
            }
            else -> {
                _detailState.value = _detailState.value.copy(isReviewsLoading = false)
                _detailState.value.reviews.mapNotNull { it.bookingId }.toSet()
            }
        }
    }

    private fun loadReviewableBookings(doctorId: Int, knownReviewedIds: Set<Int>? = null) {
        viewModelScope.launch {
            when (val result = bookingRepository.getBookings()) {
                is Result.Success -> {
                    // Pakai ID review yang baru dimuat bila tersedia — membaca
                    // state.reviews di sini bisa kena race dengan loadReviews
                    // yang berjalan paralel, sehingga booking yang baru
                    // dinilai tetap muncul sebagai "bisa dinilai".
                    val reviewedBookingIds = knownReviewedIds
                        ?: _detailState.value.reviews.mapNotNull { it.bookingId }.toSet()
                    _detailState.value = _detailState.value.copy(
                        reviewableBookings = result.data.filter { booking ->
                            booking.doctorId == doctorId &&
                                booking.status == "completed" &&
                                booking.id != null &&
                                booking.id !in reviewedBookingIds
                        }
                    )
                }
                else -> Unit
            }
        }
    }

    fun submitReview(doctorId: Int, bookingId: Int, rating: Int, review: String?) {
        viewModelScope.launch {
            // Cegah double-rating di sisi klien: kalau booking ini sudah ada
            // di daftar review yang dimuat, jangan panggil API (server
            // menjawab 409) — beri tahu user dengan bahasa yang jelas.
            if (_detailState.value.reviews.any { it.bookingId == bookingId }) {
                _detailState.value = _detailState.value.copy(
                    reviewMessage = "Kunjungan ini sudah pernah Anda nilai.",
                    error = null
                )
                refreshRatingAfterSubmit(doctorId, submittedBookingId = bookingId)
                return@launch
            }
            _detailState.value = _detailState.value.copy(
                isSubmittingReview = true,
                reviewMessage = null,
                error = null
            )
            when (val result = doctorRepository.submitDoctorReview(doctorId, bookingId, rating, review)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isSubmittingReview = false,
                        reviewMessage = "Ulasan Anda telah dikirim. Terima kasih!"
                    )
                    // Refresh berurutan (bukan paralel): reviews dulu sampai
                    // dapat angka authoritative, baru turunkan ke cache +
                    // daftar booking yang bisa dinilai. Tanpa ini, angka di
                    // UI tetap memakai snapshot lama sampai refresh manual.
                    refreshRatingAfterSubmit(doctorId, submittedBookingId = bookingId)
                }
                is Result.Error -> {
                    // Server menolak double-rating dengan 409. Jangan tampilkan
                    // pesan teknis — anggap review sudah ada lalu sinkronkan
                    // daftar agar booking itu tidak ditawarkan lagi.
                    if (result.message.contains("already reviewed", ignoreCase = true)) {
                        _detailState.value = _detailState.value.copy(
                            isSubmittingReview = false,
                            error = null,
                            reviewMessage = "Kunjungan ini sudah pernah Anda nilai."
                        )
                        refreshRatingAfterSubmit(doctorId, submittedBookingId = bookingId)
                    } else {
                        _detailState.value = _detailState.value.copy(
                            isSubmittingReview = false,
                            error = result.message
                        )
                    }
                }
                else -> Unit
            }
        }
    }

    /**
     * Muat ulang reviews → patch cache repo + salinan doctor di detail →
     * muat ulang booking yang bisa dinilai. Satu alur, tanpa mengandalkan
     * caller untuk me-refresh layar lain.
     */
    private suspend fun refreshRatingAfterSubmit(doctorId: Int, submittedBookingId: Int) {
        _detailState.value = _detailState.value.copy(isReviewsLoading = true)
        when (val result = doctorRepository.getDoctorReviews(doctorId)) {
            is Result.Success -> {
                val data = result.data
                val freshReviews = data.reviews.orEmpty()
                val freshAvg = data.averageRating ?: 0.0
                val freshTotal = data.totalReviews ?: freshReviews.size
                _detailState.value = _detailState.value.copy(
                    reviews = freshReviews,
                    averageRating = freshAvg,
                    totalReviews = freshTotal,
                    isReviewsLoading = false,
                    // Salinan doctor di detail ikut diperbarui agar header
                    // (rating) konsisten dengan angka ulasan terbaru.
                    doctor = _detailState.value.doctor?.copy(
                        averageRating = freshAvg,
                        reviewsCount = freshTotal
                    )
                )
                // Sebarkan ke cache list + per-ID: layar list (instance VM
                // lain) menerima via ratingVersion tanpa refresh manual.
                doctorRepository.updateCachedRating(doctorId, freshAvg, freshTotal)
                val reviewedIds = freshReviews.mapNotNull { it.bookingId }.toSet() +
                    submittedBookingId
                loadReviewableBookings(doctorId, knownReviewedIds = reviewedIds)
            }
            else -> {
                // Fallback: minimal pastikan booking yang baru dinilai
                // tidak ditawarkan lagi, lalu coba sinkron biasa.
                _detailState.value = _detailState.value.copy(isReviewsLoading = false)
                loadReviews(doctorId)
                loadReviewableBookings(
                    doctorId,
                    knownReviewedIds = _detailState.value.reviews
                        .mapNotNull { it.bookingId }.toSet() + submittedBookingId
                )
                refreshDoctorsInBackground()
            }
        }
    }

    fun clearError() {
        _listState.value = _listState.value.copy(error = null)
        _detailState.value = _detailState.value.copy(error = null, reviewMessage = null)
    }
}
