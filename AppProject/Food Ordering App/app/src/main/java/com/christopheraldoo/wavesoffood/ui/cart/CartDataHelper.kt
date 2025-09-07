package com.christopheraldoo.wavesoffood.ui.cart

import android.util.Log
import com.google.firebase.database.FirebaseDatabase
import kotlinx.coroutines.tasks.await

/**
 * Helper class untuk menambahkan sample data cart ke Firebase
 * Ini bisa digunakan untuk testing checkout functionality
 */
class CartDataHelper {
    
    private val database = FirebaseDatabase.getInstance()
    private val cartRef = database.getReference("carts")
    
    suspend fun addSampleCartData(userId: String = "user_dummy_id"): Result<Boolean> {
        return try {
            val sampleCartItems = listOf(
                CartItem(
                    id = "cart_item_1",
                    menuId = "menu_1",
                    userId = userId,
                    menuName = "Nasi Gudeg Special",
                    menuImageUrl = "https://example.com/gudeg.jpg",
                    price = 25000,
                    quantity = 2,
                    totalPrice = 50000,
                    category = "Indonesian Food",
                    addedAt = System.currentTimeMillis(),
                    isAvailable = true
                ),
                CartItem(
                    id = "cart_item_2", 
                    menuId = "menu_2",
                    userId = userId,
                    menuName = "Es Teh Manis",
                    menuImageUrl = "https://example.com/es_teh.jpg",
                    price = 8000,
                    quantity = 3,
                    totalPrice = 24000,
                    category = "Beverages",
                    addedAt = System.currentTimeMillis(),
                    isAvailable = true
                ),
                CartItem(
                    id = "cart_item_3",
                    menuId = "menu_3", 
                    userId = userId,
                    menuName = "Sate Ayam",
                    menuImageUrl = "https://example.com/sate.jpg",
                    price = 35000,
                    quantity = 1,
                    totalPrice = 35000,
                    category = "Indonesian Food",
                    addedAt = System.currentTimeMillis(),
                    isAvailable = true
                )
            )
            
            // Add each cart item to Firebase
            for (cartItem in sampleCartItems) {
                cartRef.child(userId).child(cartItem.id).setValue(cartItem).await()
                Log.d("CartDataHelper", "Added cart item: ${cartItem.menuName}")
            }
            
            Log.d("CartDataHelper", "Successfully added ${sampleCartItems.size} sample cart items")
            Result.success(true)
            
        } catch (e: Exception) {
            Log.e("CartDataHelper", "Error adding sample cart data", e)
            Result.failure(e)
        }
    }
    
    suspend fun clearSampleCartData(userId: String = "user_dummy_id"): Result<Boolean> {
        return try {
            cartRef.child(userId).removeValue().await()
            Log.d("CartDataHelper", "Cleared sample cart data for user: $userId")
            Result.success(true)
        } catch (e: Exception) {
            Log.e("CartDataHelper", "Error clearing sample cart data", e)
            Result.failure(e)
        }
    }
}
