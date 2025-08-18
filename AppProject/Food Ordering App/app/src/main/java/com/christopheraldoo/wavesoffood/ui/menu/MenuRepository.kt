package com.christopheraldoo.wavesoffood.ui.menu

import android.util.Log
import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.DatabaseReference
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.tasks.await

class MenuRepository {
    
    private val TAG = "MenuRepository"
    // Fix: Gunakan path "menus" bukan "menu" sesuai dengan Firebase
    private val database: DatabaseReference = FirebaseDatabase.getInstance().getReference("menus")
    
    init {
        Log.d(TAG, "MenuRepository initialized with database path: ${database.key}")
        Log.d(TAG, "Database URL: ${FirebaseDatabase.getInstance().reference}")
        
        // Test connection immediately
        testConnection()
    }
    
    /**
     * Test koneksi Firebase untuk debugging
     */
    private fun testConnection() {
        Log.d(TAG, "Testing Firebase connection...")
        
        database.addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "=== FIREBASE CONNECTION TEST ===")
                Log.d(TAG, "Database path: ${database.key}")
                Log.d(TAG, "Snapshot exists: ${snapshot.exists()}")
                Log.d(TAG, "Total children: ${snapshot.childrenCount}")
                
                if (snapshot.exists()) {
                    Log.d(TAG, "✅ Data exists in database")
                    
                    // Log setiap item untuk debugging
                    for (menuSnapshot in snapshot.children) {
                        val key = menuSnapshot.key
                        val value = menuSnapshot.value
                        Log.d(TAG, "📋 Key: $key")
                        Log.d(TAG, "📋 Raw Value: $value")
                        
                        // Coba parse sebagai MenuItem
                        try {
                            val menu = menuSnapshot.getValue(MenuItem::class.java)
                            if (menu != null) {
                                Log.d(TAG, "✅ Successfully parsed menu: ${menu.name}")
                                Log.d(TAG, "   - ID: ${menu.id}")
                                Log.d(TAG, "   - Name: ${menu.name}")
                                Log.d(TAG, "   - Price: ${menu.price}")
                                Log.d(TAG, "   - Available: ${menu.isAvailable}")
                            } else {
                                Log.w(TAG, "❌ Failed to parse menu for key: $key")
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "❌ Error parsing menu for key: $key", e)
                        }
                    }
                } else {
                    Log.w(TAG, "❌ No data found in database")
                }
                Log.d(TAG, "=== END CONNECTION TEST ===")
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "❌ Firebase connection failed: ${error.message}", error.toException())
                Log.e(TAG, "Error code: ${error.code}")
                Log.e(TAG, "Error details: ${error.details}")
            }
        })
    }
    
    /**
     * Mendapatkan semua menu dari Firebase
     */
    fun getAllMenus(): Flow<Result<List<MenuItem>>> = callbackFlow {
        Log.d(TAG, "getAllMenus() called")
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "=== getAllMenus onDataChange ===")
                Log.d(TAG, "Snapshot exists: ${snapshot.exists()}")
                Log.d(TAG, "Total children: ${snapshot.childrenCount}")
                
                try {
                    val menuList = mutableListOf<MenuItem>()
                    
                    if (!snapshot.exists()) {
                        Log.w(TAG, "❌ No menu data found in Firebase")
                        trySend(Result.success(emptyList()))
                        return
                    }
                    
                    Log.d(TAG, "✅ Data exists, processing ${snapshot.childrenCount} items")
                    
                    for (menuSnapshot in snapshot.children) {
                        val key = menuSnapshot.key
                        Log.d(TAG, "Processing menu with key: $key")
                        
                        try {
                            // Parse data sesuai dengan struktur Firebase yang ada
                            val menu = parseMenuItemFromSnapshot(menuSnapshot)
                            if (menu != null) {
                                menu.id = key ?: ""
                                menuList.add(menu)
                                Log.d(TAG, "✅ Successfully loaded menu: ${menu.name} with ID: ${menu.id}")
                            } else {
                                Log.w(TAG, "❌ Failed to parse menu for key: $key")
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "❌ Error parsing menu item for key: $key", e)
                        }
                    }
                    
                    Log.d(TAG, "✅ Successfully loaded ${menuList.size} menu items")
                    Log.d(TAG, "Menu names: ${menuList.map { it.name }}")
                    trySend(Result.success(menuList))
                    
                } catch (e: Exception) {
                    Log.e(TAG, "❌ Error processing menu data", e)
                    trySend(Result.failure(e))
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "❌ Firebase database error: ${error.message}", error.toException())
                Log.e(TAG, "Error code: ${error.code}")
                Log.e(TAG, "Error details: ${error.details}")
                trySend(Result.failure(error.toException()))
            }
        }
        
        Log.d(TAG, "Adding ValueEventListener to database")
        database.addValueEventListener(listener)
        
        awaitClose {
            Log.d(TAG, "Removing ValueEventListener")
            database.removeEventListener(listener)
        }
    }
    
    /**
     * Parse MenuItem dari DataSnapshot sesuai struktur Firebase yang ada
     */
    private fun parseMenuItemFromSnapshot(snapshot: DataSnapshot): MenuItem? {
        return try {
            // Ambil data sesuai dengan struktur yang ada di Firebase
            val adminId = snapshot.child("adminId").getValue(String::class.java) ?: ""
            val category = snapshot.child("category").getValue(String::class.java) ?: ""
            val createdAt = snapshot.child("createdAt").getValue(Long::class.java) ?: 0L
            val description = snapshot.child("description").getValue(String::class.java) ?: ""
            val name = snapshot.child("name").getValue(String::class.java) ?: ""
            val price = snapshot.child("price").getValue(Int::class.java) ?: 25000
            val imageUrl = snapshot.child("imageUrl").getValue(String::class.java) ?: ""
            val isAvailable = snapshot.child("isAvailable").getValue(Boolean::class.java) ?: true
            
            // Buat MenuItem dengan data yang tersedia
            MenuItem(
                id = snapshot.key ?: "",
                name = name, // Gunakan name yang sebenarnya dari Firebase
                price = price, // Gunakan price yang sebenarnya dari Firebase
                imageUrl = imageUrl, // Gunakan imageUrl yang sebenarnya dari Firebase
                isAvailable = isAvailable, // Gunakan isAvailable yang sebenarnya dari Firebase
                updatedAt = createdAt, // Gunakan createdAt sebagai updatedAt
                adminId = adminId,
                category = category,
                description = description,
                createdAt = createdAt
            )
        } catch (e: Exception) {
            Log.e(TAG, "Error parsing MenuItem from snapshot", e)
            null
        }
    }
    
    /**
     * Mencari menu berdasarkan nama
     */
    fun searchMenus(query: String): Flow<Result<List<MenuItem>>> = callbackFlow {
        Log.d(TAG, "searchMenus() called with query: '$query'")
        
        val searchRef = database.orderByChild("description") // Gunakan description untuk search
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val menuList = mutableListOf<MenuItem>()
                    val searchQuery = query.lowercase()
                    
                    for (menuSnapshot in snapshot.children) {
                        try {
                            val menu = parseMenuItemFromSnapshot(menuSnapshot)
                            if (menu != null && menu.name.lowercase().contains(searchQuery)) {
                                menu.id = menuSnapshot.key ?: ""
                                menuList.add(menu)
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu item: ${menuSnapshot.key}", e)
                        }
                    }
                    
                    trySend(Result.success(menuList))
                    
                } catch (e: Exception) {
                    trySend(Result.failure(e))
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                trySend(Result.failure(error.toException()))
            }
        }
        
        searchRef.addValueEventListener(listener)
        
        awaitClose {
            searchRef.removeEventListener(listener)
        }
    }
    
    /**
     * Mendapatkan menu berdasarkan ID
     */
    suspend fun getMenuById(menuId: String): Result<MenuItem?> {
        Log.d(TAG, "getMenuById() called with ID: $menuId")
        
        return try {
            val snapshot = database.child(menuId).get().await()
            if (snapshot.exists()) {
                val menu = parseMenuItemFromSnapshot(snapshot)
                if (menu != null) {
                    menu.id = snapshot.key ?: ""
                    Result.success(menu)
                } else {
                    Result.failure(Exception("Failed to parse menu data"))
                }
            } else {
                Result.success(null)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error getting menu by ID: $menuId", e)
            Result.failure(e)
        }
    }
    
    /**
     * Mendapatkan menu yang tersedia saja
     */
    fun getAvailableMenus(): Flow<Result<List<MenuItem>>> = callbackFlow {
        Log.d(TAG, "getAvailableMenus() called")
        
        // Karena tidak ada field isAvailable, ambil semua menu
        val availableRef = database
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val menuList = mutableListOf<MenuItem>()
                    
                    for (menuSnapshot in snapshot.children) {
                        try {
                            val menu = parseMenuItemFromSnapshot(menuSnapshot)
                            if (menu != null) {
                                menu.id = menuSnapshot.key ?: ""
                                menuList.add(menu)
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu item: ${menuSnapshot.key}", e)
                        }
                    }
                    
                    trySend(Result.success(menuList))
                    
                } catch (e: Exception) {
                    trySend(Result.failure(e))
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                trySend(Result.failure(error.toException()))
            }
        }
        
        availableRef.addValueEventListener(listener)
        
        awaitClose {
            availableRef.removeEventListener(listener)
        }
    }
    
    /**
     * Mendapatkan menu berdasarkan range harga
     */
    fun getMenusByPriceRange(minPrice: Int, maxPrice: Int): Flow<Result<List<MenuItem>>> = callbackFlow {
        Log.d(TAG, "getMenusByPriceRange() called: $minPrice - $maxPrice")
        
        // Karena tidak ada field price, ambil semua menu
        val priceRef = database
        
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                try {
                    val menuList = mutableListOf<MenuItem>()
                    
                    for (menuSnapshot in snapshot.children) {
                        try {
                            val menu = parseMenuItemFromSnapshot(menuSnapshot)
                            if (menu != null) {
                                menu.id = menuSnapshot.key ?: ""
                                menuList.add(menu)
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu item: ${menuSnapshot.key}", e)
                        }
                    }
                    
                    trySend(Result.success(menuList))
                    
                } catch (e: Exception) {
                    trySend(Result.failure(e))
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                trySend(Result.failure(error.toException()))
            }
        }
        
        priceRef.addValueEventListener(listener)
        
        awaitClose {
            priceRef.removeEventListener(listener)
        }
    }
    
    companion object {
        @Volatile
        private var INSTANCE: MenuRepository? = null
        
        fun getInstance(): MenuRepository {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: MenuRepository().also { INSTANCE = it }
            }
        }
    }
} 