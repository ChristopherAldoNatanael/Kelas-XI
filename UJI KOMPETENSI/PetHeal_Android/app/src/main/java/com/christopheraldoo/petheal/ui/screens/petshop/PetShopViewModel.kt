package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopCategory
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.data.model.PetShopSeller
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

enum class PetShopSort(val apiValue: String, val label: String) {
    NEWEST("newest", "Terbaru"),
    POPULAR("popular", "Terlaris"),
    PRICE_ASC("price_asc", "Harga terendah"),
    PRICE_DESC("price_desc", "Harga tertinggi"),
    NAME("name", "Nama A-Z")
}

data class PetShopUiState(
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val categories: List<PetShopCategory> = emptyList(),
    val sellers: List<PetShopSeller> = emptyList(),
    val products: List<PetShopProduct> = emptyList(),
    val search: String = "",
    val selectedCategory: String? = null,
    val selectedClinicId: Int? = null,
    val sort: PetShopSort = PetShopSort.NEWEST,
    val page: Int = 1,
    val lastPage: Int = 1,
    val cartCount: Int = 0,
    val cartSubtotal: Long = 0,
    val notice: String? = null
) {
    val hasMore: Boolean get() = page < lastPage
    val hasActiveFilters: Boolean
        get() = search.isNotBlank() || selectedCategory != null || selectedClinicId != null
}

@HiltViewModel
class PetShopViewModel @Inject constructor(
    private val repository: PetShopRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PetShopUiState())
    val uiState: StateFlow<PetShopUiState> = _uiState.asStateFlow()

    private var searchJob: Job? = null
    private var loadJob: Job? = null

    init {
        viewModelScope.launch {
            repository.cart.collect { lines ->
                _uiState.update {
                    it.copy(
                        cartCount = lines.sumOf { line -> line.quantity },
                        cartSubtotal = lines.sumOf { line -> line.lineTotal }
                    )
                }
            }
        }
        loadInitial()
    }

    private fun loadInitial() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }

            (repository.loadCategories() as? Result.Success)?.let { loaded ->
                _uiState.update { it.copy(categories = loaded.data) }
            }
            (repository.loadSellers() as? Result.Success)?.let { loaded ->
                _uiState.update { it.copy(sellers = loaded.data) }
            }

            fetchProducts(showFullLoading = true)
        }
    }

    private fun fetchProducts(showFullLoading: Boolean = false, append: Boolean = false) {
        loadJob?.cancel()
        loadJob = viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isLoading = showFullLoading && !append,
                    isRefreshing = !showFullLoading && !append,
                    isLoadingMore = append,
                    error = null
                )
            }

            val state = _uiState.value
            val page = if (append) state.page + 1 else 1

            when (val result = repository.loadProducts(
                search = state.search,
                categorySlug = state.selectedCategory,
                clinicId = state.selectedClinicId,
                sort = state.sort.apiValue,
                page = page
            )) {
                is Result.Success -> {
                    val (products, meta) = result.data
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            isRefreshing = false,
                            isLoadingMore = false,
                            products = if (append) it.products + products else products,
                            page = meta?.currentPage ?: page,
                            lastPage = meta?.lastPage ?: page
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(
                        isLoading = false,
                        isRefreshing = false,
                        isLoadingMore = false,
                        error = if (append) null else result.message,
                        notice = if (append) result.message else it.notice
                    )
                }
                else -> _uiState.update {
                    it.copy(isLoading = false, isRefreshing = false, isLoadingMore = false)
                }
            }
        }
    }

    fun refresh() = fetchProducts(showFullLoading = false)

    fun loadMore() {
        val state = _uiState.value
        if (state.isLoading || state.isLoadingMore || !state.hasMore) return
        fetchProducts(append = true)
    }

    fun onSearchChange(value: String) {
        _uiState.update { it.copy(search = value) }
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(350)
            fetchProducts(showFullLoading = false)
        }
    }

    fun onCategorySelected(slug: String?) {
        _uiState.update {
            it.copy(selectedCategory = if (it.selectedCategory == slug) null else slug)
        }
        fetchProducts(showFullLoading = false)
    }

    fun onClinicSelected(clinicId: Int?) {
        _uiState.update {
            it.copy(selectedClinicId = if (it.selectedClinicId == clinicId) null else clinicId)
        }
        fetchProducts(showFullLoading = false)
    }

    fun onSortSelected(sort: PetShopSort) {
        if (_uiState.value.sort == sort) return
        _uiState.update { it.copy(sort = sort) }
        fetchProducts(showFullLoading = false)
    }

    fun resetFilters() {
        _uiState.update {
            it.copy(search = "", selectedCategory = null, selectedClinicId = null, sort = PetShopSort.NEWEST)
        }
        fetchProducts(showFullLoading = false)
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }
}
