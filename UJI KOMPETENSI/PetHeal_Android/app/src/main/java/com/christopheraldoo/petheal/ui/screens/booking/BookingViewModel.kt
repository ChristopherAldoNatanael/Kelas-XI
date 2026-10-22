package com.christopheraldoo.petheal.ui.screens.booking

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.repository.BookingRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.BookingRefreshManager
import com.christopheraldoo.petheal.data.repository.ClinicRepository
import com.christopheraldoo.petheal.data.repository.ServiceRepository
import com.christopheraldoo.petheal.data.repository.PaymentMethodRepository
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.util.ClinicContact
import com.christopheraldoo.petheal.util.buildClinicHelpMessage
import com.christopheraldoo.petheal.util.firstDoctorWorkingDate
import com.christopheraldoo.petheal.util.isSlotExpired
import com.christopheraldoo.petheal.util.toLeaveDates
import com.christopheraldoo.petheal.util.withExpiredSlotsUnavailable
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
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
    val dateFilter: BookingDateFilter = BookingDateFilter.ALL,
    // Identitas klinik untuk bukti pembayaran (bukan data baru dari API).
    val clinicName: String? = null,
    val clinicAddress: String? = null,
    val clinicColor: String? = null
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
    val isSlotsLoading: Boolean = false,
    val rescheduleSlotsError: String? = null,
    // PHASE 4: jadwal efektif tanggal yang sedang dilihat (cuti/jam khusus).
    val rescheduleSlotMeta: SlotMeta? = null,
    val rescheduleConflict: Boolean = false,
    val rescheduleUnavailable: Boolean = false
)

/** Kontak klinik + pesan siap-kirim untuk sheet Bantuan di alur booking. */
data class SupportContactUiState(
    val contact: ClinicContact? = null,
    val message: String = "",
    val isLoading: Boolean = false,
    val error: String? = null
)

