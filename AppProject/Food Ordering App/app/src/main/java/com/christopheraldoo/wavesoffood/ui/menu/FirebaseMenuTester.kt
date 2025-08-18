package com.christopheraldoo.wavesoffood.ui.menu

import android.util.Log
import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.DatabaseReference
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener

/**
 * Class untuk testing dan debugging koneksi Firebase
 */
class FirebaseMenuTester {
    
    private val TAG = "FirebaseMenuTester"
    // Fix: Gunakan path "menus" bukan "menu" sesuai dengan Firebase
    private val database: DatabaseReference = FirebaseDatabase.getInstance().getReference("menus")
    
    /**
     * Test koneksi Firebase dan lihat struktur data
     */
    fun testFirebaseConnection() {
        Log.d(TAG, "Testing Firebase connection...")
        
        database.addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "Firebase connection successful!")
                Log.d(TAG, "Database path: ${database.key}")
                Log.d(TAG, "Total children: ${snapshot.childrenCount}")
                
                if (snapshot.exists()) {
                    Log.d(TAG, "Data exists in database")
                    
                    // Log setiap item untuk debugging
                    for (menuSnapshot in snapshot.children) {
                        val key = menuSnapshot.key
                        val value = menuSnapshot.value
                        Log.d(TAG, "Key: $key, Value: $value")
                        
                        // Coba parse sebagai MenuItem
                        try {
                            val menu = parseMenuItemFromSnapshot(menuSnapshot)
                            if (menu != null) {
                                Log.d(TAG, "Successfully parsed menu: ${menu.name}")
                            } else {
                                Log.w(TAG, "Failed to parse menu for key: $key")
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error parsing menu item for key: $key", e)
                        }
                    }
                } else {
                    Log.w(TAG, "No data found in database")
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Firebase connection failed: ${error.message}", error.toException())
            }
        })
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
     * Test parsing data untuk satu item menu
     */
    fun testMenuItemParsing(menuId: String) {
        Log.d(TAG, "Testing menu item parsing for ID: $menuId")
        
        database.child(menuId).addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                if (snapshot.exists()) {
                    Log.d(TAG, "Found menu item with key: ${snapshot.key}")
                    Log.d(TAG, "Raw value: ${snapshot.value}")
                    
                    try {
                        val menu = parseMenuItemFromSnapshot(snapshot)
                        if (menu != null) {
                            Log.d(TAG, "Successfully parsed menu item:")
                            Log.d(TAG, "  ID: ${menu.id}")
                            Log.d(TAG, "  Name: ${menu.name}")
                            Log.d(TAG, "  Price: ${menu.price}")
                            Log.d(TAG, "  Image URL: ${menu.imageUrl}")
                            Log.d(TAG, "  Is Available: ${menu.isAvailable}")
                            Log.d(TAG, "  Updated At: ${menu.updatedAt}")
                        } else {
                            Log.w(TAG, "Failed to parse menu item")
                        }
                    } catch (e: Exception) {
                        Log.e(TAG, "Error parsing menu item", e)
                    }
                } else {
                    Log.w(TAG, "Menu item not found")
                }
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Error accessing menu item: ${error.message}", error.toException())
            }
        })
    }
    
    /**
     * Test query berdasarkan field tertentu
     */
    fun testFieldQueries() {
        Log.d(TAG, "Testing field queries...")
        
        // Test query by description (karena tidak ada field name)
        database.orderByChild("description").addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "Query by description successful, found ${snapshot.childrenCount} items")
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Query by description failed: ${error.message}")
            }
        })
        
        // Test query by category
        database.orderByChild("category").addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "Query by category successful, found ${snapshot.childrenCount} items")
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Query by category failed: ${error.message}")
            }
        })
        
        // Test query by adminId
        database.orderByChild("adminId").addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "Query by adminId successful, found ${snapshot.childrenCount} items")
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Query by adminId failed: ${error.message}")
            }
        })
    }
    
    /**
     * Test real-time updates
     */
    fun testRealtimeUpdates() {
        Log.d(TAG, "Testing real-time updates...")
        
        database.addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                Log.d(TAG, "Real-time update received at ${System.currentTimeMillis()}")
                Log.d(TAG, "Total items: ${snapshot.childrenCount}")
            }
            
            override fun onCancelled(error: DatabaseError) {
                Log.e(TAG, "Real-time update failed: ${error.message}")
            }
        })
    }
    
    companion object {
        @Volatile
        private var INSTANCE: FirebaseMenuTester? = null
        
        fun getInstance(): FirebaseMenuTester {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: FirebaseMenuTester().also { INSTANCE = it }
            }
        }
    }
} 