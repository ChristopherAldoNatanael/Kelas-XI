package com.christopheraldoo.petheal.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.DashboardData
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.Vaccination
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.BookingRepository
import com.christopheraldoo.petheal.data.repository.DashboardRepository
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class HomeUiState(
    val userName: String = "",
    val userPhoto: String? = null,
    // PHASE 7: dynamic tenant branding (backend-owned, safe fallbacks).
    val clinicName: String? = null,
    val clinicLogo: String? = null,
    val clinicColor: String? = null,
    val upcomingBooking: Booking? = null,
    val isLoading: Boolean = true,
    val isBookingLoading: Boolean = false,
    val unreadNotificationCount: Int = 0,
    val totalPets: Int = 0,
    val activePets: Int = 0,
    val pendingBookings: Int = 0,
    val confirmedBookings: Int = 0,
    val paymentAttentionCount: Int = 0,
    val outstandingAmount: Double = 0.0,
    val totalVisits: Int = 0,
    val totalSpent: Double = 0.0,
    val followUpDueCount: Int = 0,
    val dueVaccinationCount: Int = 0,
    val overdueVaccinationCount: Int = 0,
    val recentVisits: List<MedicalRecord> = emptyList(),
    val dueSoonVaccinations: List<Vaccination> = emptyList(),
    val dashboardSummary: String = "",
    val loadError: String? = null,
    // PHASE 9: finished visits whose doctor has not been rated yet (max 3).
    // Shown once as a gentle reminder — never a nag.
    val pendingReviews: List<PendingReview> = emptyList()
)

/**
 * PHASE 9: one finished visit awaiting a doctor rating.
 */
