package com.christopheraldoo.petheal.ui.screens.discovery

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.CompareItem
import com.christopheraldoo.petheal.data.model.CompareMeta
import com.christopheraldoo.petheal.data.model.ServiceCategoryDto
import com.christopheraldoo.petheal.data.repository.ClinicContextResolver
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository.CompareSort
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PriceCompareUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val categories: List<ServiceCategoryDto> = emptyList(),
    val selectedCategorySlug: String? = null,
    val serviceId: Int? = null,
    val sort: CompareSort = CompareSort.CHEAPEST,
    val minRating: Double? = null,
    val maxDistanceKm: Double? = null,
    val openNowOnly: Boolean = false,
    val items: List<CompareItem> = emptyList(),
    val meta: CompareMeta? = null,
    /** Nama klinik yang akan dipakai saat booking (dari preferensi). */
    val activeClinicName: String? = null,
    /** Slug klinik aktif — untuk menandai baris mana yang benar-benar bisa dibooking. */
    val activeClinicSlug: String? = null,
    val hasActiveClinic: Boolean = false
) {
    val hasSelection: Boolean get() = selectedCategorySlug != null || serviceId != null

    fun isActiveClinic(slug: String?): Boolean =
        slug != null && slug == activeClinicSlug
}

/**
 * Perbandingan harga antar klinik.
 *
 * Dasar pembanding SELALU kategori standar (`comparison_basis` dari backend),
 * bukan nama layanan yang mirip — sesuai aturan "jangan membandingkan layanan
 * berbeda hanya karena namanya mirip".
 */
@HiltViewModel
class PriceCompareViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val repository: DiscoveryRepository,
    private val clinicContext: ClinicContextResolver
) : ViewModel() {

    private val initialCategory: String? = savedStateHandle.get<String>("categorySlug")
    private val initialServiceId: Int? = savedStateHandle.get<Int>("serviceId")

    private val _uiState = MutableStateFlow(
        PriceCompareUiState(
            selectedCategorySlug = initialCategory?.takeIf { it.isNotBlank() },
            serviceId = initialServiceId?.takeIf { it > 0 }
        )
    )
    val uiState: StateFlow<PriceCompareUiState> = _uiState.asStateFlow()

    init {
        loadCategoriesAndCompare()
    }

    private fun loadCategoriesAndCompare() {
        viewModelScope.launch {
            val active = clinicContext.activeClinic()
            _uiState.value = _uiState.value.copy(
                activeClinicName = active.name,
                activeClinicSlug = active.slug,
                hasActiveClinic = active.isSet
            )
            if (_uiState.value.categories.isEmpty()) {
                (repository.loadCategories() as? Result.Success)?.let { loaded ->
                    _uiState.value = _uiState.value.copy(categories = loaded.data)
                }
            }
            compare()
        }
    }

    fun selectCategory(slug: String?) {
        _uiState.value = _uiState.value.copy(
            selectedCategorySlug = if (_uiState.value.selectedCategorySlug == slug) null else slug,
            serviceId = null
        )
        compare()
    }

    fun setSort(sort: CompareSort) {
        _uiState.value = _uiState.value.copy(sort = sort)
        compare()
    }

    fun setMinRating(value: Double?) {
        _uiState.value = _uiState.value.copy(minRating = value)
        compare()
    }

    fun setMaxDistance(value: Double?) {
        _uiState.value = _uiState.value.copy(maxDistanceKm = value)
        compare()
    }

    fun setOpenNow(value: Boolean) {
        _uiState.value = _uiState.value.copy(openNowOnly = value)
        compare()
    }

    fun retry() = compare()

    private fun compare() {
        val state = _uiState.value
        if (!state.hasSelection) {
            _uiState.value = state.copy(isLoading = false, error = null, items = emptyList(), meta = null)
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            when (val result = repository.compare(
                categorySlug = state.selectedCategorySlug,
                serviceId = state.serviceId,
                sort = state.sort,
                minRating = state.minRating,
                maxDistanceKm = state.maxDistanceKm,
                openNow = state.openNowOnly.takeIf { it }
            )) {
                is Result.Success -> _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    items = result.data.first,
                    meta = result.data.second
                )
                is Result.Error -> _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    error = result.message
                )
                else -> _uiState.value = _uiState.value.copy(isLoading = false)
            }
        }
    }
}
