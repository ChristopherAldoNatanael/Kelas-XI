package com.christopheraldoo.wavesoffood.ui.menu

/**
 * Data class untuk representasi item menu sesuai struktur Firebase Realtime Database
 */
data class MenuItem(
    var id: String = "",
    val name: String = "",
    val price: Int = 0,
    val imageUrl: String = "",
    val isAvailable: Boolean = true,
    val updatedAt: Long = 0L,
    // Tambahan field sesuai dengan data Firebase yang ada
    val adminId: String = "",
    val category: String = "",
    val description: String = "",
    val createdAt: Long = 0L
) {
    /**
     * Format harga ke format rupiah
     */
    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,d", price)}"
    }
    
    /**
     * Format timestamp ke format yang readable
     */
    fun getFormattedUpdateTime(): String {
        val date = java.util.Date(updatedAt)
        val format = java.text.SimpleDateFormat("dd MMM yyyy, HH:mm", java.util.Locale("id"))
        return format.format(date)
    }
    
    /**
     * Format timestamp untuk createdAt
     */
    fun getFormattedCreateTime(): String {
        val date = java.util.Date(createdAt)
        val format = java.text.SimpleDateFormat("dd MMM yyyy", java.util.Locale("id"))
        return format.format(date)
    }
    
    /**
     * Check apakah menu tersedia
     */
    fun isMenuAvailable(): Boolean {
        return isAvailable
    }
    
    /**
     * Get status text untuk menu
     */
    fun getStatusText(): String {
        return if (isAvailable) "Tersedia" else "Tidak tersedia"
    }
    
    /**
     * Get display name (prioritaskan name, fallback ke description)
     */
    fun getDisplayName(): String {
        return when {
            name.isNotEmpty() -> name
            description.isNotEmpty() -> description.take(40) + "..."
            else -> "Menu ${category.ifEmpty { "Makanan" }}"
        }
    }
    
    /**
     * Get display description (clean dan menarik)
     */
    fun getDisplayDescription(): String {
        return when {
            description.isNotEmpty() -> description
            name.isNotEmpty() -> "Menu ${category.ifEmpty { "lezat" }} yang nikmat"
            else -> "Menu ${category.ifEmpty { "makanan" }} pilihan"
        }
    }
    
    /**
     * Get category display text dengan emoji yang menarik
     */
    fun getCategoryDisplay(): String {
        return when (category.lowercase()) {
            "main_course" -> "🍽️ Main Course"
            "appetizer" -> "🥗 Appetizer"
            "dessert" -> "🍰 Dessert"
            "beverage" -> "🥤 Beverage"
            "snack" -> "🍿 Snack"
            "breakfast" -> "🥐 Breakfast"
            "lunch" -> "🍱 Lunch"
            "dinner" -> "🍽️ Dinner"
            else -> "🍴 ${category.ifEmpty { "Other" }}"
        }
    }
    
    /**
     * Get category badge text (tanpa emoji, untuk badge)
     */
    fun getCategoryBadgeText(): String {
        return when (category.lowercase()) {
            "main_course" -> "Main Course"
            "appetizer" -> "Appetizer"
            "dessert" -> "Dessert"
            "beverage" -> "Beverage"
            "snack" -> "Snack"
            "breakfast" -> "Breakfast"
            "lunch" -> "Lunch"
            "dinner" -> "Dinner"
            else -> category.ifEmpty { "Other" }
        }
    }
    
    /**
     * Get category color untuk badge
     */
    fun getCategoryColor(): Int {
        return when (category.lowercase()) {
            "main_course" -> android.graphics.Color.parseColor("#FF6B35") // Orange
            "appetizer" -> android.graphics.Color.parseColor("#4ECDC4") // Teal
            "dessert" -> android.graphics.Color.parseColor("#FFE66D") // Yellow
            "beverage" -> android.graphics.Color.parseColor("#45B7D1") // Blue
            "snack" -> android.graphics.Color.parseColor("#96CEB4") // Green
            "breakfast" -> android.graphics.Color.parseColor("#FFB6C1") // Pink
            "lunch" -> android.graphics.Color.parseColor("#DDA0DD") // Plum
            "dinner" -> android.graphics.Color.parseColor("#F0E68C") // Khaki
            else -> android.graphics.Color.parseColor("#808080") // Gray
        }
    }
    
    /**
     * Get short description untuk card (max 80 chars)
     */
    fun getShortDescription(): String {
        return if (description.isNotEmpty()) {
            if (description.length > 80) description.take(80) + "..." else description
        } else {
            "Menu ${category.ifEmpty { "lezat" }} yang nikmat dan menggugah selera"
        }
    }
} 