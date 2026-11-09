package com.christopheraldoo.petheal.ui.screens.discovery

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.ClinicCard
import com.christopheraldoo.petheal.data.model.ServiceCategoryDto
import com.christopheraldoo.petheal.data.model.UserLocation
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository.ClinicQuery
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository.DiscoverySort
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.util.LocationHelper
import com.christopheraldoo.petheal.util.LocationResult
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State lokasi untuk seluruh layar discovery.
 *
 * Karena lokasi OPSIONAL, kegagalan bukan error halaman — hanya status yang
 * ditampilkan di kartu lokasi dengan aksi "Pilih manual"/"Coba lagi".
 */
enum class LocationUiStatus { Unset, Loading, Ready, PermissionDenied, ServicesDisabled, Failed }

data class DiscoveryUiState(
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val error: String? = null,
    val clinics: List<ClinicCard> = emptyList(),
    val nearest: List<ClinicCard> = emptyList(),
    val recommended: List<ClinicCard> = emptyList(),
    val categories: List<ServiceCategoryDto> = emptyList(),
    val location: UserLocation? = null,
    val locationStatus: LocationUiStatus = LocationUiStatus.Unset,
    val search: String = "",
    val selectedCategory: String? = null,
    val sort: DiscoverySort = DiscoverySort.NEAREST,
    val minRating: Double? = null,
    val maxDistanceKm: Double? = null,
    val openNowOnly: Boolean = false
) {
    val hasLocation: Boolean get() = location != null
}

