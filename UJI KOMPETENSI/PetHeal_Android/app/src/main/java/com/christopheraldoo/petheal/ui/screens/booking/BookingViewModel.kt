package com.christopheraldoo.petheal.ui.screens.booking

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.repository.BookingRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.BookingRefreshManager
import com.christopheraldoo.petheal.data.repository.ServiceRepository
import com.christopheraldoo.petheal.data.repository.PaymentMethodRepository
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import javax.inject.Inject

// Filter and Sort enums
enum class BookingSortOrder {
    NEWEST_FIRST,  // Terbaru
    OLDEST_FIRST   // Terlama
}

enum class BookingDateFilter {
    ALL,           // Semua
    TODAY,         // Hari Ini
    TOMORROW,      // Besok
    THIS_WEEK,     // Minggu Ini (Senin–Minggu berjalan)
    THIS_MONTH     // Bulan Ini
}

data class BookingStartUiState(
    val pets: List<Pet> = emptyList(),
    val doctors: List<Doctor> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val searchQuery: String = "",
    val selectedPetId: Int? = null,
    val selectedDoctorId: Int? = null
) {
    val filteredDoctors: List<Doctor>
        get() = if (searchQuery.isBlank()) doctors
                else doctors.filter {
                    it.name?.contains(searchQuery, ignoreCase = true) == true ||
                    it.specialization?.contains(searchQuery, ignoreCase = true) == true
                }
    val canContinue: Boolean
        get() = selectedPetId != null && selectedDoctorId != null
}

data class BookingsUiState(
    val bookings: List<Booking> = emptyList(),
    val allBookings: List<Booking> = emptyList(), // Store all bookings for filtering
    val isLoading: Boolean = false,
    val error: String? = null,
    // Filter and sort state
    val sortOrder: BookingSortOrder = BookingSortOrder.NEWEST_FIRST,
    val dateFilter: BookingDateFilter = BookingDateFilter.ALL
)

data class BookingDetailUiState(
    val booking: Booking? = null,
    val medicalRecord: MedicalRecord? = null,
    val isMedicalRecordLoading: Boolean = false,
    val isLoading: Boolean = false,
    val error: String? = null,
    val isCancelled: Boolean = false,
    val isRescheduled: Boolean = false,
    // PHASE 7: real availability for the reschedule dialog (was hardcoded).
    val rescheduleSlots: List<TimeSlot> = emptyList(),
    val isSlotsLoading: Boolean = false
)

data class CreateBookingUiState(
    // Data
    val pets: List<Pet> = emptyList(),
    val services: List<Service> = emptyList(),
    val slots: List<TimeSlot> = emptyList(),
    val doctor: Doctor? = null,
    val paymentMethods: List<PaymentMethod> = emptyList(),
    // Selections
    val selectedPetId: Int? = null,
    val selectedServiceId: Int? = null,
    val selectedServiceName: String = "",
    val selectedDate: LocalDate = LocalDate.now(),
    val selectedTime: String? = null,
    val selectedPaymentMethod: PaymentMethod? = null,
    val selectedPaymentType: String = "full", // "full" or "dp"
    val totalAmount: Double = 150000.0, // Default price
    val dpAmount: Double = 75000.0, // 50% for DP
    val notes: String = "",
    // State
    val isLoading: Boolean = false,
    val isCreated: Boolean = false,
    val createdBookingId: Int? = null, // NEW: Store the created booking ID
    val error: String? = null
)

