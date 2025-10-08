package com.christopheraldoo.adminwafeoffood.menu.viewmodel

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.repository.MenuRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.launch

class MenuViewModel : ViewModel() {
    
    private val repository = MenuRepository()
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()
    
    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()
    
    private val _menuList = MutableStateFlow<List<MenuItem>>(emptyList())
    val menuList: StateFlow<List<MenuItem>> = _menuList.asStateFlow()
    
    companion object {
        private const val TAG = "MenuViewModel"
    }
      init {
        Log.d(TAG, "MenuViewModel initialized")
        // Don't call loadMenus() in init to prevent automatic loading
        // Fragment will call loadMenus() or refreshMenus() when ready
    }
    
    fun loadMenus() {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Starting to load menus...")
                _isLoading.value = true
                _error.value = null // Clear previous errors
                
                // Use safer error handling
                repository.getAllMenus()
                    .catch { exception ->
                        Log.e(TAG, "Error in menu flow", exception)
                        _error.value = "Error loading menus: ${exception.message}"
                        _menuList.value = emptyList()
                        _isLoading.value = false
                    }
                    .collect { menuList ->
                        try {
                            Log.d(TAG, "Received ${menuList.size} menus from repository")
                            _menuList.value = menuList
                            _isLoading.value = false
                        } catch (e: Exception) {
                            Log.e(TAG, "Error processing collected menus", e)
                            _error.value = "Error processing menu data: ${e.message}"
                            _menuList.value = emptyList()
                            _isLoading.value = false
                        }
                    }
                    
            } catch (e: Exception) {
                Log.e(TAG, "Error loading menus", e)
                _error.value = "Error loading menus: ${e.message}"
                _menuList.value = emptyList()
                _isLoading.value = false
            }
        }
    }

    fun refreshMenus() {
        Log.d(TAG, "Refreshing menus...")
        loadMenus()
    }
    
    fun addMenu(menuItem: MenuItem) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Adding menu: ${menuItem.name}")
                _isLoading.value = true
                
                val response = repository.addMenu(menuItem)
                Log.d(TAG, "Add menu response: ${response.message}")
                
                if (response.result.name != "SUCCESS") {
                    _error.value = response.message
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error adding menu", e)
                _error.value = "Failed to add menu: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }
    
    fun updateMenu(menuItem: MenuItem) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Updating menu: ${menuItem.name}")
                _isLoading.value = true
                
                val response = repository.updateMenu(menuItem)
                Log.d(TAG, "Update menu response: ${response.message}")
                
                if (response.result.name != "SUCCESS") {
                    _error.value = response.message
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error updating menu", e)
                _error.value = "Failed to update menu: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }
    
    fun deleteMenu(menuId: String) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Deleting menu: $menuId")
                
                val response = repository.deleteMenu(menuId)
                Log.d(TAG, "Delete menu response: ${response.message}")
                
                if (response.result.name != "SUCCESS") {
                    _error.value = response.message
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error deleting menu", e)
                _error.value = "Failed to delete menu: ${e.message}"
            }
        }
    }
    
    fun updateMenuAvailability(menuId: String, isAvailable: Boolean) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Updating availability: $menuId -> $isAvailable")
                
                val response = repository.updateMenuAvailability(menuId, isAvailable)
                
                if (response.result.name != "SUCCESS") {
                    _error.value = response.message
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error updating availability", e)
                _error.value = "Failed to update availability: ${e.message}"
            }
        }
    }
    
    suspend fun getMenuById(menuId: String): MenuItem? {
        return try {
            Log.d(TAG, "Getting menu by ID: $menuId")
            repository.getMenuById(menuId)
        } catch (e: Exception) {
            Log.e(TAG, "Error getting menu by ID", e)
            _error.value = "Failed to get menu: ${e.message}"
            null
        }
    }
    
    fun clearError() {
        Log.d(TAG, "Clearing error")
        _error.value = null
    }
}
