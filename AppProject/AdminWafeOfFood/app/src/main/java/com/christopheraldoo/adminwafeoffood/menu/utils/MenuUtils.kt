package com.christopheraldoo.adminwafeoffood.menu.utils

import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.model.MenuStatistics

object MenuUtils {
    
    fun formatPrice(price: Double): String {
        return "Rp ${String.format("%,.0f", price)}"
    }
    
    fun validatePrice(priceText: String): Pair<Boolean, String> {
        return try {
            val price = priceText.toDouble()
            when {
                price <= 0 -> false to "Harga harus lebih dari 0"
                price > 10000000 -> false to "Harga maksimal Rp 10.000.000"
                else -> true to "Valid"
            }
        } catch (e: NumberFormatException) {
            false to "Format harga tidak valid"
        }
    }
    
    fun validateMenuName(name: String): Pair<Boolean, String> {
        return when {
            name.isBlank() -> false to "Nama menu tidak boleh kosong"
            name.length < 3 -> false to "Nama menu minimal 3 karakter"
            name.length > 50 -> false to "Nama menu maksimal 50 karakter"
            else -> true to "Valid"
        }
    }
    
    fun validateDescription(description: String): Pair<Boolean, String> {
        return when {
            description.isBlank() -> false to "Deskripsi tidak boleh kosong"
            description.length < 10 -> false to "Deskripsi minimal 10 karakter"
            description.length > 200 -> false to "Deskripsi maksimal 200 karakter"
            else -> true to "Valid"
        }
    }
    
    fun generateMenuStatistics(menuList: List<MenuItem>): MenuStatistics {
        val total = menuList.size
        val available = menuList.count { it.isAvailable }
        val unavailable = total - available
        val categories = menuList.map { it.category }.distinct().size
        val avgPrice = if (total > 0) menuList.map { it.price }.average() else 0.0
        val mostExpensive = menuList.maxByOrNull { it.price }?.name ?: ""
        val cheapest = menuList.minByOrNull { it.price }?.name ?: ""
        
        return MenuStatistics(
            totalMenus = total,
            availableMenus = available,
            unavailableMenus = unavailable,
            totalCategories = categories,
            averagePrice = avgPrice,
            mostExpensiveMenu = mostExpensive,
            cheapestMenu = cheapest
        )
    }
    
    // Helper method untuk format currency
    fun formatCurrency(amount: Double): String {
        return "Rp ${String.format("%,.0f", amount)}"
    }
    
    // Helper method untuk validasi email admin
    fun isValidEmail(email: String): Boolean {
        return android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()
    }
    
    // Helper method untuk generate random menu ID
    fun generateMenuId(): String {
        return "menu_${System.currentTimeMillis()}_${(1000..9999).random()}"
    }
    
    // Helper method untuk filter menu berdasarkan kategori
    fun filterMenusByCategory(menuList: List<MenuItem>, category: String): List<MenuItem> {
        return if (category == "all") {
            menuList
        } else {
            menuList.filter { it.category == category }
        }
    }
    
    // Helper method untuk filter menu berdasarkan ketersediaan
    fun filterMenusByAvailability(menuList: List<MenuItem>, availableOnly: Boolean): List<MenuItem> {
        return if (availableOnly) {
            menuList.filter { it.isAvailable }
        } else {
            menuList
        }
    }
    
    // Helper method untuk sort menu
    fun sortMenus(menuList: List<MenuItem>, sortBy: String): List<MenuItem> {
        return when (sortBy) {
            "name_asc" -> menuList.sortedBy { it.name }
            "name_desc" -> menuList.sortedByDescending { it.name }
            "price_asc" -> menuList.sortedBy { it.price }
            "price_desc" -> menuList.sortedByDescending { it.price }
            "date_newest" -> menuList.sortedByDescending { it.createdAt }
            "date_oldest" -> menuList.sortedBy { it.createdAt }
            else -> menuList
        }
    }
}