@HiltViewModel
class BookingViewModel @Inject constructor(
    private val bookingRepository: BookingRepository,
    private val petRepository: PetRepository,
    private val doctorRepository: DoctorRepository,
    private val bookingRefreshManager: BookingRefreshManager,
    private val serviceRepository: ServiceRepository,
    private val paymentMethodRepository: PaymentMethodRepository,
    private val medicalRecordRepository: MedicalRecordRepository
) : ViewModel() {

    private var hasLoadedBookings = false
    private var lastLoadedDetailBookingId: Int? = null

    private val _listState = MutableStateFlow(BookingsUiState())
    val listState: StateFlow<BookingsUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(BookingDetailUiState())
    val detailState: StateFlow<BookingDetailUiState> = _detailState.asStateFlow()

    private val _createState = MutableStateFlow(CreateBookingUiState())
    val createState: StateFlow<CreateBookingUiState> = _createState.asStateFlow()

    // ── BookingStart (alur transaksi mandiri: hewan + dokter ringkas) ──
    private val _startState = MutableStateFlow(BookingStartUiState())
    val startState: StateFlow<BookingStartUiState> = _startState.asStateFlow()

    private var lastHandledRefreshVersion = 0L

    init {
        viewModelScope.launch {
            bookingRefreshManager.refreshVersion.collect { version ->
                if (version > 0 && version > lastHandledRefreshVersion) {
                    lastHandledRefreshVersion = version
                    // WAJIB force: tanpa ini early-return di bawah membuat
                    // booking baru (mis. dibuat hari ini) tidak pernah
                    // diambil ulang → filter "Hari Ini" tampak kosong.
                    loadBookings(forceRefresh = true)
                }
            }
        }
        // Rating yang di-patch dari layar detail ikut terlihat di pilihan
        // dokter alur booking tanpa refresh manual.
        viewModelScope.launch {
            doctorRepository.ratingVersion.collect { version ->
                if (version == 0L) return@collect
                val snapshot = doctorRepository.snapshotDoctors()
                if (snapshot.isNotEmpty()) {
                    _startState.value = _startState.value.copy(doctors = snapshot)
                }
            }
        }
    }

    fun loadBookings(forceRefresh: Boolean = false) {
        if (!forceRefresh && hasLoadedBookings && _listState.value.allBookings.isNotEmpty()) {
            return
        }
        viewModelScope.launch {
            val hasCachedBookings = _listState.value.allBookings.isNotEmpty()
            if (!hasCachedBookings || forceRefresh) {
                _listState.value = _listState.value.copy(isLoading = true, error = null)
            } else {
                _listState.value = _listState.value.copy(error = null)
            }
            when (val r = bookingRepository.getBookings()) {
                is Result.Success -> {
                    val allBookings = r.data
                    hasLoadedBookings = true
                    // Apply current filters and sort
                    val filteredAndSorted = applyFiltersAndSort(allBookings)
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        allBookings = allBookings,
                        bookings = filteredAndSorted
                    )
                }
                is Result.Error   -> {
                    Log.e("BookingViewModel", "loadBookings error: ${r.message}")
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        error = r.message
                    )
                }
                else -> Unit
            }
        }
    }

    /**
     * Change sort order and re-apply filters
     */
    fun setSortOrder(sortOrder: BookingSortOrder) {
        Log.d("BookingViewModel", "Sort order changed: $sortOrder")
        _listState.value = _listState.value.copy(sortOrder = sortOrder)
        applyFiltersAndSort()
    }

    /**
     * Change date filter and re-apply filters
     */
    fun setDateFilter(dateFilter: BookingDateFilter) {
        Log.d("BookingViewModel", "Date filter changed: $dateFilter")
        _listState.value = _listState.value.copy(dateFilter = dateFilter)
        applyFiltersAndSort()
    }

    /**
     * Reset all filters to default
     */
    fun resetFilters() {
        Log.d("BookingViewModel", "Resetting all filters")
        _listState.value = _listState.value.copy(
            sortOrder = BookingSortOrder.NEWEST_FIRST,
            dateFilter = BookingDateFilter.ALL
        )
        applyFiltersAndSort()
    }

    /**
     * Apply current filters and sort to all bookings
     */
    private fun applyFiltersAndSort() {
        val currentState = _listState.value
        val filteredAndSorted = applyFiltersAndSort(currentState.allBookings)
        _listState.value = currentState.copy(bookings = filteredAndSorted)
    }

    /**
     * Apply date filter and sort order to a list of bookings.
     *
     * Desain: opsi filter berorientasi JADWAL (melihat ke depan), karena isi
     * My Booking mayoritas appointment mendatang — filter retrospektif
     * ("Kemarin/1 Minggu lalu") membuat booking besok tidak pernah ketemu.
     * Semua tanggal dinormalisasi ke 10 char pertama (aman untuk datetime).
     */
    private fun applyFiltersAndSort(bookings: List<Booking>): List<Booking> {
        var filtered = bookings
        val today = LocalDate.now()

        // Apply date filter
        filtered = when (_listState.value.dateFilter) {
            BookingDateFilter.ALL -> filtered
            BookingDateFilter.TODAY -> {
                val todayStr = today.format(DateTimeFormatter.ISO_LOCAL_DATE)
                filtered.filter { normalizeBookingDate(it.bookingDate) == todayStr }
            }
            BookingDateFilter.TOMORROW -> {
                val tomorrowStr = today.plusDays(1).format(DateTimeFormatter.ISO_LOCAL_DATE)
                filtered.filter { normalizeBookingDate(it.bookingDate) == tomorrowStr }
            }
            BookingDateFilter.THIS_WEEK -> {
                // Senin–Minggu minggu berjalan (mencakup yang lewat +
                // yang akan datang minggu ini).
                val startOfWeek = today.minusDays((today.dayOfWeek.value - 1).toLong())
                val endOfWeek = startOfWeek.plusDays(6)
                filtered.filter { booking ->
                    val bookingDate = parseBookingDate(booking.bookingDate)
                    bookingDate != null && !bookingDate.isBefore(startOfWeek) && !bookingDate.isAfter(endOfWeek)
                }
            }
            BookingDateFilter.THIS_MONTH -> {
                filtered.filter { booking ->
                    val bookingDate = parseBookingDate(booking.bookingDate)
                    bookingDate != null &&
                        bookingDate.year == today.year &&
                        bookingDate.month == today.month
                }
            }
        }

        // Apply sort (tanggal kosong selalu paling bawah di kedua mode).
        filtered = when (_listState.value.sortOrder) {
            BookingSortOrder.NEWEST_FIRST -> {
                filtered.sortedWith(compareBy<Booking> { it.bookingDate == null }
                    .thenByDescending { it.bookingDate ?: "" }
                    .thenByDescending { it.bookingTime ?: "" })
            }
            BookingSortOrder.OLDEST_FIRST -> {
                filtered.sortedWith(compareBy<Booking> { it.bookingDate == null }
                    .thenBy { it.bookingDate ?: "" }
                    .thenBy { it.bookingTime ?: "" })
            }
        }

        Log.d("BookingViewModel", "Filtered bookings: ${filtered.size} (from ${bookings.size})")
        return filtered
    }

    /** Ambil "YYYY-MM-DD" dari bookingDate mentah (aman untuk datetime). */
    private fun normalizeBookingDate(raw: String?): String? {
        if (raw.isNullOrBlank()) return null
        return try {
            raw.trim().take(10).takeIf { it.length == 10 }
        } catch (e: Exception) {
            null
        }
    }

    /** Parse bookingDate mentah menjadi LocalDate (toleran datetime). */
    private fun parseBookingDate(raw: String?): LocalDate? {
        val normalized = normalizeBookingDate(raw) ?: return null
        return try {
            LocalDate.parse(normalized, DateTimeFormatter.ISO_LOCAL_DATE)
        } catch (e: Exception) {
            null
        }
    }

    fun loadBookingDetail(id: Int, forceRefresh: Boolean = false) {
        if (!forceRefresh && lastLoadedDetailBookingId == id && _detailState.value.booking?.id == id) {
            return
        }
        viewModelScope.launch {
            val hasCachedBooking = _detailState.value.booking?.id == id
            if (!hasCachedBooking || forceRefresh) {
                _detailState.value = BookingDetailUiState(isLoading = true)
            } else {
                _detailState.value = _detailState.value.copy(error = null)
            }
            when (val r = bookingRepository.getBooking(id)) {
                is Result.Success -> {
                    lastLoadedDetailBookingId = id
                    _detailState.value = BookingDetailUiState(
                        booking = r.data,
                        isMedicalRecordLoading = true
                    )
                    when (val recordResult = medicalRecordRepository.getBookingMedicalRecord(id, forceRefresh = forceRefresh)) {
                        is Result.Success -> _detailState.value = _detailState.value.copy(
                            medicalRecord = recordResult.data,
                            isMedicalRecordLoading = false
                        )
                        is Result.Error -> _detailState.value = _detailState.value.copy(
                            isMedicalRecordLoading = false
                        )
                        else -> Unit
                    }
                }
                is Result.Error   -> _detailState.value = BookingDetailUiState(error = r.message)
                else -> Unit
            }
        }
    }

    fun cancelBooking(id: Int, reason: String) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true)
            when (val r = bookingRepository.cancelBooking(id, reason)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false, isCancelled = true, booking = r.data
                    )
                    // Daftar ikut basi (status berubah) → minta semua VM list reload.
                    bookingRefreshManager.requestRefresh()
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = r.message
                )
                else -> Unit
            }
        }
    }

    fun rescheduleBooking(id: Int, newDate: String, newTime: String) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true)
            when (val r = bookingRepository.rescheduleBooking(id, newDate, newTime)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false, isRescheduled = true, booking = r.data
                    )
                    // Tanggal berubah → posisi di daftar/filter ikut berubah.
                    bookingRefreshManager.requestRefresh()
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = r.message
                )
                else -> Unit
            }
        }
    }

    /** PHASE 7: reschedule dialog uses live slots, like the create flow. */
    fun loadRescheduleSlots(doctorId: Int, date: LocalDate) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isSlotsLoading = true)
            val dateStr = date.format(DateTimeFormatter.ISO_LOCAL_DATE)
            when (val r = doctorRepository.getDoctorSlots(doctorId, dateStr)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    rescheduleSlots = r.data, isSlotsLoading = false
                )
                else -> _detailState.value = _detailState.value.copy(isSlotsLoading = false)
            }
        }
    }

    fun loadCreateBookingData(doctorId: Int, petId: Int) {
        viewModelScope.launch {
            _createState.value = CreateBookingUiState(isLoading = true, selectedPetId = petId)
            // Load pets list
            val petsResult = petRepository.getPets()
            val pets = if (petsResult is Result.Success) petsResult.data else emptyList()
            // Load services
            val servicesResult = serviceRepository.getServices()
            val services = if (servicesResult is Result.Success) servicesResult.data else emptyList()
            // Load doctor
            val doctorResult = doctorRepository.getDoctor(doctorId)
            val doctor = if (doctorResult is Result.Success) doctorResult.data else null
            // Load payment methods
            val paymentResult = paymentMethodRepository.getPaymentMethods()
            val paymentMethods = if (paymentResult is Result.Success) paymentResult.data else emptyList()
            _createState.value = _createState.value.copy(
                isLoading = false,
                pets = pets,
                services = services,
                doctor = doctor,
                paymentMethods = paymentMethods,
                selectedServiceId = services.firstOrNull()?.id,
                selectedServiceName = services.firstOrNull()?.name.orEmpty()
            )
            services.firstOrNull()?.price?.let { setTotalAmount(it) }
            loadSlots(doctorId)
        }
    }

    fun loadSlots(doctorId: Int) {
        viewModelScope.launch {
            val date = _createState.value.selectedDate
            val dateStr = date.format(DateTimeFormatter.ISO_LOCAL_DATE)
            when (val r = doctorRepository.getDoctorSlots(doctorId, dateStr)) {
                is Result.Success -> _createState.value = _createState.value.copy(slots = r.data)
                else -> Unit
            }
        }
    }

    fun selectPet(petId: Int) {
        _createState.value = _createState.value.copy(selectedPetId = petId)
    }

    fun selectService(service: Service) {
        _createState.value = _createState.value.copy(
            selectedServiceId = service.id,
            selectedServiceName = service.name.orEmpty(),
            selectedPaymentMethod = null
        )
        service.price?.let { setTotalAmount(it) }
    }

    fun selectDate(date: LocalDate, doctorId: Int) {
        _createState.value = _createState.value.copy(selectedDate = date, selectedTime = null)
        loadSlots(doctorId)
    }

    fun selectTime(time: String) {
        _createState.value = _createState.value.copy(selectedTime = time)
    }

    fun selectPaymentMethod(paymentMethod: PaymentMethod) {
        _createState.value = _createState.value.copy(selectedPaymentMethod = paymentMethod)
    }

    fun selectPaymentType(paymentType: String) {
        _createState.value = _createState.value.copy(selectedPaymentType = paymentType)
    }

    fun setTotalAmount(amount: Double) {
        _createState.value = _createState.value.copy(
            totalAmount = amount,
            dpAmount = amount * 0.5 // 50% DP
        )
    }

    fun setNotes(notes: String) {
        _createState.value = _createState.value.copy(notes = notes)
    }

    fun createBooking(doctorId: Int) {
        val s = _createState.value
        // The submit button is disabled until all of these are set, so this is
        // a defensive path only — but never fail silently if it is ever hit.
        val petId   = s.selectedPetId ?: return setCreateError("Pilih hewan terlebih dahulu.")
        val serviceId = s.selectedServiceId ?: return setCreateError("Pilih layanan terlebih dahulu.")
        val time    = s.selectedTime  ?: return setCreateError("Pilih jam konsultasi terlebih dahulu.")
        val dateStr = s.selectedDate.format(DateTimeFormatter.ISO_LOCAL_DATE)
        viewModelScope.launch {
            _createState.value = s.copy(isLoading = true, error = null)
            val req = BookingRequest(
                petId       = petId,
                doctorId    = doctorId,
                serviceId   = serviceId,
                bookingDate = dateStr,
                bookingTime = time,
                notes       = s.notes.ifBlank { null },
                paymentMethodId = s.selectedPaymentMethod?.id,
                paymentType = s.selectedPaymentType,
                totalAmount = s.totalAmount,
                dpAmount = s.dpAmount
            )
            when (val r = bookingRepository.createBooking(req)) {
                is Result.Success -> {
                    val bookingId = r.data.id
                    _createState.value = _createState.value.copy(
                        isLoading = false,
                        isCreated = true,
                        createdBookingId = bookingId
                    )
                    // Booking baru wajib langsung masuk daftar (mencakup alur
                    // tanpa/tunda pembayaran yang tidak lewat PaymentResult).
                    bookingRefreshManager.requestRefresh()
                }
                is Result.Error -> _createState.value = _createState.value.copy(
                    isLoading = false, error = r.message
                )
                else -> Unit
            }
        }
    }

    fun clearCreateState() {
        _createState.value = CreateBookingUiState()
    }

    // ── BookingStart: entry transaksi (hewan + dokter, tanpa profil) ──────
    // Layar ini hanya memilih "untuk siapa" dan "dengan siapa". Layanan,
    // tanggal, jam, dan pembayaran tetap di CreateBookingScreen agar tidak
    // ada duplikasi logika pembuatan booking.
    fun loadBookingStartData() {
        viewModelScope.launch {
            val current = _startState.value
            _startState.value = current.copy(
                isLoading = current.pets.isEmpty() && current.doctors.isEmpty(),
                error = null
            )
            val pets = when (val r = petRepository.getPets()) {
                is Result.Success -> r.data
                else -> current.pets
            }
            var doctors = when (val r = doctorRepository.getDoctors(forceRefresh = false)) {
                is Result.Success -> r.data
                else -> current.doctors
            }
            if (doctors.isEmpty()) {
                when (val r = doctorRepository.getDoctors(forceRefresh = true)) {
                    is Result.Success -> doctors = r.data
                    is Result.Error -> _startState.value =
                        _startState.value.copy(error = r.message)
                    else -> Unit
                }
            } else {
                viewModelScope.launch {
                    when (val r = doctorRepository.getDoctors(forceRefresh = true)) {
                        is Result.Success -> if (r.data.isNotEmpty()) {
                            _startState.value = _startState.value.copy(doctors = r.data)
                        }
                        else -> Unit
                    }
                }
            }
            _startState.value = _startState.value.copy(
                isLoading = false, pets = pets, doctors = doctors
            )
            // Pasangan rating mencurigakan (avg > 0 tapi 0 ulasan) langsung
            // dibetulkan dari server; hasilnya mengalir lewat ratingVersion.
            viewModelScope.launch {
                runCatching { doctorRepository.revalidateSuspiciousRatings(doctors) }
            }
        }
    }

    fun onStartSearch(query: String) {
        _startState.value = _startState.value.copy(searchQuery = query)
    }

    fun selectStartPet(petId: Int) {
        _startState.value = _startState.value.copy(selectedPetId = petId)
    }

    fun selectStartDoctor(doctorId: Int) {
        _startState.value = _startState.value.copy(selectedDoctorId = doctorId)
    }

    fun clearStartError() {
        _startState.value = _startState.value.copy(error = null)
    }

    private fun setCreateError(message: String) {
        _createState.value = _createState.value.copy(error = message)
    }

    /**
     * Refresh bookings list (e.g., after payment completion)
     */
    fun refreshBookings() {
        Log.d("BookingViewModel", "Refreshing bookings list")
        loadBookings(forceRefresh = true)
    }
}
