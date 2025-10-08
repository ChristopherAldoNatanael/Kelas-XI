package com.christopheraldoo.adminwafeoffood.menu.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import java.text.NumberFormat
import java.util.*

// =================================================================================
// MENU ITEM MODEL - Model untuk Realtime Database
// =================================================================================

@Parcelize
data class MenuItem(
    val id: String = "",
    val name: String = "",
    val description: String = "",
    val price: Int = 0, // Ubah ke Int untuk matching dengan struktur Firebase
    val category: String = "",
    val imageURL: String = "", // Ubah ke imageURL untuk matching dengan struktur Firebase
    val isAvailable: Boolean = true,
    val adminId: String = "admin_001", // Set default adminId
    val createdAt: Long = System.currentTimeMillis(),
    val updatedAt: Long = System.currentTimeMillis()
) : Parcelable {
    
    // Helper methods untuk format tampilan
    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,d", price)}"
    }
    
    // Convert to Map untuk Firebase Realtime Database - sesuai struktur yang ada
    fun toMap(): Map<String, Any> {
        return mapOf(
            "id" to id,
            "name" to name,
            "description" to description,
            "price" to price,
            "category" to category,
            "imageURL" to imageURL, // Gunakan imageURL
            "isAvailable" to isAvailable,
            "adminId" to adminId,
            "createdAt" to createdAt,
            "updatedAt" to updatedAt
        )
    }

    companion object {
        // Create from Map untuk Firebase Realtime Database
        fun fromMap(data: Map<String, Any>, id: String): MenuItem? {
            return try {
                MenuItem(
                    id = data["id"] as? String ?: id,
                    name = data["name"] as? String ?: "",
                    description = data["description"] as? String ?: "",
                    price = (data["price"] as? Number)?.toInt() ?: 0, // Convert to Int
                    category = data["category"] as? String ?: "",
                    imageURL = data["imageURL"] as? String ?: "", // Gunakan imageURL
                    isAvailable = data["isAvailable"] as? Boolean ?: true,
                    adminId = data["adminId"] as? String ?: "admin_001",
                    createdAt = (data["createdAt"] as? Number)?.toLong() ?: System.currentTimeMillis(),
                    updatedAt = (data["updatedAt"] as? Number)?.toLong() ?: System.currentTimeMillis()
                )
            } catch (e: Exception) {
                null
            }        }
    }
}

// =================================================================================
// MENU CATEGORY MODEL
// =================================================================================

data class MenuCategory(
    val id: String = "",
    val name: String = "",
    val displayName: String = "",
    val description: String = "",
    val iconUrl: String = "",
    val isActive: Boolean = true,
    val createdAt: Long = 0L
) {
    // Empty constructor untuk Firebase
    constructor() : this("", "", "", "", "", true, 0L)
    
    // Convert to Map untuk Firebase
    fun toMap(): Map<String, Any> {
        return mapOf(
            "id" to id,
            "name" to name,
            "displayName" to displayName,
            "description" to description,
            "iconUrl" to iconUrl,
            "isActive" to isActive,
            "createdAt" to createdAt
        )
    }
}

// =================================================================================
// OPERATION RESULT MODELS
// =================================================================================

enum class MenuOperationResult {
    SUCCESS,
    ERROR,
    LOADING,
    VALIDATION_ERROR
}

data class MenuOperationResponse(
    val result: MenuOperationResult,
    val message: String = "",
    val data: MenuItem? = null
)

// =================================================================================
// FILTER & SEARCH MODELS
// =================================================================================

data class MenuFilter(
    val category: String? = null,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val isAvailable: Boolean? = null,
    val searchQuery: String? = null
)

enum class AvailabilityFilter {
    ALL,
    AVAILABLE_ONLY,
    UNAVAILABLE_ONLY
}

enum class MenuSortBy {
    NAME_ASC,
    NAME_DESC,
    PRICE_ASC,
    PRICE_DESC,
    CREATED_DATE_ASC,
    CREATED_DATE_DESC,
    AVAILABILITY
}

// =================================================================================
// STATISTICS MODEL
// =================================================================================

data class MenuStatistics(
    val totalMenus: Int = 0,
    val availableMenus: Int = 0,
    val unavailableMenus: Int = 0,
    val totalCategories: Int = 0,
    val averagePrice: Double = 0.0,
    val mostExpensiveMenu: String = "",
    val cheapestMenu: String = ""
) {
    fun getAvailabilityPercentage(): Float {
        return if (totalMenus > 0) {
            (availableMenus.toFloat() / totalMenus.toFloat()) * 100
        } else {
            0f
        }
    }
    
    fun getFormattedAveragePrice(): String {
        return "Rp ${String.format("%,.0f", averagePrice)}"
    }
}

// =================================================================================
// PREDEFINED CATEGORIES
// =================================================================================

