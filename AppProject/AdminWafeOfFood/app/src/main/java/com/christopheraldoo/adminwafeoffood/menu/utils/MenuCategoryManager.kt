package com.christopheraldoo.adminwafeoffood.menu.utils

import com.christopheraldoo.adminwafeoffood.menu.model.DefaultMenuCategories
import com.christopheraldoo.adminwafeoffood.menu.model.MenuCategory

/**
 * Utility class for managing menu categories
 */
object MenuCategoryManager {
    
    /**
     * Get all available categories
     */
    fun getCategories(): List<MenuCategory> {
        return DefaultMenuCategories.getCategories()
    }
    
    /**
     * Get category display names for spinner
     */
    fun getCategoryDisplayNames(): List<String> {
        return DefaultMenuCategories.getCategoryDisplayNames()
    }
    
    /**
     * Find category by name
     */
    fun getCategoryByName(name: String): MenuCategory? {
        return DefaultMenuCategories.getCategoryByName(name)
    }
    
    /**
     * Get category names (not display names)
     */
    fun getCategoryNames(): List<String> {
        return getCategories().map { it.name }
    }
}
