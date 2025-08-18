package com.christopheraldoo.wavesoffood.ui.menu

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import android.util.Log
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.onStart
import kotlinx.coroutines.launch

class MenuViewModel : ViewModel() {
    
    private val TAG = "MenuViewModel"
    private val menuRepository = MenuRepository.getInstance()
    
    // LiveData untuk UI state
    private val _menuState = MutableLiveData<MenuState>()
    val menuState: LiveData<MenuState> = _menuState
    
    // LiveData untuk menu list
    private val _menuList = MutableLiveData<List<MenuItem>>()
    val menuList: LiveData<List<MenuItem>> = _menuList
    
    // LiveData untuk search query
    private val _searchQuery = MutableLiveData<String>()
    val searchQuery: LiveData<String> = _searchQuery
    
    // LiveData untuk price range filter
    private val _priceRange = MutableLiveData<Pair<Int, Int>?>()
    val priceRange: LiveData<Pair<Int, Int>?> = _priceRange
    
    init {
        Log.d(TAG, "MenuViewModel initialized")
        loadAllMenus()
    }
    
    /**
     * Load semua menu dari Firebase
     */
    fun loadAllMenus() {
        Log.d(TAG, "loadAllMenus() called")
        viewModelScope.launch {
            _menuState.value = MenuState.Loading
            Log.d(TAG, "Menu state set to Loading")
            
            menuRepository.getAllMenus()
                .onStart { 
                    Log.d(TAG, "getAllMenus flow started")
                    _menuState.value = MenuState.Loading 
                }
                .catch { error ->
                    Log.e(TAG, "❌ Error in getAllMenus flow: ${error.message}", error)
                    _menuState.value = MenuState.Error(error.message ?: "Unknown error occurred")
                }
                .collectLatest { result ->
                    Log.d(TAG, "getAllMenus result received: $result")
                    
                    result.fold(
                        onSuccess = { menus ->
                            Log.d(TAG, "✅ Success: Loaded ${menus.size} menus")
                            Log.d(TAG, "Menu names: ${menus.map { it.name }}")
                            
                            _menuList.value = menus
                            _menuState.value = if (menus.isEmpty()) {
                                Log.d(TAG, "⚠️ Menu list is empty")
                                MenuState.Empty
                            } else {
                                Log.d(TAG, "✅ Menu list has data")
                                MenuState.Success
                            }
                        },
                        onFailure = { error ->
                            Log.e(TAG, "❌ Failure: ${error.message}", error)
                            _menuState.value = MenuState.Error(error.message ?: "Failed to load menus")
                        }
                    )
                }
        }
    }
    
    /**
     * Search menu berdasarkan query
     */
    fun searchMenus(query: String) {
        Log.d(TAG, "searchMenus() called with query: '$query'")
        _searchQuery.value = query
        
        if (query.isBlank()) {
            Log.d(TAG, "Query is blank, loading all menus")
            loadAllMenus()
            return
        }
        
        viewModelScope.launch {
            _menuState.value = MenuState.Loading
            
            menuRepository.searchMenus(query)
                .onStart { _menuState.value = MenuState.Loading }
                .catch { error ->
                    Log.e(TAG, "Error in searchMenus flow: ${error.message}", error)
                    _menuState.value = MenuState.Error(error.message ?: "Unknown error occurred")
                }
                .collectLatest { result ->
                    result.fold(
                        onSuccess = { menus ->
                            Log.d(TAG, "Search result: ${menus.size} menus found")
                            _menuList.value = menus
                            _menuState.value = if (menus.isEmpty()) {
                                MenuState.Empty
                            } else {
                                MenuState.Success
                            }
                        },
                        onFailure = { error ->
                            Log.e(TAG, "Search failed: ${error.message}", error)
                            _menuState.value = MenuState.Error(error.message ?: "Failed to search menus")
                        }
                    )
                }
        }
    }
    
    /**
     * Load menu yang tersedia saja
     */
    fun loadAvailableMenus() {
        Log.d(TAG, "loadAvailableMenus() called")
        viewModelScope.launch {
            _menuState.value = MenuState.Loading
            
            menuRepository.getAvailableMenus()
                .onStart { _menuState.value = MenuState.Loading }
                .catch { error ->
                    Log.e(TAG, "Error in loadAvailableMenus flow: ${error.message}", error)
                    _menuState.value = MenuState.Error(error.message ?: "Unknown error occurred")
                }
                .collectLatest { result ->
                    result.fold(
                        onSuccess = { menus ->
                            Log.d(TAG, "Available menus result: ${menus.size} menus")
                            _menuList.value = menus
                            _menuState.value = if (menus.isEmpty()) {
                                MenuState.Empty
                            } else {
                                MenuState.Success
                            }
                        },
                        onFailure = { error ->
                            Log.e(TAG, "Load available menus failed: ${error.message}", error)
                            _menuState.value = MenuState.Error(error.message ?: "Failed to load available menus")
                        }
                    )
                }
        }
    }
    
    /**
     * Filter menu berdasarkan range harga
     */
    fun filterMenusByPrice(minPrice: Int, maxPrice: Int) {
        Log.d(TAG, "filterMenusByPrice() called: $minPrice - $maxPrice")
        _priceRange.value = Pair(minPrice, maxPrice)
        
        viewModelScope.launch {
            _menuState.value = MenuState.Loading
            
            menuRepository.getMenusByPriceRange(minPrice, maxPrice)
                .onStart { _menuState.value = MenuState.Loading }
                .catch { error ->
                    Log.e(TAG, "Error in filterMenusByPrice flow: ${error.message}", error)
                    _menuState.value = MenuState.Error(error.message ?: "Unknown error occurred")
                }
                .collectLatest { result ->
                    result.fold(
                        onSuccess = { menus ->
                            Log.d(TAG, "Price filter result: ${menus.size} menus")
                            _menuList.value = menus
                            _menuState.value = if (menus.isEmpty()) {
                                MenuState.Empty
                            } else {
                                MenuState.Success
                            }
                        },
                        onFailure = { error ->
                            Log.e(TAG, "Price filter failed: ${error.message}", error)
                            _menuState.value = MenuState.Error(error.message ?: "Failed to filter menus by price")
                        }
                    )
                }
        }
    }
    
    /**
     * Clear semua filter
     */
    fun clearFilters() {
        Log.d(TAG, "clearFilters() called")
        _priceRange.value = null
        _searchQuery.value = ""
        loadAllMenus()
    }
    
    /**
     * Get menu berdasarkan ID
     */
    fun getMenuById(menuId: String, onResult: (MenuItem?) -> Unit) {
        Log.d(TAG, "getMenuById() called with ID: $menuId")
        viewModelScope.launch {
            val result = menuRepository.getMenuById(menuId)
            result.fold(
                onSuccess = { menu -> 
                    Log.d(TAG, "getMenuById success: ${menu?.name}")
                    onResult(menu) 
                },
                onFailure = { error -> 
                    Log.e(TAG, "getMenuById failed: ${error.message}", error)
                    _menuState.value = MenuState.Error(error.message ?: "Failed to get menu")
                    onResult(null)
                }
            )
        }
    }
    
    /**
     * Refresh data menu
     */
    fun refreshMenus() {
        Log.d(TAG, "refreshMenus() called")
        loadAllMenus()
    }
}

/**
 * State untuk UI menu
 */
sealed class MenuState {
    object Loading : MenuState()
    object Success : MenuState()
    object Empty : MenuState()
    data class Error(val message: String) : MenuState()
} 