package com.christopheraldoo.wavesoffood.interfaces

/**
 * Interface untuk komunikasi navigasi antara child fragment dan parent fragment
 * Digunakan untuk memastikan navigasi bottom navigation bekerja dengan benar
 */
interface NavigationInterface {
    /**
     * Navigate to search fragment
     */
    fun navigateToSearch()
    
    /**
     * Navigate to menu fragment
     */
    fun navigateToMenu()
    
    /**
     * Navigate to cart fragment
     */
    fun navigateToCart()
    
    /**
     * Navigate to profile fragment
     */
    fun navigateToProfile()
    
    /**
     * Navigate to home fragment
     */
    fun navigateToHome()
}
