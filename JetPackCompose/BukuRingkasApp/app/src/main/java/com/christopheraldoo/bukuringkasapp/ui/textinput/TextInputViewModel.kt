package com.christopheraldoo.bukuringkasapp.ui.textinput

import android.app.Application
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.widget.Toast
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.SummaryResult
import com.christopheraldoo.bukuringkasapp.RingkasanMateri
import com.christopheraldoo.bukuringkasapp.data.model.AppDatabase
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.christopheraldoo.bukuringkasapp.data.repository.GeminiRepository
import com.google.gson.Gson
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class TextInputUiState(
    val inputText: String = "",
    val isLoading: Boolean = false,
    val summaryResult: SummaryResult? = null,
    val errorMessage: String? = null,
    val isSaved: Boolean = false,
    val feedbackText: String = ""
)

class TextInputViewModel(application: Application) : AndroidViewModel(application) {
    
    private val _uiState = MutableStateFlow(TextInputUiState())
    val uiState: StateFlow<TextInputUiState> = _uiState.asStateFlow()
    
    private val geminiRepository = GeminiRepository(application)
    private val database = AppDatabase.getDatabase(application)
    private val gson = Gson()
    
    private var currentOriginalText: String = ""
    
    fun updateInputText(text: String) {
        _uiState.update { it.copy(inputText = text, errorMessage = null) }
    }
    
    fun updateFeedbackText(text: String) {
        _uiState.update { it.copy(feedbackText = text) }
    }
    
