package com.christopheraldoo.wavesoffood.ui.cart

/**
 * Data class untuk representasi item dalam keranjang
 */
data class CartItem(
    val id: String = "",
    val menuId: String = "",
    val userId: String = "",
    val menuName: String = "",
    val menuImageUrl: String = "",
    val price: Int = 0,
    val quantity: Int = 1,
    val totalPrice: Int = 0,
    val category: String = "",
    val addedAt: Long = System.currentTimeMillis(),
    val isAvailable: Boolean = true
) {
    /**
     * Hitung total harga berdasarkan quantity
     */
    fun calculateTotalPrice(): Int {
        return price * quantity
    }
    
    /**
     * Format total harga ke format rupiah
     */
    fun getFormattedTotalPrice(): String {
        return "Rp ${String.format("%,d", calculateTotalPrice())}"
    }
    
    /**
     * Format harga satuan ke format rupiah
     */
    fun getFormattedPrice(): String {
        return "Rp ${String.format("%,d", price)}"
    }
    
    /**
     * Check apakah item masih tersedia
     */
    fun isItemAvailable(): Boolean {
        return isAvailable && quantity > 0
    }
    
    /**
     * Get status text untuk item
     */
    fun getStatusText(): String {
        return when {
            !isAvailable -> "Tidak tersedia"
            quantity <= 0 -> "Stok habis"
            else -> "Tersedia"
        }
    }
} 