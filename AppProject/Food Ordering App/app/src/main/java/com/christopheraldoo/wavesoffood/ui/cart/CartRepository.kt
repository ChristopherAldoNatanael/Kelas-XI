package com.christopheraldoo.wavesoffood.ui.cart

import android.util.Log
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.DatabaseReference
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.tasks.await

class CartRepository {
    
    private val TAG = "CartRepository"
    private val database: DatabaseReference = FirebaseDatabase.getInstance().getReference("carts")
    private val auth: FirebaseAuth = FirebaseAuth.getInstance()
    
    init {
        Log.d(TAG, "CartRepository initialized")
    }
    
    /**
     * Mendapatkan user ID yang sedang login
     */
    private fun getCurrentUserId(): String? {
        return auth.currentUser?.uid
    }
    
    /**
     * Mendapatkan email user yang sedang login
     */
    private fun getCurrentUserEmail(): String? {
        return auth.currentUser?.email
    }
    
    /**
     * Mendapatkan semua item keranjang user
     */
    fun getUserCart(): Flow<Result<List<CartItem>>> = callbackFlow {
        val userId = getCurrentUserId()
        if (userId == null) {
            trySend(Result.failure(Exception("User not logged in")))
            return@callbackFlow
        }
        
        Log.d(TAG, "Getting cart for user: $userId")
        
        val cartRef = database.child(userId)
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val cartItems = mutableListOf<CartItem>()
                    
                    if (snapshot.exists()) {
                        for (itemSnapshot in snapshot.children) {
                            try {
                                val cartItem = itemSnapshot.getValue(CartItem::class.java)
                                if (cartItem != null) {
                                    val itemWithId = cartItem.copy(id = itemSnapshot.key ?: "")
                                    cartItems.add(itemWithId)
                                    Log.d(TAG, "Loaded cart item: ${itemWithId.menuName} x${itemWithId.quantity}")
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Error parsing cart item: ${itemSnapshot.key}", e)
                            }
                        }
                    }
                    
                    Log.d(TAG, "Successfully loaded ${cartItems.size} cart items")
                    trySend(Result.success(cartItems))
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error processing cart data", e)
                    trySend(Result.failure(e))
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Firebase cart error: ${error.message}", error.toException())
                trySend(Result.failure(error.toException()))
            }
        }
        
        cartRef.addValueEventListener(listener)
        
