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

/** Satu klinik yang benar-benar punya dokter aktif, siap dipilih user. */
data class BookingClinicOption(
    val id: Int,
    val name: String,
    val slug: String? = null,
    val address: String? = null,
    val doctorCount: Int = 0,
    /** Klinik favorit (klinik aktif di preferensi) — ditandai "Terakhir dipakai". */
    val isPreferred: Boolean = false
) {
    val key: String get() = slug ?: id.toString()
}

data class BookingStartUiState(
    val pets: List<Pet> = emptyList(),
    /** Katalog dokter lintas klinik: sumber daftar klinik & pilihan dokter. */
    val doctors: List<Doctor> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val searchQuery: String = "",
    val selectedPetId: Int? = null,
    val selectedDoctorId: Int? = null,
    /**
     * MULTI-CLINIC (2026-11-09): klinik tujuan booking yang DIPILIH user di
     * langkah 2. Booking selalu milik satu klinik; sebelum revisi ini klinik
     * ditentukan diam-diam dari preferensi sehingga user melihat dokter
     * semua klinik tanpa tahu transaksinya masuk ke klinik mana.
     */
    val selectedClinicId: Int? = null,
    /** Nama klinik favorit tersimpan — hanya untuk label "Terakhir dipakai". */
    val preferredClinicName: String? = null,
    /** Slug klinik favorit tersimpan; dipakai bila layar asal hanya punya slug. */
    val preferredClinicSlug: String? = null
) {
    val clinics: List<BookingClinicOption>
        get() = doctors
            .mapNotNull { doctor -> doctor.clinic?.takeIf { it.id != null } }
            .groupBy { it.id }
            .map { (id, refs) ->
                val first = refs.first()
                BookingClinicOption(
                    id = id!!,
                    name = first.name.orEmpty().ifBlank { "Klinik tanpa nama" },
                    slug = first.slug,
                    address = first.address,
                    doctorCount = refs.size,
                    isPreferred = !preferredClinicName.isNullOrBlank() &&
                        first.name.equals(preferredClinicName, ignoreCase = true)
                )
            }
            .sortedWith(compareByDescending<BookingClinicOption> { it.isPreferred }.thenBy { it.name })

    /** Klinik yang sedang dipilih user (null = belum memilih). */
    val selectedClinic: BookingClinicOption?
        get() = selectedClinicId?.let { id -> clinics.firstOrNull { it.id == id } }

    /** Dokter yang praktik di klinik terpilih. Sengaja TIDAK dibiarkan kosong. */
    val doctorsInSelectedClinic: List<Doctor>
        get() = selectedClinicId?.let { id -> doctors.filter { it.clinic?.id == id } }.orEmpty()

    val filteredDoctors: List<Doctor>
        get() {
            val pool = doctorsInSelectedClinic
            if (searchQuery.isBlank()) return pool
            return pool.filter {
                it.name?.contains(searchQuery, ignoreCase = true) == true ||
                it.specialization?.contains(searchQuery, ignoreCase = true) == true
            }
        }

    /** Total dokter aktif di seluruh klinik — ditampilkan sebelum memilih. */
    val totalDoctors: Int get() = doctors.size

    val canContinue: Boolean
        get() = selectedPetId != null && selectedDoctorId != null && selectedClinicId != null
}

