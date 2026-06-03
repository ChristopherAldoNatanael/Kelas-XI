package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.model.PaymentMethod
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PaymentMethodRepository @Inject constructor(
    private val apiService: ApiService
) {
    suspend fun getPaymentMethods(): Result<List<PaymentMethod>> {
        return try {
            // Public endpoint - no auth required
            val response = apiService.getPaymentMethods()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Result.Error(response.body()?.message ?: "Failed to load payment methods")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }
}