        awaitClose {
            cartRef.removeEventListener(listener)
        }
    }
    
    /**
     * Menambahkan item ke keranjang
     */
    suspend fun addToCart(menuId: String, menuName: String, menuImageUrl: String, price: Int, quantity: Int, category: String): Result<Boolean> {
        val userId = getCurrentUserId()
        val userEmail = getCurrentUserEmail()
        
        if (userId == null) {
            return Result.failure(Exception("User not logged in"))
        }
        
        Log.d(TAG, "Adding to cart: $menuName x$quantity for user: $userId")
        
        return try {
            // Check apakah item sudah ada di keranjang
            val existingItem = getCartItemByMenuId(userId, menuId)
            
            if (existingItem != null) {
                // Update quantity jika item sudah ada
                val newQuantity = existingItem.quantity + quantity
                val newTotalPrice = existingItem.price * newQuantity
                
                val updatedItem = existingItem.copy(
                    quantity = newQuantity,
                    totalPrice = newTotalPrice
                )
                
                database.child(userId).child(existingItem.id).setValue(updatedItem).await()
                Log.d(TAG, "Updated existing cart item: ${updatedItem.menuName} x${updatedItem.quantity}")
                
            } else {
                // Buat item baru
                val cartItem = CartItem(
                    id = database.child(userId).push().key ?: "",
                    menuId = menuId,
                    userId = userId,
                    menuName = menuName,
                    menuImageUrl = menuImageUrl,
                    price = price,
                    quantity = quantity,
                    totalPrice = price * quantity,
                    category = category,
                    addedAt = System.currentTimeMillis(),
                    isAvailable = true
                )
                
                database.child(userId).child(cartItem.id).setValue(cartItem).await()
                Log.d(TAG, "Added new cart item: ${cartItem.menuName} x${cartItem.quantity}")
            }
            
            Result.success(true)
            
        } catch (e: Exception) {
            Log.e(TAG, "Error adding to cart", e)
            Result.failure(e)
        }
    }
    
    /**
     * Update quantity item di keranjang
     */
    suspend fun updateCartItemQuantity(cartItemId: String, newQuantity: Int): Result<Boolean> {
        val userId = getCurrentUserId()
        if (userId == null) {
            return Result.failure(Exception("User not logged in"))
        }
        
        Log.d(TAG, "Updating cart item quantity: $cartItemId to $newQuantity")
        
        return try {
            val cartRef = database.child(userId).child(cartItemId)
            val snapshot = cartRef.get().await()
            
            if (snapshot.exists()) {
                val cartItem = snapshot.getValue(CartItem::class.java)
                if (cartItem != null) {
                    val updatedItem = cartItem.copy(
                        quantity = newQuantity,
                        totalPrice = cartItem.price * newQuantity
                    )
                    
                    cartRef.setValue(updatedItem).await()
                    Log.d(TAG, "Updated cart item quantity: ${updatedItem.menuName} x${updatedItem.quantity}")
                    Result.success(true)
                } else {
                    Result.failure(Exception("Failed to parse cart item"))
                }
            } else {
                Result.failure(Exception("Cart item not found"))
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating cart item quantity", e)
            Result.failure(e)
        }
    }
    
    /**
     * Hapus item dari keranjang
     */
    suspend fun removeFromCart(cartItemId: String): Result<Boolean> {
        val userId = getCurrentUserId()
        if (userId == null) {
            return Result.failure(Exception("User not logged in"))
        }
        
        Log.d(TAG, "Removing cart item: $cartItemId")
        
        return try {
            database.child(userId).child(cartItemId).removeValue().await()
            Log.d(TAG, "Removed cart item: $cartItemId")
            Result.success(true)
            
        } catch (e: Exception) {
            Log.e(TAG, "Error removing cart item", e)
            Result.failure(e)
        }
    }
    
    /**
     * Kosongkan keranjang user
     */
    suspend fun clearUserCart(): Result<Boolean> {
        val userId = getCurrentUserId()
        if (userId == null) {
            return Result.failure(Exception("User not logged in"))
        }
        
        Log.d(TAG, "Clearing cart for user: $userId")
        
        return try {
            database.child(userId).removeValue().await()
            Log.d(TAG, "Cleared cart for user: $userId")
            Result.success(true)
            
        } catch (e: Exception) {
            Log.e(TAG, "Error clearing cart", e)
            Result.failure(e)
        }
    }
    
    /**
     * Mendapatkan item keranjang berdasarkan menu ID
     */
    private suspend fun getCartItemByMenuId(userId: String, menuId: String): CartItem? {
        return try {
            val cartRef = database.child(userId)
            val snapshot = cartRef.get().await()
            
            for (itemSnapshot in snapshot.children) {
                val cartItem = itemSnapshot.getValue(CartItem::class.java)
                if (cartItem?.menuId == menuId) {
                    return cartItem.copy(id = itemSnapshot.key ?: "")
                }
            }
            null
            
        } catch (e: Exception) {
            Log.e(TAG, "Error getting cart item by menu ID", e)
            null
        }
    }
    
    /**
     * Mendapatkan total harga keranjang
     */
    suspend fun getCartTotalPrice(): Result<Int> {
        val userId = getCurrentUserId()
        if (userId == null) {
            return Result.failure(Exception("User not logged in"))
        }
        
        return try {
            val cartRef = database.child(userId)
            val snapshot = cartRef.get().await()
            
            var totalPrice = 0
            if (snapshot.exists()) {
                for (itemSnapshot in snapshot.children) {
                    val cartItem = itemSnapshot.getValue(CartItem::class.java)
                    if (cartItem != null) {
                        totalPrice += cartItem.calculateTotalPrice()
                    }
                }
            }
            
            Result.success(totalPrice)
            
        } catch (e: Exception) {
            Log.e(TAG, "Error getting cart total price", e)
            Result.failure(e)
        }
    }
    
    companion object {
        @Volatile
        private var INSTANCE: CartRepository? = null
        
        fun getInstance(): CartRepository {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: CartRepository().also { INSTANCE = it }
            }
        }
    }
} 