    fun summarizeText() {
        val text = _uiState.value.inputText.trim()
        if (text.isEmpty()) {
            _uiState.update { it.copy(errorMessage = "Teks tidak boleh kosong") }
            return
        }
        
        currentOriginalText = text
        
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, summaryResult = null) }
            
            try {
                val result = geminiRepository.summarizeText(text)
                
                result.fold(
                    onSuccess = { summary ->
                        val summaryResult = SummaryResult(
                            status = "success",
                            mataPelajaran = detectSubject(text),
                            kelas = null,
                            topik = detectTopic(text),
                            ringkasan = RingkasanMateri(
                                mataPelajaran = detectSubject(text),
                                topik = detectTopic(text),
                                konsepUtama = summary,
                                poinPenting = emptyList(),
                                rumus = null,
                                contohAplikasi = null,
                                kataKunci = emptyList()
                            ),
                            errorMessage = null
                        )
                        
                        _uiState.update { 
                            it.copy(
                                isLoading = false, 
                                summaryResult = summaryResult,
                                isSaved = false
                            ) 
                        }
                        
                        // Auto save to history
                        saveToHistory(summaryResult)
                    },
                    onFailure = { error ->
                        _uiState.update { 
                            it.copy(
                                isLoading = false, 
                                errorMessage = error.message ?: "Gagal membuat ringkasan"
                            ) 
                        }
                    }
                )
            } catch (e: Exception) {
                _uiState.update { 
                    it.copy(
                        isLoading = false, 
                        errorMessage = "Error: ${e.message}"
                    ) 
                }
            }
        }
    }
    
    fun enhanceSummary(type: String) {
        if (currentOriginalText.isEmpty()) {
            _uiState.update { it.copy(errorMessage = "Tidak ada teks untuk ditingkatkan") }
            return
        }
        
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            
            try {
                val result = geminiRepository.summarizeWithEnhancement(currentOriginalText, type)
                
                result.fold(
                    onSuccess = { summary ->
                        val currentResult = _uiState.value.summaryResult
                        val updatedResult = currentResult?.copy(
                            topik = "Ringkasan yang Ditingkatkan",
                            ringkasan = currentResult.ringkasan?.copy(konsepUtama = summary)
                        ) ?: SummaryResult(
                            status = "success",
                            mataPelajaran = null,
                            kelas = null,
                            topik = "Ringkasan yang Ditingkatkan",
                            ringkasan = RingkasanMateri(
                                mataPelajaran = "",
                                topik = "",
                                konsepUtama = summary,
                                poinPenting = emptyList(),
                                rumus = null,
                                contohAplikasi = null,
                                kataKunci = emptyList()
                            ),
                            errorMessage = null
                        )
                        
                        _uiState.update { 
                            it.copy(
                                isLoading = false, 
                                summaryResult = updatedResult
                            ) 
                        }
                    },
                    onFailure = { error ->
                        _uiState.update { 
                            it.copy(
                                isLoading = false, 
                                errorMessage = error.message
                            ) 
                        }
                    }
                )
            } catch (e: Exception) {
                _uiState.update { 
                    it.copy(
                        isLoading = false, 
                        errorMessage = "Error: ${e.message}"
                    ) 
                }
            }
        }
    }
    
    fun updateSummary(editedText: String) {
        val currentResult = _uiState.value.summaryResult ?: return
        
        val updatedResult = currentResult.copy(
            ringkasan = currentResult.ringkasan?.copy(konsepUtama = editedText)
        )
        
        _uiState.update { it.copy(summaryResult = updatedResult) }
        
        viewModelScope.launch {
            saveToHistory(updatedResult, isEdited = true)
        }
    }
    
    fun clearAll() {
        currentOriginalText = ""
        _uiState.update { 
            TextInputUiState() 
        }
    }
    
    fun copyToClipboard() {
        val context = getApplication<Application>()
        val content = _uiState.value.summaryResult?.ringkasan?.konsepUtama ?: return
        
        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        val clip = ClipData.newPlainText("Ringkasan", content)
        clipboard.setPrimaryClip(clip)
        
        Toast.makeText(context, "Ringkasan disalin ke clipboard", Toast.LENGTH_SHORT).show()
    }
    
    fun sendFeedback() {
        val feedback = _uiState.value.feedbackText
        if (feedback.isBlank()) return
        
        // Log feedback (di production, kirim ke server)
        println("FEEDBACK: $feedback")
        
        Toast.makeText(getApplication(), "Terima kasih atas feedback Anda!", Toast.LENGTH_SHORT).show()
        _uiState.update { it.copy(feedbackText = "") }
    }
      private suspend fun saveToHistory(summaryResult: SummaryResult, isEdited: Boolean = false) {
        try {
            val historyItem = HistoryItem(
                title = summaryResult.topik ?: "Ringkasan",
                subject = summaryResult.mataPelajaran ?: "Umum",
                grade = summaryResult.kelas ?: 10,
                type = "summary",
                content = gson.toJson(summaryResult.ringkasan),
                createdAt = System.currentTimeMillis()
            )
            
            database.historyDao().insert(historyItem)
            _uiState.update { it.copy(isSaved = true) }
            
            val message = if (isEdited) "Ringkasan yang diedit tersimpan" else "Ringkasan tersimpan"
            Toast.makeText(getApplication(), message, Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Toast.makeText(getApplication(), "Gagal menyimpan: ${e.message}", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun detectSubject(text: String): String {
        val subjectKeywords = mapOf(
            "Matematika" to listOf("persamaan", "fungsi", "integral", "turunan", "matriks", "vektor", "logaritma"),
            "Fisika" to listOf("gaya", "energi", "momentum", "gelombang", "listrik", "magnet", "newton"),
            "Kimia" to listOf("unsur", "senyawa", "reaksi", "mol", "atom", "elektron", "ikatan"),
            "Biologi" to listOf("sel", "organ", "jaringan", "evolusi", "genetika", "ekosistem", "fotosintesis"),
            "Sejarah" to listOf("perang", "kerajaan", "revolusi", "kolonial", "kemerdekaan", "proklamasi"),
            "Ekonomi" to listOf("permintaan", "penawaran", "inflasi", "pasar", "produksi", "konsumsi"),
            "Bahasa Indonesia" to listOf("puisi", "prosa", "novel", "cerpen", "paragraf", "kalimat")
        )
        
        val lowerText = text.lowercase()
        
        for ((subject, keywords) in subjectKeywords) {
            for (keyword in keywords) {
                if (lowerText.contains(keyword)) {
                    return subject
                }
            }
        }
        
        return "Umum"
    }
    
    private fun detectTopic(text: String): String {
        // Simple topic detection - take first meaningful sentence
        val sentences = text.split(".", "!", "?").filter { it.length > 10 }
        return if (sentences.isNotEmpty()) {
            val firstSentence = sentences.first().trim()
            if (firstSentence.length > 50) {
                firstSentence.take(50) + "..."
            } else {
                firstSentence
            }
        } else {
            "Ringkasan Materi"
        }
    }
}
