package com.christopheraldoo.adminwafeoffood.menu.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize

/**
 * Data class untuk Menu sesuai struktur Firebase
 */
@Parcelize
data class Menu(
    val id: String = "",
    val name: String = "",
    val category: String = "",
    val description: String = "",
    val price: Int = 0,
    val imageURL: String = "",
    val adminId: String = "admin_001",
    val isAvailable: Boolean = true,
    val createdAt: Long = 0L,
    val updatedAt: Long = 0L
) : Parcelable {
    
    /**
     * Convert to Map untuk Firebase
     */
    fun toMap(): Map<String, Any> {
        return mapOf(
            "id" to id,
            "name" to name,
            "category" to category,
            "description" to description,
            "price" to price,
            "imageURL" to imageURL,
            "adminId" to adminId,
            "isAvailable" to isAvailable,
            "createdAt" to createdAt,
            "updatedAt" to updatedAt
        )
    }
    
    /**
     * Format harga ke format rupiah
     */
    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,d", price)}"
    }
    
    /**
     * Validate data sebelum save
     */
    fun isValid(): Boolean {
        return name.isNotBlank() && 
               category.isNotBlank() && 
               description.isNotBlank() && 
               price > 0
    }
    
    companion object {
        /**
         * Create Menu from Firebase Map
         */
        fun fromMap(map: Map<String, Any>, id: String): Menu {
            return Menu(
                id = map["id"] as? String ?: id,
                name = map["name"] as? String ?: "",
                category = map["category"] as? String ?: "",
                description = map["description"] as? String ?: "",
                price = (map["price"] as? Number)?.toInt() ?: 0,
                imageURL = map["imageURL"] as? String ?: "",
                adminId = map["adminId"] as? String ?: "admin_001",
                isAvailable = map["isAvailable"] as? Boolean ?: true,
                createdAt = (map["createdAt"] as? Number)?.toLong() ?: 0L,
                updatedAt = (map["updatedAt"] as? Number)?.toLong() ?: 0L
            )
        }
    }
}
