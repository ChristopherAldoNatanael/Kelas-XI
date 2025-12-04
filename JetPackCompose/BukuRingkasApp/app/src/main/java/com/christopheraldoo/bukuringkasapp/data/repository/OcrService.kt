package com.christopheraldoo.bukuringkasapp.data.repository

import android.content.Context
import android.graphics.Bitmap
import android.net.Uri
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.text.TextRecognition
import com.google.mlkit.vision.text.TextRecognizer
import com.google.mlkit.vision.text.latin.TextRecognizerOptions
import kotlinx.coroutines.tasks.await

/**
 * Standalone OCR Service menggunakan Google ML Kit
 * Tidak memerlukan aktivasi di Firebase Console
 */
class OcrService(private val context: android.content.Context) {

    private val textRecognizer: TextRecognizer by lazy {
        TextRecognition.getClient(TextRecognizerOptions.DEFAULT_OPTIONS)
    }

    /**
     * Ekstrak teks dari gambar Bitmap
     */
    suspend fun extractTextFromBitmap(bitmap: Bitmap): Result<String> {
        return try {
            val image = InputImage.fromBitmap(bitmap, 0)

            val result = textRecognizer.process(image).await()

            val extractedText = result.text

            if (extractedText.isNotBlank()) {
                Result.success(extractedText)
            } else {
                Result.failure(Exception("Tidak ada teks yang ditemukan dalam gambar"))
            }
        } catch (e: Exception) {
            Result.failure(Exception("Gagal mengekstrak teks: ${e.message}"))
        }
    }

    /**
     * Ekstrak teks dari URI gambar
     */
    suspend fun extractTextFromUri(uri: Uri): Result<String> {
        return try {
            val image = InputImage.fromFilePath(context, uri)

            val result = textRecognizer.process(image).await()

            val extractedText = result.text

            if (extractedText.isNotBlank()) {
                Result.success(extractedText)
            } else {
                Result.failure(Exception("Tidak ada teks yang ditemukan dalam gambar"))
            }
        } catch (e: Exception) {
            Result.failure(Exception("Gagal mengekstrak teks: ${e.message}"))
        }
    }

    /**
     * Membersihkan teks hasil OCR dari karakter yang tidak diinginkan
     */
    fun cleanOcrText(text: String): String {
        return text
            .replace(Regex("""\n\s*\n"""), "\n") // Hapus baris kosong ganda
            .replace(Regex("""[^\w\s\.\?\!\-\(\)²³√∑π=]"""), "") // Hapus karakter aneh
            .replace(Regex("""\s+"""), " ") // Normalisasi spasi
            .trim()
    }

    /**
     * Mendeteksi bahasa teks (sederhana)
     */
    fun detectLanguage(text: String): String {
        val indonesianWords = listOf("yang", "dan", "di", "dengan", "untuk", "dari", "ini", "itu", "ada", "oleh")
        val englishWords = listOf("the", "and", "in", "with", "for", "from", "this", "that", "there", "is")

        val indonesianCount = indonesianWords.count { text.contains(it, ignoreCase = true) }
        val englishCount = englishWords.count { text.contains(it, ignoreCase = true) }

        return when {
            indonesianCount > englishCount -> "indonesian"
            englishCount > indonesianCount -> "english"
            else -> "mixed"
        }
    }

    /**
     * Validasi kualitas hasil OCR
     */
    fun validateOcrQuality(originalText: String, cleanedText: String): Double {
        if (originalText.isBlank()) return 0.0

        val originalLength = originalText.length
        val cleanedLength = cleanedText.length

        return (cleanedLength.toDouble() / originalLength).coerceAtMost(1.0)
    }

    /**
     * Close text recognizer untuk cleanup
     */
    fun close() {
        textRecognizer.close()
    }
}

/**
 * Extension function untuk Bitmap
 */
suspend fun OcrService.extractTextFromBitmap(bitmap: Bitmap): Result<String> {
    return extractTextFromBitmap(bitmap)
}

/**
 * Extension function untuk Uri
 */
suspend fun OcrService.extractTextFromUri(uri: Uri): Result<String> {
    return extractTextFromUri(uri)
}
