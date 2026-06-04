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
    YESTERDAY,     // Kemarin
    LAST_WEEK,     // 1 Minggu
    LAST_MONTH,    // 1 Bulan
    LAST_3_MONTHS  // 3 Bulan
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
    val isLoading: Boolean = false,
    val error: String? = null,
    val isCancelled: Boolean = false,
    val isRescheduled: Boolean = false
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
    private val paymentMethodRepository: PaymentMethodRepository
) : ViewModel() {

    private val _listState = MutableStateFlow(BookingsUiState())
    val listState: StateFlow<BookingsUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(BookingDetailUiState())
    val detailState: StateFlow<BookingDetailUiState> = _detailState.asStateFlow()

    private val _createState = MutableStateFlow(CreateBookingUiState())
    val createState: StateFlow<CreateBookingUiState> = _createState.asStateFlow()

    private var lastHandledRefreshVersion = 0L

    init {
        viewModelScope.launch {
            bookingRefreshManager.refreshVersion.collect { version ->
                if (version > 0 && version > lastHandledRefreshVersion) {
                    lastHandledRefreshVersion = version
                    loadBookings()
                }
            }
        }
    }

    fun loadBookings() {
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isLoading = true, error = null)
            when (val r = bookingRepository.getBookings()) {
                is Result.Success -> {
                    val allBookings = r.data
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
     * Apply date filter and sort order to a list of bookings
     */
    private fun applyFiltersAndSort(bookings: List<Booking>): List<Booking> {
        var filtered = bookings

        // Apply date filter
        filtered = when (_listState.value.dateFilter) {
            BookingDateFilter.ALL -> filtered
            BookingDateFilter.TODAY -> {
                val today = LocalDate.now().format(DateTimeFormatter.ISO_LOCAL_DATE)
                filtered.filter { it.bookingDate == today }
            }
            BookingDateFilter.YESTERDAY -> {
                val yesterday = LocalDate.now().minusDays(1).format(DateTimeFormatter.ISO_LOCAL_DATE)
                filtered.filter { it.bookingDate == yesterday }
            }
            BookingDateFilter.LAST_WEEK -> {
                val oneWeekAgo = LocalDate.now().minusWeeks(1)
                filtered.filter { booking ->
                    try {
                        val bookingDate = LocalDate.parse(booking.bookingDate, DateTimeFormatter.ISO_LOCAL_DATE)
                        !bookingDate.isBefore(oneWeekAgo)
                    } catch (e: Exception) {
                        false
                    }
                }
            }
            BookingDateFilter.LAST_MONTH -> {
                val oneMonthAgo = LocalDate.now().minusMonths(1)
                filtered.filter { booking ->
                    try {
                        val bookingDate = LocalDate.parse(booking.bookingDate, DateTimeFormatter.ISO_LOCAL_DATE)
                        !bookingDate.isBefore(oneMonthAgo)
                    } catch (e: Exception) {
                        false
                    }
                }
            }
            BookingDateFilter.LAST_3_MONTHS -> {
                val threeMonthsAgo = LocalDate.now().minusMonths(3)
                filtered.filter { booking ->
                    try {
                        val bookingDate = LocalDate.parse(booking.bookingDate, DateTimeFormatter.ISO_LOCAL_DATE)
                        !bookingDate.isBefore(threeMonthsAgo)
                    } catch (e: Exception) {
                        false
                    }
                }
            }
        }

        // Apply sort
        filtered = when (_listState.value.sortOrder) {
            BookingSortOrder.NEWEST_FIRST -> {
                filtered.sortedWith(compareByDescending<Booking> { it.bookingDate ?: "" }
                    .thenByDescending { it.bookingTime ?: "" })
            }
            BookingSortOrder.OLDEST_FIRST -> {
                filtered.sortedWith(compareBy<Booking> { it.bookingDate ?: "" }
                    .thenBy { it.bookingTime ?: "" })
            }
        }

        Log.d("BookingViewModel", "Filtered bookings: ${filtered.size} (from ${bookings.size})")
        return filtered
    }

    fun loadBookingDetail(id: Int) {
        viewModelScope.launch {
            _detailState.value = BookingDetailUiState(isLoading = true)
            when (val r = bookingRepository.getBooking(id)) {
                is Result.Success -> _detailState.value = BookingDetailUiState(booking = r.data)
                is Result.Error   -> _detailState.value = BookingDetailUiState(error = r.message)
                else -> Unit
            }
        }
    }

    fun cancelBooking(id: Int, reason: String) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true)
            when (val r = bookingRepository.cancelBooking(id, reason)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    isLoading = false, isCancelled = true, booking = r.data
                )
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
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    isLoading = false, isRescheduled = true, booking = r.data
                )
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = r.message
                )
                else -> Unit
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
        val petId   = s.selectedPetId ?: return
        val serviceId = s.selectedServiceId ?: return
        val time    = s.selectedTime  ?: return
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

    /**
     * Refresh bookings list (e.g., after payment completion)
     */
    fun refreshBookings() {
        Log.d("BookingViewModel", "Refreshing bookings list")
        loadBookings()
    }
}