@HiltViewModel
class DiscoveryViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val repository: DiscoveryRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(DiscoveryUiState())
    val uiState: StateFlow<DiscoveryUiState> = _uiState.asStateFlow()

    private var searchJob: Job? = null
    private var loadedOnce = false

    init {
        viewModelScope.launch {
            val saved = repository.currentLocation()
            _uiState.value = _uiState.value.copy(
                location = saved,
                locationStatus = if (saved == null) LocationUiStatus.Unset else LocationUiStatus.Ready
            )
            loadAll(showFullLoading = true)
        }
    }

    /** Muat klinik + kategori. Dipanggil ulang saat filter berubah. */
    fun loadAll(showFullLoading: Boolean = false) {
        if (loadedOnce && showFullLoading) return
        loadedOnce = true
        fetch(showFullLoading = showFullLoading)
    }

    fun refresh() = fetch(showFullLoading = false, forceRefresh = true)

    private fun fetch(showFullLoading: Boolean, forceRefresh: Boolean = false) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isLoading = showFullLoading,
                isRefreshing = !showFullLoading,
                error = null
            )

            if (_uiState.value.categories.isEmpty()) {
                (repository.loadCategories() as? Result.Success)?.let { loaded ->
                    _uiState.value = _uiState.value.copy(categories = loaded.data)
                }
            }

            val state = _uiState.value
            val query = ClinicQuery(
                search = state.search.ifBlank { null },
                categorySlug = state.selectedCategory,
                sort = state.sort,
                minRating = state.minRating,
                maxDistanceKm = state.maxDistanceKm,
                openNow = state.openNowOnly.takeIf { it }
            )

            when (val result = repository.loadClinics(query, forceRefresh)) {
                is Result.Success -> {
                    val clinics = result.data
                    // "Terdekat" hanya bila lokasi tersedia — jangan mengarang urutan.
                    val nearest = if (state.hasLocation) {
                        clinics.filter { it.distanceKm != null }
                            .sortedBy { it.distanceKm }
                            .take(3)
                    } else {
                        emptyList()
                    }
                    // Rekomendasi = rating nyata tertinggi; klinik tanpa ulasan
                    // tidak masuk (bukan rating 0).
                    val recommended = clinics
                        .filter { (it.rating ?: 0.0) > 0.0 }
                        .sortedByDescending { it.rating }
                        .take(3)

                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        isRefreshing = false,
                        error = null,
                        clinics = clinics,
                        nearest = nearest,
                        recommended = recommended
                    )
                }
                is Result.Error -> _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    isRefreshing = false,
                    error = result.message
                )
                else -> _uiState.value = _uiState.value.copy(isLoading = false, isRefreshing = false)
            }
        }
    }

    // ── Pencarian & filter ──────────────────────────────────────────────────

    fun onSearchChange(value: String) {
        _uiState.value = _uiState.value.copy(search = value)
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(350) // debounce ketik
            fetch(showFullLoading = false)
        }
    }

    fun onCategorySelected(slug: String?) {
        _uiState.value = _uiState.value.copy(
            selectedCategory = if (_uiState.value.selectedCategory == slug) null else slug
        )
        fetch(showFullLoading = false)
    }

    fun onSortChange(sort: DiscoverySort) {
        _uiState.value = _uiState.value.copy(sort = sort)
        fetch(showFullLoading = false)
    }

    fun onMinRatingChange(value: Double?) {
        _uiState.value = _uiState.value.copy(minRating = value)
        fetch(showFullLoading = false)
    }

    fun onMaxDistanceChange(value: Double?) {
        _uiState.value = _uiState.value.copy(maxDistanceKm = value)
        fetch(showFullLoading = false)
    }

    fun onOpenNowToggle(value: Boolean) {
        _uiState.value = _uiState.value.copy(openNowOnly = value)
        fetch(showFullLoading = false)
    }

    fun resetFilters() {
        _uiState.value = _uiState.value.copy(
            selectedCategory = null,
            sort = DiscoverySort.NEAREST,
            minRating = null,
            maxDistanceKm = null,
            openNowOnly = false,
            search = ""
        )
        fetch(showFullLoading = false)
    }

    // ── Lokasi ──────────────────────────────────────────────────────────────

    /**
     * Ambil lokasi GPS. Tidak pernah memaksa: kegagalan hanya mengubah status
     * sehingga UI menampilkan penjelasan + opsi input manual.
     */
    fun requestGpsLocation() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(locationStatus = LocationUiStatus.Loading)
            when (val result = LocationHelper.currentLocation(context)) {
                is LocationResult.Success -> {
                    val location = UserLocation(
                        latitude = result.latitude,
                        longitude = result.longitude,
                        label = "Lokasi GPS"
                    )
                    repository.saveLocation(location)
                    _uiState.value = _uiState.value.copy(
                        location = location,
                        locationStatus = LocationUiStatus.Ready
                    )
                    fetch(showFullLoading = false, forceRefresh = true)
                }
                LocationResult.PermissionDenied -> _uiState.value = _uiState.value.copy(
                    locationStatus = LocationUiStatus.PermissionDenied
                )
                LocationResult.ServicesDisabled -> _uiState.value = _uiState.value.copy(
                    locationStatus = LocationUiStatus.ServicesDisabled
                )
                LocationResult.Timeout, LocationResult.Unavailable -> _uiState.value =
                    _uiState.value.copy(locationStatus = LocationUiStatus.Failed)
            }
        }
    }

    /**
     * Lokasi manual: koordinat tetap dibutuhkan untuk menghitung jarak.
     * Pengguna memilih klinik acuan yang dikenal (mis. "dekat klinik X")
     * atau memasukkan koordinat sendiri di layar pengaturan lokasi.
     */
    fun setManualLocation(latitude: Double, longitude: Double, label: String?) {
        viewModelScope.launch {
            val location = UserLocation(
                latitude = latitude,
                longitude = longitude,
                label = label ?: "Lokasi manual",
                source = com.christopheraldoo.petheal.data.model.LocationSource.MANUAL
            )
            repository.saveLocation(location)
            _uiState.value = _uiState.value.copy(
                location = location,
                locationStatus = LocationUiStatus.Ready
            )
            fetch(showFullLoading = false, forceRefresh = true)
        }
    }

    fun clearLocation() {
        viewModelScope.launch {
            repository.clearLocation()
            _uiState.value = _uiState.value.copy(
                location = null,
                locationStatus = LocationUiStatus.Unset,
                nearest = emptyList()
            )
            fetch(showFullLoading = false, forceRefresh = true)
        }
    }

    fun dismissLocationStatus() {
        _uiState.value = _uiState.value.copy(
            locationStatus = if (_uiState.value.location == null) {
                LocationUiStatus.Unset
            } else {
                LocationUiStatus.Ready
            }
        )
    }
}