data class CreateBookingUiState(
    // Data
    val pets: List<Pet> = emptyList(),
    val services: List<Service> = emptyList(),
    val slots: List<TimeSlot> = emptyList(),
    // PHASE 4: metadata cuti/jam khusus untuk tanggal terpilih.
    val slotMeta: SlotMeta? = null,
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
    val isSlotsLoading: Boolean = false,
    val slotsError: String? = null,
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
    private val medicalRecordRepository: MedicalRecordRepository,
    private val clinicRepository: ClinicRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private var hasLoadedBookings = false
    private var lastLoadedDetailBookingId: Int? = null
    private var rescheduleSlotsRequestDate: LocalDate? = null

    private val _listState = MutableStateFlow(BookingsUiState())
    val listState: StateFlow<BookingsUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(BookingDetailUiState())
    val detailState: StateFlow<BookingDetailUiState> = _detailState.asStateFlow()

    private val _createState = MutableStateFlow(CreateBookingUiState())
    val createState: StateFlow<CreateBookingUiState> = _createState.asStateFlow()

    // ── BookingStart (alur transaksi mandiri: hewan + dokter ringkas) ──
    private val _startState = MutableStateFlow(BookingStartUiState())
    val startState: StateFlow<BookingStartUiState> = _startState.asStateFlow()

    private val _supportState = MutableStateFlow(SupportContactUiState())
    val supportState: StateFlow<SupportContactUiState> = _supportState.asStateFlow()
    private var cachedSupportContact: ClinicContact? = null

    private var lastHandledRefreshVersion = 0L

    init {
        viewModelScope.launch {
            preferencesManager.clinicName.collect { name ->
                _listState.value = _listState.value.copy(clinicName = name)
            }
        }
        viewModelScope.launch {
            preferencesManager.clinicAddress.collect { address ->
                _listState.value = _listState.value.copy(clinicAddress = address)
            }
        }
        viewModelScope.launch {
            preferencesManager.clinicColor.collect { color ->
                _listState.value = _listState.value.copy(clinicColor = color)
            }
        }
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
            _detailState.value = _detailState.value.copy(
                isLoading = true,
                error = null,
                rescheduleConflict = false
            )
            when (val r = bookingRepository.rescheduleBooking(id, newDate, newTime)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false,
                        isRescheduled = true,
                        booking = r.data,
                        rescheduleConflict = false
                    )
                    // Tanggal berubah → posisi di daftar/filter ikut berubah.
                    bookingRefreshManager.requestRefresh()
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false,
                    error = r.message,
                    rescheduleConflict = r.code == "slot_conflict",
                    rescheduleUnavailable = r.code == "slot_unavailable"
                )
                else -> Unit
            }
        }
    }

    /** PHASE 7: reschedule dialog uses live slots, like the create flow. */
    fun loadRescheduleSlots(doctorId: Int, date: LocalDate) {
        rescheduleSlotsRequestDate = date
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(
                isSlotsLoading = true,
                rescheduleSlots = emptyList(),
                rescheduleSlotMeta = null,
                rescheduleSlotsError = null,
                rescheduleConflict = false,
                rescheduleUnavailable = false
            )
            val dateStr = date.format(DateTimeFormatter.ISO_LOCAL_DATE)
            when (val r = doctorRepository.getDoctorSlots(doctorId, dateStr)) {
                is Result.Success -> {
                    if (rescheduleSlotsRequestDate != date) return@launch
                    // Jam yang sudah lewat hari ini tidak boleh dipilih ulang.
                    _detailState.value = _detailState.value.copy(
                        rescheduleSlots = r.data.slots.withExpiredSlotsUnavailable(date),
                        rescheduleSlotMeta = r.data.meta,
                        isSlotsLoading = false,
                        rescheduleSlotsError = null
                    )
                }
                is Result.Error -> {
                    if (rescheduleSlotsRequestDate != date) return@launch
                    _detailState.value = _detailState.value.copy(
                        isSlotsLoading = false,
                        rescheduleSlotsError = r.message.ifBlank { "Gagal memuat jadwal dokter." }
                    )
                }
                else -> Unit
            }
        }
    }

    fun clearDetailError() {
        _detailState.value = _detailState.value.copy(
            error = null,
            rescheduleConflict = false,
            rescheduleUnavailable = false
        )
    }

    fun loadCreateBookingData(doctorId: Int, petId: Int) {
        viewModelScope.launch {
            _createState.value = CreateBookingUiState(
                isLoading = true,
                selectedPetId = petId,
                isSlotsLoading = true
            )
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

            // Never open the flow on a day the doctor does not practice. The
            // API returns an empty list for off-days, which would otherwise
            // look like a loading/empty bug to the user.
            // PHASE 4: lewati juga tanggal cuti yang sudah diketahui, bukan
            // hanya hari yang tidak ada di available_days.
            val firstWorkingDate = firstDoctorWorkingDate(
                availableDays = doctor?.availableDays,
                from = LocalDate.now(),
                leaveDates = doctor?.scheduleExceptions.toLeaveDates().orEmpty().keys
            ) ?: LocalDate.now()

            _createState.value = _createState.value.copy(
                isLoading = false,
                pets = pets,
                services = services,
                doctor = doctor,
                paymentMethods = paymentMethods,
                selectedDate = firstWorkingDate,
                selectedServiceId = services.firstOrNull()?.id,
                selectedServiceName = services.firstOrNull()?.name.orEmpty()
            )
            services.firstOrNull()?.price?.let { setTotalAmount(it) }
            loadSlots(doctorId, firstWorkingDate)
        }
    }

    /**
     * Sumber kontak Bantuan: `clinics.phone` / `clinics.email` via
     * `GET public/clinics/{slug}`. Data diambil saat sheet dibuka (bukan saat
     * layar dibuka) supaya perubahan nomor di web admin langsung terpakai
     * tanpa perlu update APK. Pesan WA selalu dibangun ulang dari pilihan
     * terakhir pengguna.
     */
    fun loadSupportContact(forceRefresh: Boolean = false) {
        if (_supportState.value.isLoading) return
        viewModelScope.launch {
            val create = _createState.value
            val userName = preferencesManager.userName.first()
            val storedClinicName = preferencesManager.clinicName.first()
            val message = buildClinicHelpMessage(
                clinicName = storedClinicName,
                userName = userName,
                doctorName = create.doctor?.name,
                serviceName = create.selectedServiceName,
                date = create.selectedDate,
                time = create.selectedTime
            )

            val cached = cachedSupportContact
            if (!forceRefresh && cached != null) {
                _supportState.value = SupportContactUiState(
                    contact = cached,
                    message = message
                )
                return@launch
            }

            _supportState.value = SupportContactUiState(
                contact = cached,
                message = message,
                isLoading = true
            )

            when (val r = clinicRepository.getSupportContact(forceRefresh)) {
                is Result.Success -> {
                    val contact = r.data.copy(
                        clinicName = r.data.clinicName ?: storedClinicName
                    )
                    cachedSupportContact = contact
                    _supportState.value = SupportContactUiState(
                        contact = contact,
                        message = message
                    )
                }
                is Result.Error -> {
                    _supportState.value = SupportContactUiState(
                        contact = cached,
                        message = message,
                        error = r.message.ifBlank { "Kontak klinik belum bisa dimuat." }
                    )
                }
                else -> Unit
            }
        }
    }

    fun loadSlots(doctorId: Int, date: LocalDate = _createState.value.selectedDate) {
        viewModelScope.launch {
            _createState.value = _createState.value.copy(
                isSlotsLoading = true,
                slotsError = null
            )
            val dateStr = date.format(DateTimeFormatter.ISO_LOCAL_DATE)
            when (val r = doctorRepository.getDoctorSlots(doctorId, dateStr)) {
                is Result.Success -> {
                    // A slow request from a previous date must never overwrite
                    // the date the user is currently looking at.
                    if (_createState.value.selectedDate != date) return@launch
                    // Backend tidak mengirim status "sudah lewat", jadi jam
                    // hari ini yang sudah terlewati ditandai di sini agar tidak
                    // bisa dipilih (mis. sekarang 18.00 → slot < 18.00 mati).
                    val slots = r.data.slots.withExpiredSlotsUnavailable(date)
                    val stillAvailable = _createState.value.selectedTime?.let { selected ->
                        slots.any { it.time == selected && it.available }
                    } == true
                    _createState.value = _createState.value.copy(
                        slots = slots,
                        slotMeta = r.data.meta,
                        isSlotsLoading = false,
                        slotsError = null,
                        selectedTime = _createState.value.selectedTime.takeIf { stillAvailable }
                    )
                }
                is Result.Error -> {
                    if (_createState.value.selectedDate != date) return@launch
                    _createState.value = _createState.value.copy(
                        slots = emptyList(),
                        slotMeta = null,
                        isSlotsLoading = false,
                        slotsError = r.message.ifBlank { "Gagal memuat jadwal dokter." }
                    )
                }
                else -> Unit
            }
        }
    }

    fun retrySlots(doctorId: Int) {
        loadSlots(doctorId, _createState.value.selectedDate)
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
        _createState.value = _createState.value.copy(
            selectedDate = date,
            selectedTime = null,
            slots = emptyList(),
            slotMeta = null,
            isSlotsLoading = true,
            slotsError = null,
            error = null
        )
        loadSlots(doctorId, date)
    }

    fun selectTime(time: String) {
        _createState.value = _createState.value.copy(selectedTime = time, error = null)
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
        // Slot bisa saja menjadi lewat antara saat dipilih dan saat submit
        // (layar lama terbuka, jam berjalan). Jangan kirim booking yang pasti
        // ditolak klinik.
        if (isSlotExpired(s.selectedDate, time)) {
            _createState.value = _createState.value.copy(
                selectedTime = null,
                error = "Jam $time sudah lewat. Pilih jam lain atau tanggal berikutnya."
            )
            loadSlots(doctorId, s.selectedDate)
            return
        }
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
                is Result.Error -> {
                    val slotConflict = r.code == "slot_conflict"
                    // PHASE 4: cuti/jam khusus berubah setelah layar dibuka →
                    // pilihan jam dibuang lalu slot dimuat ulang. Pilihan hewan,
                    // layanan, dan dokter tidak ikut hilang.
                    val slotUnavailable = r.code == "slot_unavailable"
                    val mustRepick = slotConflict || slotUnavailable
                    _createState.value = _createState.value.copy(
                        isLoading = false,
                        selectedTime = if (mustRepick) null else _createState.value.selectedTime,
                        error = if (slotConflict) {
                            "Jadwal yang Anda pilih baru saja diambil. Silakan pilih jam lain."
                        } else {
                            r.message
                        }
                    )
                    // Refresh availability without touching the other choices.
                    if (mustRepick) loadSlots(doctorId, s.selectedDate)
                }
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
