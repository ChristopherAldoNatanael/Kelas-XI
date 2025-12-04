package com.christopheraldoo.bukuringkasapp.viewmodel

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.data.firebase.FirebaseService
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.data.repository.OpenAIRepository
import com.christopheraldoo.bukuringkasapp.data.repository.OcrService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * ViewModel untuk Scan Screen
 * Mengelola OCR processing dan text summarization
 */
class ScanViewModel : ViewModel() {

    private lateinit var ocrService: OcrService
    private var repository: OpenAIRepository? = null
    private val firebaseService = FirebaseService()
    
    // Initialize repository with context
    fun initialize(context: Context) {
        if (repository == null) {
            repository = OpenAIRepository(context)
        }
    }

    // State untuk UI
    private val _extractedText = MutableStateFlow("")
    val extractedText: StateFlow<String> = _extractedText

    private val _isProcessing = MutableStateFlow(false)
    val isProcessing: StateFlow<Boolean> = _isProcessing

    private val _summaryResult = MutableStateFlow<SummaryData?>(null)
    val summaryResult: StateFlow<SummaryData?> = _summaryResult

    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage

    /**
     * Process gambar dengan OCR
     */
    fun processImageWithOcr(imageUri: android.net.Uri, context: android.content.Context) {
        _isProcessing.value = true
        _errorMessage.value = null

        viewModelScope.launch {
            try {
                val ocrService = OcrService(context)
                val result = ocrService.extractTextFromUri(imageUri)

                result.fold(
                    onSuccess = { text ->
                        _extractedText.value = ocrService.cleanOcrText(text)
                    },
                    onFailure = { error ->
                        _errorMessage.value = "OCR Error: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            } finally {
                _isProcessing.value = false
            }
        }
    }

    /**
     * Process gambar dari Bitmap dengan OCR
     */
    fun processBitmapWithOcr(bitmap: android.graphics.Bitmap, context: android.content.Context) {
        _isProcessing.value = true
        _errorMessage.value = null

        viewModelScope.launch {
            try {
                val ocrService = OcrService(context)
                val result = ocrService.extractTextFromBitmap(bitmap)

                result.fold(
                    onSuccess = { text ->
                        _extractedText.value = ocrService.cleanOcrText(text)
                    },
                    onFailure = { error ->
                        _errorMessage.value = "OCR Error: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            } finally {
                _isProcessing.value = false
            }
        }
    }    /**
     * Generate ringkasan dari teks hasil OCR
     */
    fun generateSummary(text: String, context: Context) {
        if (text.isBlank()) {
            _errorMessage.value = "Teks kosong. Tidak dapat membuat ringkasan."
            return
        }

        // Initialize repository if not already done
        initialize(context)

        _isProcessing.value = true
        _errorMessage.value = null

        viewModelScope.launch {
            try {
                // Check if API key is configured
                if (repository?.isApiKeyConfigured() != true) {
                    _errorMessage.value = "API Key belum dikonfigurasi. Silakan atur API Key di halaman Pengaturan."
                    _isProcessing.value = false
                    return@launch
                }

                val result = repository?.summarizeText(text) ?: Result.failure(Exception("Repository not initialized"))

                result.fold(
                    onSuccess = { summary ->
                        // Convert ke SummaryData format
                        val summaryData = SummaryData(
                            title = "Ringkasan Materi",
                            subject = detectSubject(text),
                            grade = detectGrade(text),
                            mainConcept = summary,
                            keyPoints = extractKeyPoints(text),
                            keywords = extractKeywords(text)
                        )
                        _summaryResult.value = summaryData
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal membuat ringkasan: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            } finally {
                _isProcessing.value = false
            }
        }
    }

    /**
     * Save ringkasan ke Firebase
     */
    fun saveSummary(summary: SummaryData) {
        viewModelScope.launch {
            try {
                val result = firebaseService.saveSummary(summary)
                result.fold(
                    onSuccess = {
                        // Success
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal menyimpan: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            }
        }
    }

    /**
     * Clear all states
     */
    fun clearAll() {
        _extractedText.value = ""
        _summaryResult.value = null
        _errorMessage.value = null
        _isProcessing.value = false
    }

    /**
     * Clear error message
     */
    fun clearErrorMessage() {
        _errorMessage.value = null
    }

    // Helper functions untuk text analysis
    private fun detectSubject(text: String): String {
        val subjects = mapOf(
            "fisika" to "Fisika",
            "kimia" to "Kimia",
            "biologi" to "Biologi",
            "matematika" to "Matematika",
            "sejarah" to "Sejarah",
            "geografi" to "Geografi",
            "bahasa indonesia" to "Bahasa Indonesia",
            "bahasa inggris" to "Bahasa Inggris"
        )

        subjects.forEach { (key, subject) ->
            if (text.contains(key, ignoreCase = true)) {
                return subject
            }
        }
        return "Umum"
    }

    private fun detectGrade(text: String): Int {
        val gradeKeywords = mapOf(
            "kelas 10" to 10,
            "kelas 11" to 11,
            "kelas 12" to 12,
            "x" to 10,
            "xi" to 11,
            "xii" to 12
        )

        gradeKeywords.forEach { (key, grade) ->
            if (text.contains(key, ignoreCase = true)) {
                return grade
            }
        }
        return 10
    }

    private fun extractKeyPoints(text: String): List<com.christopheraldoo.bukuringkasapp.data.model.KeyPoint> {
        return text.split("\n")
            .filter { it.isNotBlank() && (it.startsWith("- ") || it.startsWith("• ")) }
            .take(5)
            .map { line ->
                val content = line.removePrefix("- ").removePrefix("• ").trim()
                com.christopheraldoo.bukuringkasapp.data.model.KeyPoint(
                    title = content.take(30),
                    explanation = content
                )
            }
    }

    private fun extractKeywords(text: String): List<String> {
        val ignoreWords = setOf("yang", "dan", "di", "ke", "dari", "dalam", "pada", "untuk", "dengan")
        return text.lowercase()
            .split(Regex("""\W+"""))
            .filter { it.length > 3 && it !in ignoreWords }
            .groupingBy { it }
            .eachCount()
            .entries
            .sortedByDescending { it.value }
            .take(5)
            .map { it.key }
    }
}