data class BookingsUiState(
    val bookings: List<Booking> = emptyList(),
    val allBookings: List<Booking> = emptyList(), // Store all bookings for filtering
    val isLoading: Boolean = false,
    val error: String? = null,
    // Filter and sort state
    val sortOrder: BookingSortOrder = BookingSortOrder.NEWEST_FIRST,
    val dateFilter: BookingDateFilter = BookingDateFilter.ALL,
    // MULTI-CLINIC (2026-11-11): identitas klinik untuk struk DIHAPUS dari
    // state ini. Struk membaca `booking.clinic` (klinik milik transaksi),
    // bukan klinik sesi — pasien bisa punya booking di beberapa klinik.

    // Feedback check-in ditulis ke state DAFTAR (bukan detail): tombolnya ada
    // di kartu daftar, jadi pesan sukses/gagal harus terlihat di layar itu.
    val checkInLoadingId: Int? = null,
    val checkInMessage: String? = null,
    val checkInError: String? = null
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
    // 0 = BELUM ada harga tervalidasi dari backend. Dulu default 150000/75000
    // yang tampil sebagai tagihan selama loading/gagal muat layanan — angka
    // tebakan. CTA & ringkasan harga WAJIB mati selama totalAmount == 0.
    val totalAmount: Double = 0.0,
    val dpAmount: Double = 0.0,
    val notes: String = "",
    // State
    val isLoading: Boolean = false,
    val isCreated: Boolean = false,
    val createdBookingId: Int? = null, // NEW: Store the created booking ID
    val isSlotsLoading: Boolean = false,
    val slotsError: String? = null,
    /**
     * MULTI-CLINIC (2026-11-09): klinik yang akan menerima booking — klinik
     * DOKTER, bukan klinik favorit di preferensi. Ditampilkan sebagai
     * ringkasan supaya user bisa memeriksa tujuannya sebelum membayar.
     */
    val clinicName: String? = null,
    val clinicAddress: String? = null,
    val clinicSlug: String? = null,
    /** Gagal memuat dokter (mis. server error) — layar wajib menawarkan retry. */
    val doctorError: String? = null,
    // Error per sumber data, supaya "gagal muat" tidak tampil sebagai
    // "memuat..." selamanya atau daftar kosong yang menenangkan.
    val servicesError: String? = null,
    val paymentMethodsError: String? = null,
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

    /**
     * Check-in mandiri untuk booking hari ini.
     *
     * Label nomor antrian ditaruh di pesan yang terlihat pasien; kalau server
     * menolak (mis. jadwal ternyata bukan hari ini), pesannya diteruskan apa
     * adanya supaya pasien tahu alasannya.
     */
    fun checkInBooking(id: Int) {
        // Cegah double-submit: dua tap cepat tidak boleh mengirim dua request.
        if (_listState.value.checkInLoadingId != null) return

        _listState.value = _listState.value.copy(
            checkInLoadingId = id,
            checkInError = null,
            checkInMessage = null
        )

        viewModelScope.launch {
            when (val r = bookingRepository.checkInBooking(id)) {
                is Result.Success -> {
                    val booking = r.data
                    val label = booking.queueLabel
                        ?: booking.queueNumber?.let { String.format("A-%02d", it) }
                    val position = booking.queuePosition
                    val message = buildString {
                        append(
                            label?.let { "Check-in berhasil. Nomor antrian Anda $it." }
                                ?: "Check-in berhasil."
                        )
                        if (position != null && position > 0) {
                            append(" $position antrian lagi.")
                        } else if (position == 0) {
                            append(" Giliran Anda.")
                        }
                    }

                    _listState.value = _listState.value.copy(
                        checkInLoadingId = null,
                        checkInMessage = message,
                        // Kartu langsung memakai data baru (nomor + posisi)
                        // tanpa menunggu reload halaman.
                        bookings = _listState.value.bookings.map {
                            if (it.id == booking.id) booking else it
                        },
                        allBookings = _listState.value.allBookings.map {
                            if (it.id == booking.id) booking else it
                        }
                    )
                    bookingRefreshManager.requestRefresh()
                }

                is Result.Error -> _listState.value = _listState.value.copy(
                    checkInLoadingId = null,
                    checkInError = r.message
                )

                else -> _listState.value = _listState.value.copy(checkInLoadingId = null)
            }
        }
    }

    /** Tutup pesan check-in (dipanggil tombol X pada snackbar). */
    fun clearCheckInNotice() {
        _listState.value = _listState.value.copy(
            checkInMessage = null,
            checkInError = null
        )
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
            // Dokter dimuat DULU: kliniknya menentukan katalog layanan.
            // MULTI-CLINIC: scope ke klinik yang dipilih di langkah 2 (bila
            // ada), bukan klinik tersimpan — dokter klinik lain harus tetap
            // bisa dibuka walau klinik favorit menunjuk klinik berbeda.
            val chosenClinicId = _startState.value.selectedClinicId
            val doctorResult = doctorRepository.getDoctor(doctorId, clinicId = chosenClinicId)
            val doctor = if (doctorResult is Result.Success) doctorResult.data else null
            // Kegagalan dokter TIDAK boleh tampil sebagai layar kosong tanpa
            // penjelasan: simpan pesannya agar UI menawarkan "Coba lagi".
            val doctorError = (doctorResult as? Result.Error)?.message
                ?.ifBlank { "Data dokter belum bisa dimuat." }
            val bookingClinicId = doctor?.clinic?.id ?: chosenClinicId
            // Load services — kegagalan diumumkan, tidak ditelan jadi list
            // kosong yang tampak seperti "memuat..." selamanya.
            // MULTI-CLINIC (2026-11-09): WAJIB dibatasi ke klinik dokter.
            // Sebelumnya `getServices()` tanpa scope: pasien menerima layanan
            // semua klinik lalu bisa memilih layanan klinik lain → backend
            // menolak "Dokter dan layanan harus berasal dari klinik yang sama".
            val servicesResult = serviceRepository.getServices(clinicId = bookingClinicId)
            val services = if (servicesResult is Result.Success) {
                servicesResult.data.filter { svc ->
                    bookingClinicId == null || svc.clinicId == null || svc.clinicId == bookingClinicId
                }
            } else {
                emptyList()
            }
            val servicesError =
                (servicesResult as? Result.Error)?.message?.ifBlank { "Gagal memuat layanan." }
            // Load payment methods
            val paymentResult = paymentMethodRepository.getPaymentMethods()
            val paymentMethods = if (paymentResult is Result.Success) paymentResult.data else emptyList()
            val paymentMethodsError =
                (paymentResult as? Result.Error)?.message?.ifBlank { "Gagal memuat metode pembayaran." }

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
                servicesError = servicesError,
                paymentMethodsError = paymentMethodsError,
                clinicName = doctor?.clinic?.name,
                clinicAddress = doctor?.clinic?.address,
                clinicSlug = doctor?.clinic?.slug,
                doctorError = doctorError,
                selectedDate = firstWorkingDate,
                selectedServiceId = services.firstOrNull()?.id,
                selectedServiceName = services.firstOrNull()?.name.orEmpty()
            )
            // Harga hanya berasal dari layanan nyata. Layanan tanpa harga
            // (data master belum lengkap) TIDAK usah dapat nominal warisan —
            // total tetap 0 dan CTA tetap terkunci.
            setTotalAmount(services.firstOrNull()?.price ?: 0.0)
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
            // MULTI-CLINIC (2026-11-11): bantuan menuju klinik DOKTER yang
            // dibooking. Fallback ke klinik sesi DIHAPUS — pesan WA/email
            // bantuan akan salah kirim ke klinik lain, persis kesalahan yang
            // ingin dicegah. Tanpa klinik dokter, pesan memakai nama klinik
            // dari kontak yang berhasil dimuat (atau netral).
            val storedClinicName = create.clinicName
            if (create.clinicSlug.isNullOrBlank() && storedClinicName.isNullOrBlank()) {
                _supportState.value = SupportContactUiState(
                    message = "",
                    error = "Klinik tujuan belum diketahui. Muat ulang pilihan dokter lalu coba lagi."
                )
                return@launch
            }
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

            // Klinik transaksi (dokter), bukan klinik favorit — kalau tidak,
            // pesan bantuan terkirim ke klinik yang salah.
            when (
                val r = clinicRepository.getSupportContactFor(
                    create.clinicSlug,
                    forceRefresh,
                    fallbackToCurrent = false
                )
            ) {
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
        // Layanan tanpa harga menghapus nominal lama (0 = belum tervalidasi),
        // bukan mewarisi harga layanan sebelumnya.
        setTotalAmount(service.price ?: 0.0)
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
        // Jangan pernah mengirim booking dengan nominal yang belum tervalidasi
        // dari backend (0 = harga layanan belum diketahui).
        if (s.totalAmount <= 0.0) {
            return setCreateError("Harga layanan belum tersedia. Pilih layanan lain atau hubungi klinik.")
        }
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
                dpAmount = s.dpAmount,
                // Klinik dinyatakan eksplisit: header X-Clinic-Slug bisa
                // menunjuk klinik favorit yang berbeda dari klinik dokter.
                clinicSlug = s.clinicSlug
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
            // Nama klinik favorit tersimpan — HANYA untuk label "Terakhir
            // dipakai". Klinik tujuan booking dipilih user di langkah 2,
            // tidak pernah diturunkan diam-diam dari preferensi ini.
            val clinicName = runCatching {
                preferencesManager.clinicName.first()
            }.getOrNull() ?: current.preferredClinicName
            val clinicSlug = runCatching {
                preferencesManager.clinicSlug.first()
            }.getOrNull() ?: current.preferredClinicSlug
            _startState.value = _startState.value.copy(
                preferredClinicName = clinicName,
                preferredClinicSlug = clinicSlug
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
            // Klinik terpilih bisa lenyap dari katalog (klinik/nonaktif /
            // dokter pindah). Jangan biarkan pilihan hantu lolos ke langkah 3.
            val stillHasClinic = _startState.value.selectedClinicId?.let { id ->
                doctors.any { it.clinic?.id == id }
            } == true
            val stillHasDoctor = _startState.value.selectedDoctorId?.let { id ->
                val clinic = _startState.value.selectedClinicId
                doctors.any { it.id == id && it.clinic?.id == clinic }
            } == true
            _startState.value = _startState.value.copy(
                isLoading = false,
                pets = pets,
                doctors = doctors,
                selectedClinicId = _startState.value.selectedClinicId.takeIf { stillHasClinic },
                selectedDoctorId = _startState.value.selectedDoctorId.takeIf { stillHasDoctor }
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

    /**
     * Pastikan klinik tujuan terpilih = klinik yang dipakai transaksi
     * (header `X-Clinic-Slug`, kontak bantuan, branding). Dipanggil di awal
     * langkah 3 karena user sudah menyatakan pilihannya di langkah 2.
     * Amannya: bila tidak ada slug, tidak ada yang ditulis.
     */
    fun applySelectedClinicAsActive() {
        val clinic = _startState.value.selectedClinic ?: return
        val slug = clinic.slug ?: return
        viewModelScope.launch {
            clinicRepository.rememberClinic(
                name = clinic.name,
                slug = slug,
                address = clinic.address,
                id = clinic.id
            )
        }
    }

    fun selectStartPet(petId: Int) {
        _startState.value = _startState.value.copy(selectedPetId = petId)
    }

    /**
     * Pilih klinik tujuan booking. Dokter klinik lain dibuang dari pilihan
     * karena satu booking hanya boleh berisi satu klinik — lebih baik
     * direset sekarang daripada gagal saat submit.
     */
    fun selectStartClinic(clinicId: Int) {
        if (_startState.value.selectedClinicId == clinicId) return
        _startState.value = _startState.value.copy(
            selectedClinicId = clinicId,
            selectedDoctorId = null,
            searchQuery = ""
        )
    }

    /** Batalkan pilihan klinik (kembali ke daftar klinik). */
    fun clearStartClinic() {
        _startState.value = _startState.value.copy(
            selectedClinicId = null,
            selectedDoctorId = null,
            searchQuery = ""
        )
    }

    /** Hanya menerima dokter dari klinik terpilih — penjaga terakhir di VM. */
    fun selectStartDoctor(doctorId: Int) {
        val state = _startState.value
        val clinicId = state.selectedClinicId ?: return
        val belongs = state.doctors.any { it.id == doctorId && it.clinic?.id == clinicId }
        if (!belongs) {
            // Satu booking = satu klinik. Tidak pernah dicampur diam-diam.
            _startState.value = state.copy(
                error = "Dokter itu tidak praktik di klinik ini. Untuk dokter tersebut, " +
                    "ganti klinik tujuan lebih dulu."
            )
            return
        }
        _startState.value = state.copy(selectedDoctorId = doctorId, error = null)
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
