package com.christopheraldoo.wavesoffood.data.helpers

import android.util.Log
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderItem
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.data.model.OrderType
import com.christopheraldoo.wavesoffood.data.model.PaymentMethod
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.FirebaseDatabase
import kotlinx.coroutines.tasks.await

/**
 * Helper class untuk menambahkan sample order data ke Firebase
 * Berguna untuk testing order history functionality
 */
class OrderDataHelper {
    
    private val database = FirebaseDatabase.getInstance()
    private val ordersRef = database.getReference("orders")
    private val userOrdersRef = database.getReference("user_orders")
    private val auth = FirebaseAuth.getInstance()
    
    companion object {
        private const val TAG = "OrderDataHelper"
        
        @Volatile
        private var INSTANCE: OrderDataHelper? = null
        
        fun getInstance(): OrderDataHelper {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: OrderDataHelper().also { INSTANCE = it }
            }
        }
    }
    
    /**
     * Menambahkan sample order data untuk testing
     */
    suspend fun addSampleOrderData(): Result<Boolean> {
        return try {
            val currentUser = auth.currentUser
            if (currentUser == null) {
                Log.e(TAG, "User not authenticated")
                return Result.failure(Exception("User not authenticated"))
            }
            
            val userId = currentUser.uid
            val userEmail = currentUser.email ?: "test@example.com"
            val userName = currentUser.displayName ?: "Test User"
            
            val sampleOrders = createSampleOrders(userId, userEmail, userName)
            
            // Save each order to Firebase
            sampleOrders.forEach { order ->
                // Save to main orders collection
                ordersRef.child(order.id).setValue(order).await()
                // Save to user-specific orders for easy retrieval
                userOrdersRef.child(userId).child(order.id).setValue(order).await()
            }
            
            Log.d(TAG, "Successfully added ${sampleOrders.size} sample orders")
            Result.success(true)
        } catch (e: Exception) {
            Log.e(TAG, "Error adding sample order data", e)
            Result.failure(e)
        }
    }
    
    /**
     * Membuat list sample orders dengan berbagai status
     */
    private fun createSampleOrders(userId: String, userEmail: String, userName: String): List<Order> {
        val currentTime = System.currentTimeMillis()
        
        return listOf(
            // Order 1: Completed order
            Order(
                id = "order_${System.currentTimeMillis()}_1",
                userId = userId,
                userEmail = userEmail,
                userName = userName,
                items = listOf(
                    OrderItem(
                        menuId = "menu_1",
                        menuName = "Nasi Gudeg Special",
                        menuImageUrl = "https://images.unsplash.com/photo-1563379091339-03246963d96c?w=400",
                        price = 25000,
                        quantity = 2,
                        totalPrice = 50000,
                        category = "Indonesian Food"
                    ),
                    OrderItem(
                        menuId = "menu_2",
                        menuName = "Es Teh Manis",
                        menuImageUrl = "https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=400",
                        price = 5000,
                        quantity = 2,
                        totalPrice = 10000,
                        category = "Beverages"
                    )
                ),
                subtotal = 60000,
                discount = 6000, // 10% discount
                deliveryFee = 10000,
                total = 64000,
                orderType = OrderType.TAKE_AWAY,
                paymentMethod = PaymentMethod.QRIS,
                status = OrderStatus.COMPLETED,
                recipientName = userName,
                deliveryAddress = "Jl. Malioboro No. 123, Yogyakarta",
                notes = "Extra sambal please",
                createdAt = currentTime - (3 * 24 * 60 * 60 * 1000), // 3 days ago
                updatedAt = currentTime - (3 * 24 * 60 * 60 * 1000),
                estimatedDeliveryTime = currentTime - (3 * 24 * 60 * 60 * 1000) + (30 * 60 * 1000),
                completedAt = currentTime - (3 * 24 * 60 * 60 * 1000) + (25 * 60 * 1000)
            ),
            
            // Order 2: In progress order
            Order(
                id = "order_${System.currentTimeMillis()}_2",
                userId = userId,
                userEmail = userEmail,
                userName = userName,
                items = listOf(
                    OrderItem(
                        menuId = "menu_3",
                        menuName = "Sate Ayam Madura",
                        menuImageUrl = "https://images.unsplash.com/photo-1529563021893-cc83c992d75d?w=400",
                        price = 35000,
                        quantity = 1,
                        totalPrice = 35000,
                        category = "Grilled"
                    ),
                    OrderItem(
                        menuId = "menu_4",
                        menuName = "Nasi Putih",
                        menuImageUrl = "https://images.unsplash.com/photo-1596797038530-2c107229654b?w=400",
                        price = 8000,
                        quantity = 1,
                        totalPrice = 8000,
                        category = "Rice"
                    )
                ),
                subtotal = 43000,
                discount = 0,
                deliveryFee = 10000,
                total = 53000,
                orderType = OrderType.DINE_IN,
                paymentMethod = PaymentMethod.CASH,
                status = OrderStatus.PREPARING,
                recipientName = userName,
                deliveryAddress = "Jl. Sultan Agung No. 456, Yogyakarta",
                notes = "Medium spicy level",
                createdAt = currentTime - (2 * 60 * 60 * 1000), // 2 hours ago
                updatedAt = currentTime - (30 * 60 * 1000), // Updated 30 minutes ago
                estimatedDeliveryTime = currentTime + (15 * 60 * 1000), // 15 minutes from now
                completedAt = 0L
            ),
            
            // Order 3: Recent pending order
            Order(
                id = "order_${System.currentTimeMillis()}_3",
                userId = userId,
                userEmail = userEmail,
                userName = userName,
                items = listOf(
                    OrderItem(
                        menuId = "menu_5",
                        menuName = "Gado-gado Jakarta",
                        menuImageUrl = "https://images.unsplash.com/photo-1604329760661-e71dc83f8385?w=400",
                        price = 20000,
                        quantity = 1,
                        totalPrice = 20000,
                        category = "Salad"
                    ),
                    OrderItem(
                        menuId = "menu_6",
                        menuName = "Kerupuk Udang",
                        menuImageUrl = "https://images.unsplash.com/photo-1625938146369-ddd4b6d0f21d?w=400",
                        price = 3000,
                        quantity = 3,
                        totalPrice = 9000,
                        category = "Snacks"
                    )
                ),
                subtotal = 29000,
                discount = 0,
                deliveryFee = 10000,
                total = 39000,
                orderType = OrderType.TAKE_AWAY,
                paymentMethod = PaymentMethod.BANK_TRANSFER,
                status = OrderStatus.PENDING,
                recipientName = userName,
                deliveryAddress = "Jl. Prawirotaman No. 789, Yogyakarta",
                notes = "Please call when arrived",
                createdAt = currentTime - (15 * 60 * 1000), // 15 minutes ago
                updatedAt = currentTime - (15 * 60 * 1000),
                estimatedDeliveryTime = currentTime + (30 * 60 * 1000), // 30 minutes from now
                completedAt = 0L
            ),
            
            // Order 4: Delivered order from yesterday
            Order(
                id = "order_${System.currentTimeMillis()}_4",
                userId = userId,
                userEmail = userEmail,
                userName = userName,
                items = listOf(
                    OrderItem(
                        menuId = "menu_7",
                        menuName = "Rendang Daging Sapi",
                        menuImageUrl = "https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400",
                        price = 45000,
                        quantity = 1,
                        totalPrice = 45000,
                        category = "Indonesian Food"
                    ),
                    OrderItem(
                        menuId = "menu_8",
                        menuName = "Nasi Putih",
                        menuImageUrl = "https://images.unsplash.com/photo-1596797038530-2c107229654b?w=400",
                        price = 8000,
                        quantity = 2,
                        totalPrice = 16000,
                        category = "Rice"
                    ),
                    OrderItem(
                        menuId = "menu_9",
                        menuName = "Es Jeruk Segar",
                        menuImageUrl = "https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=400",
                        price = 12000,
                        quantity = 1,
                        totalPrice = 12000,
                        category = "Beverages"
                    )
                ),
                subtotal = 73000,
                discount = 7300, // 10% discount for order above 70k
                deliveryFee = 10000,
                total = 75700,
                orderType = OrderType.TAKE_AWAY,
                paymentMethod = PaymentMethod.CREDIT_CARD,
                status = OrderStatus.DELIVERING,
                recipientName = userName,
                deliveryAddress = "Jl. Tugu No. 321, Yogyakarta",
                notes = "Extra rice please",
                createdAt = currentTime - (1 * 24 * 60 * 60 * 1000), // 1 day ago
                updatedAt = currentTime - (1 * 24 * 60 * 60 * 1000) + (45 * 60 * 1000),
                estimatedDeliveryTime = currentTime - (1 * 24 * 60 * 60 * 1000) + (40 * 60 * 1000),
                completedAt = currentTime - (1 * 24 * 60 * 60 * 1000) + (42 * 60 * 1000)
            ),
            
            // Order 5: Cancelled order
            Order(
                id = "order_${System.currentTimeMillis()}_5",
                userId = userId,
                userEmail = userEmail,
                userName = userName,
                items = listOf(
                    OrderItem(
                        menuId = "menu_10",
                        menuName = "Pizza Margherita",
                        menuImageUrl = "https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400",
                        price = 55000,
                        quantity = 1,
                        totalPrice = 55000,
                        category = "Italian"
                    )
                ),
                subtotal = 55000,
                discount = 0,
                deliveryFee = 10000,
                total = 65000,
                orderType = OrderType.TAKE_AWAY,
                paymentMethod = PaymentMethod.QRIS,
                status = OrderStatus.CANCELLED,
                recipientName = userName,
                deliveryAddress = "Jl. Kaliurang No. 654, Yogyakarta",
                notes = "Changed my mind",
                createdAt = currentTime - (5 * 24 * 60 * 60 * 1000), // 5 days ago
                updatedAt = currentTime - (5 * 24 * 60 * 60 * 1000) + (10 * 60 * 1000),
                estimatedDeliveryTime = currentTime - (5 * 24 * 60 * 60 * 1000) + (30 * 60 * 1000),
                completedAt = 0L
            )
        )
    }
    
    /**
     * Menghapus semua sample order data
     */
    suspend fun clearSampleOrderData(): Result<Boolean> {
        return try {
            val currentUser = auth.currentUser
            if (currentUser == null) {
                return Result.failure(Exception("User not authenticated"))
            }
            
            userOrdersRef.child(currentUser.uid).removeValue().await()
            Log.d(TAG, "Cleared sample order data for user: ${currentUser.uid}")
            Result.success(true)
        } catch (e: Exception) {
            Log.e(TAG, "Error clearing sample order data", e)
            Result.failure(e)
        }
    }
}