object DefaultMenuCategories {
    fun getCategories(): List<MenuCategory> {
        return listOf(
            MenuCategory("all", "all", "All Menu", "Semua kategori menu", "", true, 0L),
            MenuCategory("main_course", "main_course", "Main Course", "Hidangan utama", "", true, 0L),
            MenuCategory("appetizer", "appetizer", "Appetizer", "Makanan pembuka", "", true, 0L),
            MenuCategory("dessert", "dessert", "Dessert", "Hidangan penutup", "", true, 0L),
            MenuCategory("drink", "drink", "Drinks", "Minuman", "", true, 0L),
            MenuCategory("snack", "snack", "Snacks", "Makanan ringan", "", true, 0L),
            MenuCategory("special", "special", "Special Menu", "Menu spesial", "", true, 0L)
        )
    }
    
    fun getCategoryDisplayNames(): List<String> {
        return getCategories().map { it.displayName }
    }
    
    fun getCategoryByName(name: String): MenuCategory? {
        return getCategories().find { it.name == name || it.displayName == name }
    }
}

// =================================================================================
// VALIDATION HELPERS
// =================================================================================

object MenuValidation {
    const val MIN_NAME_LENGTH = 3
    const val MAX_NAME_LENGTH = 50
    const val MIN_DESCRIPTION_LENGTH = 10
    const val MAX_DESCRIPTION_LENGTH = 200
    const val MIN_PRICE = 1000.0
    const val MAX_PRICE = 10000000.0
    
    fun validateMenuItem(menuItem: MenuItem): MenuOperationResponse {
        return when {
            menuItem.name.isBlank() -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Nama menu tidak boleh kosong"
            )
            menuItem.name.length < MIN_NAME_LENGTH -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Nama menu minimal $MIN_NAME_LENGTH karakter"
            )
            menuItem.name.length > MAX_NAME_LENGTH -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Nama menu maksimal $MAX_NAME_LENGTH karakter"
            )
            menuItem.description.isBlank() -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Deskripsi menu tidak boleh kosong"
            )
            menuItem.description.length < MIN_DESCRIPTION_LENGTH -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Deskripsi menu minimal $MIN_DESCRIPTION_LENGTH karakter"
            )
            menuItem.description.length > MAX_DESCRIPTION_LENGTH -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Deskripsi menu maksimal $MAX_DESCRIPTION_LENGTH karakter"
            )
            menuItem.price < MIN_PRICE -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Harga menu minimal Rp ${String.format("%,.0f", MIN_PRICE)}"
            )
            menuItem.price > MAX_PRICE -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Harga menu maksimal Rp ${String.format("%,.0f", MAX_PRICE)}"
            )
            menuItem.category.isBlank() -> MenuOperationResponse(
                MenuOperationResult.VALIDATION_ERROR,
                "Kategori menu harus dipilih"
            )
            else -> MenuOperationResponse(MenuOperationResult.SUCCESS, "Valid")
        }
    }
}

// =================================================================================
// HELPER EXTENSIONS
// =================================================================================

fun List<MenuItem>.filterByCategory(category: String): List<MenuItem> {
    return if (category == "all") this else this.filter { it.category == category }
}

fun List<MenuItem>.filterByAvailability(availability: AvailabilityFilter): List<MenuItem> {
    return when (availability) {
        AvailabilityFilter.ALL -> this
        AvailabilityFilter.AVAILABLE_ONLY -> this.filter { it.isAvailable }
        AvailabilityFilter.UNAVAILABLE_ONLY -> this.filter { !it.isAvailable }
    }
}

fun List<MenuItem>.sortBy(sortBy: MenuSortBy): List<MenuItem> {
    return when (sortBy) {
        MenuSortBy.NAME_ASC -> this.sortedBy { it.name }
        MenuSortBy.NAME_DESC -> this.sortedByDescending { it.name }
        MenuSortBy.PRICE_ASC -> this.sortedBy { it.price }
        MenuSortBy.PRICE_DESC -> this.sortedByDescending { it.price }
        MenuSortBy.CREATED_DATE_ASC -> this.sortedBy { it.createdAt }
        MenuSortBy.CREATED_DATE_DESC -> this.sortedByDescending { it.createdAt }
        MenuSortBy.AVAILABILITY -> this.sortedByDescending { it.isAvailable }
    }
}

fun List<MenuItem>.searchByQuery(query: String): List<MenuItem> {
    return if (query.isBlank()) {
        this
    } else {
        this.filter { 
            it.name.contains(query, ignoreCase = true) || 
            it.description.contains(query, ignoreCase = true) ||
            it.category.contains(query, ignoreCase = true)
        }
    }
}

fun List<MenuItem>.toStatistics(): MenuStatistics {
    val total = this.size
    val available = this.count { it.isAvailable }
    val unavailable = total - available
    val categories = this.map { it.category }.distinct().size
    val avgPrice = if (total > 0) this.map { it.price }.average() else 0.0
    val mostExpensive = this.maxByOrNull { it.price }?.name ?: ""
    val cheapest = this.minByOrNull { it.price }?.name ?: ""
    
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