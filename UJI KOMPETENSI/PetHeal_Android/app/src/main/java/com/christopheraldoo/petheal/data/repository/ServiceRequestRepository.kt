package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.ServiceRequest
import com.christopheraldoo.petheal.data.model.ServiceRequestCreateRequest
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import org.json.JSONObject
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Permintaan layanan (kontrol / layanan di luar katalog).
 *
 * Server adalah satu-satunya sumber status dan harga; repo ini tidak
 * menyimpan cache keputusan agar UI tidak menampilkan penawaran yang sudah
 * kedaluwarsa.
 */
@Singleton
class ServiceRequestRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "ServiceRequestRepo"
    }

    private fun errorMessage(response: Response<*>): String {
        val raw = runCatching { response.errorBody()?.string() }.getOrNull()
        val serverMessage = raw
            ?.let { runCatching { JSONObject(it).optString("message") }.getOrNull() }
            ?.takeIf { it.isNotBlank() }

        return serverMessage ?: when (response.code()) {
            401 -> "Sesi berakhir. Silakan masuk kembali."
            403 -> "Akses ditolak."
            404 -> "Permintaan layanan tidak ditemukan."
            422 -> "Data tidak valid. Periksa kembali isian Anda."
            500 -> "Server sedang bermasalah. Coba lagi nanti."
            else -> "Gagal memproses permintaan."
        }
    }

    suspend fun getServiceRequests(): Result<List<ServiceRequest>> {
        return try {
            val response = apiService.getServiceRequests()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Result.Error(errorMessage(response))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getServiceRequests exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getServiceRequest(id: Int): Result<ServiceRequest> {
        return try {
            val response = apiService.getServiceRequest(id)
            val data = response.body()?.data
            if (response.isSuccessful && response.body()?.success == true && data != null) {
                Result.Success(data)
            } else {
                Result.Error(errorMessage(response))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getServiceRequest($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun createServiceRequest(request: ServiceRequestCreateRequest): Result<ServiceRequest> {
        return try {
            val response = apiService.createServiceRequest(request)
            val data = response.body()?.data
            if (response.isSuccessful && response.body()?.success == true && data != null) {
                Result.Success(data)
            } else {
                Result.Error(errorMessage(response))
            }
        } catch (e: Exception) {
            Log.e(TAG, "createServiceRequest exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun acceptOffer(id: Int, note: String?): Result<ServiceRequest> {
        return respond(id, note) { apiService.acceptServiceRequest(id, it) }
    }

    suspend fun rejectOffer(id: Int, note: String?): Result<ServiceRequest> {
        return respond(id, note) { apiService.rejectServiceRequest(id, it) }
    }

    private suspend fun respond(
        id: Int,
        note: String?,
        call: suspend (Map<String, String>) -> Response<com.christopheraldoo.petheal.data.model.ServiceRequestResponse>
    ): Result<ServiceRequest> {
        return try {
            val body = buildMap { note?.takeIf { it.isNotBlank() }?.let { put("note", it) } }
            val response = call(body)
            val data = response.body()?.data
            if (response.isSuccessful && response.body()?.success == true && data != null) {
                Result.Success(data)
            } else {
                Result.Error(errorMessage(response))
            }
        } catch (e: Exception) {
            Log.e(TAG, "respond($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
}
