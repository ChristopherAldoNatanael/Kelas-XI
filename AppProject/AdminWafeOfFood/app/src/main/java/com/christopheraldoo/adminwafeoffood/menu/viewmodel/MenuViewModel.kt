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
        loadMenus()
    }
    
    private fun loadMenus() {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Starting to load menus...")
                _isLoading.value = true
                
                // PERBAIKI - gunakan catch untuk handle error
                repository.getAllMenus()
                    .catch { exception ->
                        Log.e(TAG, "Error in menu flow", exception)
                        _error.value = "Error loading menus: ${exception.message}"
                        _menuList.value = emptyList()
                        _isLoading.value = false // PENTING: Set loading false saat error
                    }
                    .collect { menuList ->
                        Log.d(TAG, "Received ${menuList.size} menus from repository")
                        _menuList.value = menuList
                        _isLoading.value = false // PENTING: Set loading false saat sukses
                    }
                    
            } catch (e: Exception) {
                Log.e(TAG, "Error loading menus", e)
                _error.value = "Error loading menus: ${e.message}"
                _menuList.value = emptyList()
                _isLoading.value = false // PENTING: Set loading false saat error
            }
        }
    }
    
    fun addMenu(menuItem: MenuItem) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Adding menu: ${menuItem.name}")
                _isLoading.value = true
                
                val response = repository.addMenu(menuItem)
                Log.d(TAG, "Add menu response: ${response.message}")
                
                _error.value = response.message
                
                // TIDAK perlu manual reload - Firebase realtime akan otomatis update
                
            } catch (e: Exception) {
                Log.e(TAG, "Error adding menu", e)
                _error.value = "Failed to add menu: ${e.message}"
            } finally {
                _isLoading.value = false // PENTING: Selalu set false di finally
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
                
                _error.value = response.message
                
            } catch (e: Exception) {
                Log.e(TAG, "Error updating menu", e)
                _error.value = "Failed to update menu: ${e.message}"
            } finally {
                _isLoading.value = false // PENTING: Selalu set false
            }
        }
    }
    
    fun deleteMenu(menuId: String) {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Deleting menu: $menuId")
                // TIDAK set loading untuk delete - biar responsive
                
                val response = repository.deleteMenu(menuId)
                Log.d(TAG, "Delete menu response: ${response.message}")
                
                _error.value = response.message
                
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
                // TIDAK set loading untuk toggle - biar responsive
                
                val response = repository.updateMenuAvailability(menuId, isAvailable)
                
                // TIDAK show success message untuk toggle sederhana
                if (response.result.name == "ERROR") {
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
    
    fun refreshMenus() {
        Log.d(TAG, "Manual refresh requested")
        loadMenus()
    }
}