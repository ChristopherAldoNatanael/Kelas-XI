package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PetShopOrdersUiState(
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val orders: List<PetShopOrder> = emptyList(),
    val page: Int = 1,
    val lastPage: Int = 1
) {
    val hasMore: Boolean get() = page < lastPage
}

@HiltViewModel
class PetShopOrdersViewModel @Inject constructor(
    private val repository: PetShopRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PetShopOrdersUiState())
    val uiState: StateFlow<PetShopOrdersUiState> = _uiState.asStateFlow()

    init {
        load()
    }

    fun load(isRefresh: Boolean = false) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isLoading = !isRefresh && it.orders.isEmpty(),
                    isRefreshing = isRefresh,
                    error = null
                )
            }

            when (val result = repository.loadOrders(page = 1)) {
                is Result.Success -> {
                    val (orders, meta) = result.data
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            isRefreshing = false,
                            error = null,
                            orders = orders,
                            page = meta?.currentPage ?: 1,
                            lastPage = meta?.lastPage ?: 1
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, isRefreshing = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false, isRefreshing = false) }
            }
        }
    }

    fun refresh() = load(isRefresh = true)

    fun loadMore() {
        val state = _uiState.value
        if (state.isLoading || state.isRefreshing || state.isLoadingMore || !state.hasMore) return

        viewModelScope.launch {
            _uiState.update { it.copy(isLoadingMore = true) }
            when (val result = repository.loadOrders(page = state.page + 1)) {
                is Result.Success -> {
                    val (orders, meta) = result.data
                    _uiState.update {
                        it.copy(
                            isLoadingMore = false,
                            orders = it.orders + orders,
                            page = meta?.currentPage ?: (it.page + 1),
                            lastPage = meta?.lastPage ?: it.lastPage
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoadingMore = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoadingMore = false) }
            }
        }
    }
}