data class PendingReview(
    val bookingId: Int,
    val doctorId: Int,
    val doctorName: String,
    val doctorPhoto: String?,
    val specialization: String?,
    val visitDate: String?
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val dashboardRepository: DashboardRepository,
    private val preferencesManager: PreferencesManager,
    private val authRepository: AuthRepository,
    private val notificationRepository: NotificationRepository,
    private val bookingRepository: BookingRepository,
    private val doctorRepository: DoctorRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()
    private var lastLoadAt = 0L

    init {
        // 1. Observe DataStore userName live — updates UI the moment it changes (INSTANT)
        viewModelScope.launch {
            preferencesManager.userName.collect { name ->
                if (!name.isNullOrBlank()) {
                    _uiState.value = _uiState.value.copy(
                        userName = name.trim(),
                        isLoading = false // Cache loaded, stop global loading
                    )
                } else if (_uiState.value.isLoading) {
                    // If still loading and name is null, keep loading
                } else {
                    // If loaded and name is null, stop loading
                    _uiState.value = _uiState.value.copy(isLoading = false)
                }
            }
        }
        // ... (photo observer) ...
        viewModelScope.launch {
            preferencesManager.userPhoto.collect { photo ->
                _uiState.value = _uiState.value.copy(userPhoto = photo)
            }
        }
        // ... (notification observer) ...
        viewModelScope.launch {
            notificationRepository.unreadCount.collect { count ->
                _uiState.value = _uiState.value.copy(unreadNotificationCount = count)
            }
        }
        // PHASE 7: tenant branding follows persisted clinic choice.
        viewModelScope.launch {
            preferencesManager.clinicName.collect { name ->
                _uiState.value = _uiState.value.copy(clinicName = name)
            }
        }
        viewModelScope.launch {
            preferencesManager.clinicLogo.collect { logo ->
                _uiState.value = _uiState.value.copy(clinicLogo = logo)
            }
        }
        viewModelScope.launch {
            preferencesManager.clinicColor.collect { color ->
                _uiState.value = _uiState.value.copy(clinicColor = color)
            }
        }

        loadBookingData()
    }

    fun loadBookingData(quiet: Boolean = false) {
        viewModelScope.launch {
            // Mode quiet (kembali dari layar lain): jangan tampilkan skeleton
            // bila data sudah ada — update diam-diam di background.
            val silent = quiet && _uiState.value.upcomingBooking != null &&
                !_uiState.value.isLoading
            if (!silent) {
                _uiState.value = _uiState.value.copy(isBookingLoading = true, loadError = null)
            }

            // Fetch fresh profile (in background to update cache) + upcoming bookings
            val profileDeferred = async {
                try {
                    val result = authRepository.getProfile()
                    if (result is Result.Success) {
                        val user = result.data
                        preferencesManager.saveUserInfo(
                            userId = user.id ?: 0,
                            email = user.email ?: "",
                            name = user.name ?: "",
                            photo = user.photo
                        )
                    }
                } catch (e: Exception) { /* silent */ }
            }

            val dashboardDeferred = async {
                // PHASE 7: via repository (VM no longer touches ApiService).
                when (val result = dashboardRepository.getDashboard()) {
                    is Result.Success -> Result.Success(result.data)
                    is Result.Error -> result
                    else -> Result.Error("Gagal memuat ringkasan")
                }
            }

            profileDeferred.await()
            when (val dashboardResult = dashboardDeferred.await()) {
                is Result.Success -> {
                    val dashboard = dashboardResult.data
                    lastLoadAt = System.currentTimeMillis()
                    _uiState.value = _uiState.value.copy(
                    upcomingBooking = dashboard.bookings.upcoming.firstOrNull(),
                    totalPets = dashboard.pets.total,
                    activePets = dashboard.pets.active,
                    pendingBookings = dashboard.bookings.pending,
                    confirmedBookings = dashboard.bookings.confirmed,
                    paymentAttentionCount = dashboard.payments.attentionCount,
                    outstandingAmount = dashboard.payments.outstandingAmount,
                    totalVisits = dashboard.medical.totalVisits,
                    totalSpent = dashboard.medical.totalSpent,
                    followUpDueCount = dashboard.medical.followUpDue,
                    dueVaccinationCount = dashboard.vaccinationAlerts.dueSoon.size,
                    overdueVaccinationCount = dashboard.vaccinationAlerts.overdue.size,
                    recentVisits = dashboard.medical.recentVisits,
                    dueSoonVaccinations = dashboard.vaccinationAlerts.dueSoon,
                    dashboardSummary = dashboard.summary,
                    isBookingLoading = false,
                    loadError = null
                )
            }
            else -> {
                val message = (dashboardResult as? Result.Error)?.message
                    ?: "Gagal memuat ringkasan"
                _uiState.value = _uiState.value.copy(
                    isBookingLoading = false,
                    // Quiet gagal → pertahankan data lama, jangan tampilkan banner.
                    loadError = if (silent) _uiState.value.loadError else message
                )
            }
        }
    }
    }

    // Keep loadHomeData for manual refresh (pull-to-refresh)
    fun refresh() {
        loadBookingData()
    }

    /**
     * Revalidasi ringan saat kembali ke Beranda (mis. setelah kirim review
     * atau buat booking di layar lain): hanya reload bila data lebih tua
     * dari [maxAgeMs], tanpa skeleton, tanpa error banner bila gagal.
     */
    fun refreshIfStale(maxAgeMs: Long = 60_000) {
        if (System.currentTimeMillis() - lastLoadAt < maxAgeMs) return
        loadBookingData(quiet = true)
        loadPendingReviews()
    }

    /**
     * PHASE 9: find finished visits whose doctor has not been rated yet.
     *
     * Runs quietly once per Home session: completed bookings minus already
     * reviewed ones (via each doctor's review list) minus already nudged
     * ones (persisted). Any failure → no reminder, never an error state.
     */
    fun loadPendingReviews() {
        viewModelScope.launch {
            try {
                val bookingsResult = bookingRepository.getBookings()
                if (bookingsResult !is Result.Success) return@launch
                val completed = bookingsResult.data.filter {
                    it.status == "completed" && it.id != null && it.doctorId != null
                }
                if (completed.isEmpty()) return@launch

                val prompted = preferencesManager.ratingPromptedIds.first()
                val fresh = completed.filter { it.id.toString() !in prompted }
                if (fresh.isEmpty()) return@launch

                // Bound network fan-out: at most 5 doctors' review lists.
                val doctorIds = fresh.mapNotNull { it.doctorId }.distinct().take(5)
                val reviewedBookingIds = mutableSetOf<Int>()
                for (doctorId in doctorIds) {
                    when (val r = doctorRepository.getDoctorReviews(doctorId)) {
                        is Result.Success ->
                            r.data.reviews?.mapNotNullTo(reviewedBookingIds) { it.bookingId }
                        else -> Unit
                    }
                }

                val pending = fresh
                    .filter { it.id != null && it.id !in reviewedBookingIds }
                    .sortedByDescending { it.bookingDate.orEmpty() }
                    .take(3)
                    .mapNotNull { booking ->
                        val doctorId = booking.doctorId ?: return@mapNotNull null
                        val bookingId = booking.id ?: return@mapNotNull null
                        PendingReview(
                            bookingId = bookingId,
                            doctorId = doctorId,
                            doctorName = booking.doctor?.name ?: "Dokter",
                            doctorPhoto = booking.doctor?.photo,
                            specialization = booking.doctor?.specialization,
                            visitDate = booking.bookingDate
                        )
                    }
                if (pending.isNotEmpty()) {
                    _uiState.value = _uiState.value.copy(pendingReviews = pending)
                }
            } catch (_: Exception) {
                // Silent — the reminder is a courtesy, never a blocker.
            }
        }
    }

    /** Dismiss the reminder (marks these visits as nudged — shown once). */
    fun markReviewsPrompted() {
        val ids = _uiState.value.pendingReviews.map { it.bookingId }
        if (ids.isEmpty()) return
        viewModelScope.launch {
            runCatching { preferencesManager.markRatingPrompted(ids) }
            _uiState.value = _uiState.value.copy(pendingReviews = emptyList())
        }
    }
}
