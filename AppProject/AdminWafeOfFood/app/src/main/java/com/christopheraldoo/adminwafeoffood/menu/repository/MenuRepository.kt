package com.christopheraldoo.adminwafeoffood.menu.repository

import android.util.Log
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.model.MenuOperationResponse
import com.christopheraldoo.adminwafeoffood.menu.model.MenuOperationResult
import com.google.firebase.database.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.channels.awaitClose  // TAMBAHKAN INI
import kotlin.coroutines.resume

class MenuRepository {
    // GANTI INI - gunakan URL Asia Southeast
    private val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app")
    private val menuRef = database.getReference("menus")
    
    companion object {
        private const val TAG = "MenuRepository"
    }

    // Get all menus as Flow
    fun getAllMenus(): Flow<List<MenuItem>> = callbackFlow {
        Log.d(TAG, "Setting up Firebase listener for menus")
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    Log.d(TAG, "Firebase onDataChange - children count: ${snapshot.childrenCount}")
                    
                    val menuList = mutableListOf<MenuItem>()
                    for (childSnapshot in snapshot.children) {
                        try {
                            val menuData = childSnapshot.value as? Map<String, Any>
                            menuData?.let { data ->
                                val menuItem = MenuItem.fromMap(data, childSnapshot.key ?: "")
                                menuItem?.let { 
                                    menuList.add(it)
                                    Log.d(TAG, "Parsed menu: ${it.name}")
                            }
                        }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing individual menu item", e)
                        }
                    }
                    
                    val sortedList = menuList.sortedByDescending { it.createdAt }
                    Log.d(TAG, "Emitting ${sortedList.size} menus")
                    
                    // Emit data
                    trySend(sortedList).isSuccess
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in onDataChange", e)
                    trySend(emptyList<MenuItem>()).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Firebase listener cancelled: ${error.message}")
                close(error.toException())
            }
        }

        try {
            menuRef.addValueEventListener(listener)
            Log.d(TAG, "Firebase listener added successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error adding Firebase listener", e)
            close(e)
        }
        
        awaitClose { 
            Log.d(TAG, "Removing Firebase listener")
            menuRef.removeEventListener(listener)
        }
    }

    // Add new menu
    suspend fun addMenu(menuItem: MenuItem): MenuOperationResponse {
        return try {
            Log.d(TAG, "Adding new menu: ${menuItem.name}")
            
            val menuId = menuRef.push().key ?: return MenuOperationResponse(
                result = MenuOperationResult.ERROR,
                message = "Gagal generate ID menu"
            )
            
            val currentTime = System.currentTimeMillis()
            val menuToSave = menuItem.copy(
                id = menuId,
                createdAt = currentTime,
                updatedAt = currentTime
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                // Gunakan toMap() untuk save ke Firebase
                menuRef.child(menuId).setValue(menuToSave.toMap())
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to add menu", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                Log.d(TAG, "Menu added successfully with ID: $menuId")
                MenuOperationResponse(
                    result = MenuOperationResult.SUCCESS,
                    message = "Menu berhasil ditambahkan",
                    data = menuToSave
                )
            } else {
                MenuOperationResponse(
                    result = MenuOperationResult.ERROR,
                    message = "Gagal menyimpan menu ke database"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error adding menu", e)
            MenuOperationResponse(
                result = MenuOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Update menu
    suspend fun updateMenu(menuItem: MenuItem): MenuOperationResponse {
        return try {
            Log.d(TAG, "Updating menu: ${menuItem.name}")
            
            if (menuItem.id.isEmpty()) {
                return MenuOperationResponse(
                    result = MenuOperationResult.ERROR,
                    message = "ID menu tidak valid"
                )
            }
            
            val menuToUpdate = menuItem.copy(updatedAt = System.currentTimeMillis())
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                // Gunakan toMap() untuk update ke Firebase
                menuRef.child(menuItem.id).setValue(menuToUpdate.toMap())
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to update menu", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                Log.d(TAG, "Menu updated successfully: ${menuItem.id}")
                MenuOperationResponse(
                    result = MenuOperationResult.SUCCESS,
                    message = "Menu berhasil diupdate",
                    data = menuToUpdate
                )
            } else {
                MenuOperationResponse(
                    result = MenuOperationResult.ERROR,
                    message = "Gagal mengupdate menu"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating menu", e)
            MenuOperationResponse(
                result = MenuOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Delete menu
    suspend fun deleteMenu(id: String): MenuOperationResponse {
        return try {
            Log.d(TAG, "Deleting menu: $id")
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                menuRef.child(id).removeValue()
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to delete menu", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                Log.d(TAG, "Menu deleted successfully: $id")
                MenuOperationResponse(
                    result = MenuOperationResult.SUCCESS,
                    message = "Menu berhasil dihapus"
                )
            } else {
                MenuOperationResponse(
                    result = MenuOperationResult.ERROR,
                    message = "Gagal menghapus menu"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error deleting menu", e)
            MenuOperationResponse(
                result = MenuOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Update availability
    suspend fun updateMenuAvailability(id: String, isAvailable: Boolean): MenuOperationResponse {
        return try {
            Log.d(TAG, "Updating menu availability: $id -> $isAvailable")
            
            val updates = mapOf(
                "isAvailable" to isAvailable,
                "updatedAt" to System.currentTimeMillis()
            )
            
            val success = suspendCancellableCoroutine<Boolean> { continuation ->
                menuRef.child(id).updateChildren(updates)
                    .addOnSuccessListener { 
                        continuation.resume(true)
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "Failed to update availability", error)
                        continuation.resume(false)
                    }
            }
            
            if (success) {
                MenuOperationResponse(
                    result = MenuOperationResult.SUCCESS,
                    message = "Status menu berhasil diupdate"
                )
            } else {
                MenuOperationResponse(
                    result = MenuOperationResult.ERROR,
                    message = "Gagal mengupdate status"
                )
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating availability", e)
            MenuOperationResponse(
                result = MenuOperationResult.ERROR,
                message = "Error: ${e.message}"
            )
        }
    }

    // Get menu by ID
    suspend fun getMenuById(id: String): MenuItem? {
        return try {
            suspendCancellableCoroutine { continuation ->
                menuRef.child(id).addListenerForSingleValueEvent(object : ValueEventListener {
                    override fun onDataChange(snapshot: DataSnapshot) {
                        try {
                            val menuData = snapshot.value as? Map<String, Any>
                            if (menuData != null) {
                                val menuItem = MenuItem.fromMap(menuData, snapshot.key ?: "")
                                continuation.resume(menuItem)
                            } else {
                                continuation.resume(null)
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu by ID", e)
                            continuation.resume(null)
                        }
                    }
                    
                    override fun onCancelled(error: DatabaseError) {
                        Log.e(TAG, "Error getting menu by ID", error.toException())
                        continuation.resume(null)
                    }
                })
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error in getMenuById", e)
            null
        }
    }
}