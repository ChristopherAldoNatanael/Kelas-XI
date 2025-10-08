package com.christopheraldoo.adminwafeoffood.menu.viewmodel

import android.net.Uri
import android.util.Log
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.model.MenuOperationResult
import com.christopheraldoo.adminwafeoffood.menu.repository.MenuRepository
import com.google.firebase.storage.FirebaseStorage
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import java.util.*

class AddMenuViewModel : ViewModel() {
    
    private val menuRepository = MenuRepository()
    private val storage = FirebaseStorage.getInstance()
    
    // Save state untuk UI
    private val _saveState = MutableLiveData<SaveState>()
    val saveState: LiveData<SaveState> = _saveState
    
    // Image upload state untuk UI
    private val _imageUploadState = MutableLiveData<ImageUploadState>()
    val imageUploadState: LiveData<ImageUploadState> = _imageUploadState
    
    // Current image URL
    private val _imageUrl = MutableLiveData<String>()
    val imageUrl: LiveData<String> = _imageUrl
    
    // Form validation state
    private val _validationError = MutableLiveData<String?>()
    val validationError: LiveData<String?> = _validationError
    
    companion object {
        private const val TAG = "AddMenuViewModel"
    }
    
    /**
     * Save menu dengan validasi lengkap dan error handling
     */
    fun saveMenu(
        name: String,
        description: String,
        priceText: String,
        category: String,
        imageUrl: String = "",
        isAvailable: Boolean = true,
        adminId: String = "admin_001",
        isEditMode: Boolean = false,
        existingMenuId: String = ""
    ) {
        viewModelScope.launch {
            try {
                // Validasi input terlebih dahulu
                if (!validateMenuInput(name, description, priceText)) {
                    return@launch
                }
                
                _saveState.value = SaveState.Loading
                Log.d(TAG, "Starting save menu process...")
                
                val price = priceText.toInt()
                val currentTime = System.currentTimeMillis()
                
                val menuItem = if (isEditMode && existingMenuId.isNotEmpty()) {
                    MenuItem(
                        id = existingMenuId,
                        name = name,
                        description = description,
                        price = price,
                        category = category,
                        imageURL = imageUrl,
                        isAvailable = isAvailable,
                        adminId = adminId,
                        updatedAt = currentTime
                    )
                } else {
                    MenuItem(
                        name = name,
                        description = description,
                        price = price,
                        category = category,
                        imageURL = imageUrl,
                        isAvailable = isAvailable,
                        adminId = adminId,
                        createdAt = currentTime,
                        updatedAt = currentTime
                    )
                }
                
                Log.d(TAG, "Menu data prepared: ${menuItem.name}")
                
                val response = if (isEditMode) {
                    menuRepository.updateMenu(menuItem)
                } else {
                    menuRepository.addMenu(menuItem)
                }
                
                if (response.result == MenuOperationResult.SUCCESS) {
                    Log.d(TAG, "Menu saved successfully: ${response.message}")
                    _saveState.value = SaveState.Success(response.message)
                } else {
                    Log.e(TAG, "Failed to save menu: ${response.message}")
                    _saveState.value = SaveState.Error(response.message)
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error saving menu", e)
                _saveState.value = SaveState.Error("Gagal menyimpan menu: ${e.message}")
            }
        }
    }
    
    /**
     * Upload gambar ke Firebase Storage
     */
    fun uploadImageToFirebase(uri: Uri) {
        viewModelScope.launch {
            var inputStream: java.io.InputStream? = null
            try {
                _imageUploadState.value = ImageUploadState.Loading
                Log.d(TAG, "Starting image upload to Firebase Storage...")

                val appContext = com.google.firebase.FirebaseApp.getInstance().applicationContext
                val resolver = appContext.contentResolver

                // Open InputStream from the content URI (more reliable than putFile for various providers)
                inputStream = resolver.openInputStream(uri)
                    ?: throw IllegalStateException("Tidak bisa membaca file dari perangkat")

                val detectedMime = try {
                    resolver.getType(uri)
                } catch (_: Exception) { null }
                val mimeType = detectedMime ?: "image/jpeg"

                val extensionFromMime = try {
                    android.webkit.MimeTypeMap.getSingleton().getExtensionFromMimeType(mimeType)
                } catch (_: Exception) { null }
                val extension = extensionFromMime
                    ?: (uri.lastPathSegment?.substringAfterLast('.', "jpg") ?: "jpg")

                val fileName = "menu_images/${UUID.randomUUID()}.$extension"
                val imageRef = storage.reference.child(fileName)

                val metadata = com.google.firebase.storage.StorageMetadata.Builder()
                    .setContentType(mimeType)
                    .build()

                val uploadTask = imageRef.putStream(inputStream, metadata)
                uploadTask.addOnProgressListener { taskSnapshot: com.google.firebase.storage.UploadTask.TaskSnapshot ->
                    val total = taskSnapshot.totalByteCount.takeIf { it > 0 } ?: 1
                    val progress = (100.0 * taskSnapshot.bytesTransferred / total).toInt()
                    _imageUploadState.value = ImageUploadState.Progress(progress)
                    Log.d(TAG, "Upload progress: $progress%")
                }

                uploadTask.await()
                val downloadUrl = imageRef.downloadUrl.await()

                val finalUrl = downloadUrl.toString()
                _imageUrl.value = finalUrl
                _imageUploadState.value = ImageUploadState.Success(finalUrl)

                Log.d(TAG, "Image uploaded successfully: $finalUrl")

            } catch (e: com.google.firebase.storage.StorageException) {
                val message = when (e.errorCode) {
                    com.google.firebase.storage.StorageException.ERROR_OBJECT_NOT_FOUND -> "File tidak ditemukan. Coba pilih ulang gambarnya."
                    com.google.firebase.storage.StorageException.ERROR_NOT_AUTHENTICATED -> "Anda belum terautentikasi. Silakan login ulang."
                    com.google.firebase.storage.StorageException.ERROR_NOT_AUTHORIZED -> "Tidak punya izin ke Storage. Cek aturan dan bucket."
                    else -> e.message ?: "Gagal mengupload gambar"
                }
                Log.e(TAG, "StorageException uploading image", e)
                _imageUploadState.value = ImageUploadState.Error(message)
            } catch (se: SecurityException) {
                Log.e(TAG, "SecurityException uploading image", se)
                _imageUploadState.value = ImageUploadState.Error("Akses ke file ditolak. Beri izin galeri atau pilih file lain.")
            } catch (e: Exception) {
                Log.e(TAG, "Error uploading image", e)
                _imageUploadState.value = ImageUploadState.Error(e.message ?: "Gagal mengupload gambar")
            } finally {
                try { inputStream?.close() } catch (_: Exception) {}
            }
        }
    }
    
    fun uploadImageBytes(data: ByteArray, mimeType: String, extension: String) {
        viewModelScope.launch {
            try {
                _imageUploadState.value = ImageUploadState.Loading
                Log.d(TAG, "Starting image upload (bytes) to Firebase Storage...")

                val safeExtension = if (extension.isBlank()) "jpg" else extension
                val fileName = "menu_images/${UUID.randomUUID()}.$safeExtension"
                val imageRef = storage.reference.child(fileName)

                val metadata = com.google.firebase.storage.StorageMetadata.Builder()
                    .setContentType(mimeType.ifBlank { "image/jpeg" })
                    .build()

                val uploadTask = imageRef.putBytes(data, metadata)
                uploadTask.addOnProgressListener { taskSnapshot: com.google.firebase.storage.UploadTask.TaskSnapshot ->
                    val total = taskSnapshot.totalByteCount.takeIf { it > 0 } ?: data.size.toLong().coerceAtLeast(1)
                    val progress = (100.0 * taskSnapshot.bytesTransferred / total).toInt()
                    _imageUploadState.value = ImageUploadState.Progress(progress)
                    Log.d(TAG, "Upload (bytes) progress: $progress%")
                }

                uploadTask.await()
                val downloadUrl = imageRef.downloadUrl.await()

                val finalUrl = downloadUrl.toString()
                _imageUrl.value = finalUrl
                _imageUploadState.value = ImageUploadState.Success(finalUrl)

                Log.d(TAG, "Image (bytes) uploaded successfully: $finalUrl")

            } catch (e: com.google.firebase.storage.StorageException) {
                val message = when (e.errorCode) {
                    com.google.firebase.storage.StorageException.ERROR_OBJECT_NOT_FOUND -> "File tidak ditemukan. Coba pilih ulang gambarnya."
                    com.google.firebase.storage.StorageException.ERROR_NOT_AUTHENTICATED -> "Anda belum terautentikasi. Silakan login ulang."
                    com.google.firebase.storage.StorageException.ERROR_NOT_AUTHORIZED -> "Tidak punya izin ke Storage. Cek aturan dan bucket."
                    else -> e.message ?: "Gagal mengupload gambar"
                }
                Log.e(TAG, "StorageException uploading image (bytes)", e)
                _imageUploadState.value = ImageUploadState.Error(message)
            } catch (e: Exception) {
                Log.e(TAG, "Error uploading image (bytes)", e)
                _imageUploadState.value = ImageUploadState.Error(e.message ?: "Gagal mengupload gambar")
            }
        }
    }
    
    /**
     * Set image URL dari input manual
     */
    fun setImageUrl(url: String) {
        _imageUrl.value = url
        Log.d(TAG, "Image URL set manually: $url")
    }
    
    /**
     * Clear current image URL
     */
    fun clearImageUrl() {
        _imageUrl.value = ""
        _imageUploadState.value = ImageUploadState.Idle
        Log.d(TAG, "Image URL cleared")
    }
    
    /**
     * Validasi input form
     */
    private fun validateMenuInput(
        name: String,
        description: String,
        priceText: String
    ): Boolean {
        return when {
            name.isBlank() -> {
                _validationError.value = "Nama menu harus diisi"
                false
            }
            name.length < 3 -> {
                _validationError.value = "Nama menu minimal 3 karakter"
                false
            }
            description.isBlank() -> {
                _validationError.value = "Deskripsi menu harus diisi"
                false
            }
            description.length < 10 -> {
                _validationError.value = "Deskripsi menu minimal 10 karakter"
                false
            }
            priceText.isBlank() -> {
                _validationError.value = "Harga menu harus diisi"
                false
            }
            priceText.toIntOrNull() == null -> {
                _validationError.value = "Harga harus berupa angka yang valid"
                false
            }
            priceText.toInt() <= 0 -> {
                _validationError.value = "Harga harus lebih dari 0"
                false
            }
            else -> {
                _validationError.value = null
                true
            }
        }
    }
    
    /**
     * Clear validation error
     */
    fun clearValidationError() {
        _validationError.value = null
    }
    
    /**
     * Reset semua state
     */
    fun resetStates() {
        _saveState.value = SaveState.Idle
        _imageUploadState.value = ImageUploadState.Idle
        _imageUrl.value = ""
        _validationError.value = null
        Log.d(TAG, "All states reset")
    }
    
    /**
     * State classes untuk UI
     */
    sealed class SaveState {
        object Idle : SaveState()
        object Loading : SaveState()
        data class Success(val message: String) : SaveState()
        data class Error(val message: String) : SaveState()
    }
    
    sealed class ImageUploadState {
        object Idle : ImageUploadState()
        object Loading : ImageUploadState()
        data class Progress(val progress: Int) : ImageUploadState()
        data class Success(val url: String) : ImageUploadState()
        data class Error(val message: String) : ImageUploadState()
    }
}
