package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import retrofit2.Response
import java.io.File
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PetRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "PetRepository"
    }

    /** Extracts a human-readable error message from a failed Retrofit Response. */
    private fun <T> Response<T>.errorMessage(fallback: String): String {
        return try {
            val errJson = errorBody()?.string()
            if (!errJson.isNullOrBlank()) {
                JSONObject(errJson).optString("message", fallback).ifBlank { fallback }
            } else {
                fallback
            }
        } catch (e: Exception) {
            fallback
        }
    }

    private fun logError(operation: String, code: Int, message: String?) {
        Log.e(TAG, "$operation failed: $message (HTTP $code)")
    }

    suspend fun getPets(): Result<List<Pet>> {
        return try {
            val response = apiService.getPets()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                logError("getPets", response.code(), response.body()?.message)
                Result.Error(response.errorMessage("Failed to get pets"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getPets exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getPet(id: Int): Result<Pet> {
        return try {
            val response = apiService.getPet(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val pet = response.body()?.data
                if (pet != null) Result.Success(pet) else Result.Error("Pet not found")
            } else {
                logError("getPet($id)", response.code(), response.body()?.message)
                Result.Error(response.errorMessage("Failed to get pet"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getPet($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun createPet(pet: PetRequest, photoFile: File? = null): Result<Pet> {
        return try {
            if (photoFile != null && photoFile.exists()) {
                val photoPart = MultipartBody.Part.createFormData(
                    "photo", photoFile.name,
                    photoFile.asRequestBody("image/*".toMediaTypeOrNull())
                )
                val nameBody = pet.name.toRequestBody("text/plain".toMediaTypeOrNull())
                val speciesBody = pet.species.toRequestBody("text/plain".toMediaTypeOrNull())
                val breedBody = pet.breed?.toRequestBody("text/plain".toMediaTypeOrNull())
                val genderBody = pet.gender?.toRequestBody("text/plain".toMediaTypeOrNull())
                val dobBody = pet.dateOfBirth?.toRequestBody("text/plain".toMediaTypeOrNull())
                val ageBody = pet.age?.toString()?.toRequestBody("text/plain".toMediaTypeOrNull())
                val weightBody = pet.weight?.toString()?.toRequestBody("text/plain".toMediaTypeOrNull())

                val response = apiService.createPetWithPhoto(
                    nameBody, speciesBody, breedBody, genderBody, dobBody, ageBody, weightBody, photoPart
                )
                if (response.isSuccessful && response.body()?.success == true) {
                    val createdPet = response.body()?.data
                    if (createdPet != null) Result.Success(createdPet) else Result.Error("Failed to create pet")
                } else {
                    logError("createPet", response.code(), response.body()?.message)
                    Result.Error(response.errorMessage("Failed to create pet"))
                }
            } else {
                val response = apiService.createPet(pet)
                if (response.isSuccessful && response.body()?.success == true) {
                    val createdPet = response.body()?.data
                    if (createdPet != null) Result.Success(createdPet) else Result.Error("Failed to create pet")
                } else {
                    logError("createPet", response.code(), response.body()?.message)
                    Result.Error(response.errorMessage("Failed to create pet"))
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "createPet exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun updatePet(id: Int, pet: PetRequest): Result<Pet> {
        return try {
            val response = apiService.updatePet(id, pet)
            if (response.isSuccessful && response.body()?.success == true) {
                val updatedPet = response.body()?.data
                if (updatedPet != null) Result.Success(updatedPet) else Result.Error("Failed to update pet")
            } else {
                logError("updatePet($id)", response.code(), response.body()?.message)
                Result.Error(response.errorMessage("Failed to update pet"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "updatePet($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /**
     * Update pet with photo upload
     */
    suspend fun updatePet(id: Int, pet: PetRequest, photoFile: File?): Result<Pet> {
        return try {
            if (photoFile != null && photoFile.exists()) {
                // First update pet info, then upload photo
                val response = apiService.updatePet(id, pet)
                if (response.isSuccessful && response.body()?.success == true) {
                    val photoResp = apiService.uploadPetPhoto(id, MultipartBody.Part.createFormData(
                        "photo", photoFile.name, photoFile.asRequestBody("image/*".toMediaTypeOrNull())
                    ))
                    if (photoResp.isSuccessful && photoResp.body()?.success == true) {
                        val updatedPet = response.body()?.data
                        if (updatedPet != null) Result.Success(updatedPet) else Result.Error("Failed to update pet")
                    } else {
                        val photoError = photoResp.body()?.message ?: "Failed to upload pet photo"
                        logError("uploadPetPhoto($id)", photoResp.code(), photoResp.body()?.message)
                        Result.Error(photoError)
                    }
                } else {
                    logError("updatePet($id)", response.code(), response.body()?.message)
                    Result.Error(response.errorMessage("Failed to update pet"))
                }
            } else {
                updatePet(id, pet)
            }
        } catch (e: Exception) {
            Log.e(TAG, "updatePet($id) with photo exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun uploadPetPhoto(id: Int, photoFile: File): Result<PhotoUploadResponse> {
        return try {
            val photoPart = MultipartBody.Part.createFormData(
                "photo", photoFile.name,
                photoFile.asRequestBody("image/*".toMediaTypeOrNull())
            )
            val response = apiService.uploadPetPhoto(id, photoPart)
            if (response.isSuccessful) {
                response.body()?.let { Result.Success(it) } ?: Result.Error("Failed to upload photo")
            } else {
                logError("uploadPetPhoto($id)", response.code(), response.body()?.message)
                Result.Error(response.errorMessage("Failed to upload photo"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "uploadPetPhoto($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun deletePet(id: Int): Result<Unit> {
        return try {
            val response = apiService.deletePet(id)
            if (response.isSuccessful) {
                Result.Success(Unit)
            } else {
                logError("deletePet($id)", response.code(), response.body()?.message)
                Result.Error(response.errorMessage("Failed to delete pet"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "deletePet($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }
}
