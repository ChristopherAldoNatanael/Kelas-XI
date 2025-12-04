package com.christopheraldoo.bukuringkasapp

import com.christopheraldoo.bukuringkasapp.data.repository.OpenAIRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

data class RingkasanMateri(
    val mataPelajaran: String,
    val topik: String,
    val konsepUtama: String,
    val poinPenting: List<PoinPenting>,
    val rumus: List<Rumus>?,
    val contohAplikasi: String?,
    val kataKunci: List<String>
)

data class PoinPenting(
    val judul: String,
    val penjelasan: String
)

data class Rumus(
    val nama: String,
    val formula: String,
    val keterangan: String?
)

data class SummaryResult(
    val status: String, // "success", "error"
    val mataPelajaran: String?,
    val kelas: Int?,
    val topik: String?,
    val ringkasan: RingkasanMateri?,
    val errorMessage: String?
)

class Summarizer(private val context: android.content.Context) {

    private val repository = OpenAIRepository(context)

    suspend fun summarizeText(inputText: String): SummaryResult = withContext(Dispatchers.IO) {
        // Check if API key is configured
        if (!repository.isApiKeyConfigured()) {
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "API Key belum dikonfigurasi. Silakan atur API Key di halaman Pengaturan."
            )
        }
        if (inputText.isBlank()) {
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "Teks input kosong. Silakan masukkan materi yang akan dirangkum."
            )
        }

        // Cek panjang teks
        if (inputText.length > 5000) {
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "Teks ini cukup panjang. Untuk performa optimal di aplikasi, saya sarankan pecah menjadi bagian-bagian lebih kecil. Aplikasi dirancang untuk 2-3 paragraf setiap kali ringkasan."
            )
        }

        val cleanedText = cleanOcrText(inputText)

        // Cek kualitas OCR (jika terlalu banyak karakter aneh)
        val ocrQuality = checkOcrQuality(inputText, cleanedText)
        if (ocrQuality < 0.7) {
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "Maaf, teks hasil scan kurang jelas. Saya menemukan kesalahan OCR yang signifikan. Saran:\n\n• Foto ulang dengan pencahayaan lebih baik\n• Pastikan halaman rata (tidak melengkung)\n• Gunakan mode scan di kamera jika tersedia\n\nAtau tempel teks manual jika foto tidak memungkinkan."
            )
        }

        // Deteksi mata pelajaran
        val subject = detectSubject(cleanedText)
        if (subject.first.isEmpty()) {
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "Saya mendeteksi ini bukan buku pelajaran sekolah. Saya dirancang khusus untuk merangkum materi SMA/SMK Indonesia. Apakah Anda ingin melanjutkan?"
            )
        }

        // Deteksi kelas (untuk sementara perkirakan dari konteks)
        val grade = detectGrade(cleanedText)

        // Deteksi topik
        val topic = detectTopic(cleanedText)        // Coba API untuk ringkasan - ONLINE ONLY!
        val apiSummary = try {
            val result = repository.summarizeText(cleanedText)
            if (result.isSuccess) {
                result.getOrNull()
            } else {
                // TIDAK ADA FALLBACK - HARUS ONLINE!
                return@withContext SummaryResult(
                    status = "error",
                    mataPelajaran = null,
                    kelas = null,
                    topik = null,
                    ringkasan = null,
                    errorMessage = "APLIKASI MEMERLUKAN KONEKSI INTERNET! ${result.exceptionOrNull()?.message ?: "Koneksi gagal"}"
                )
            }
        } catch (e: Exception) {
            // TIDAK ADA FALLBACK - HARUS ONLINE!
            return@withContext SummaryResult(
                status = "error",
                mataPelajaran = null,
                kelas = null,
                topik = null,
                ringkasan = null,
                errorMessage = "APLIKASI MEMERLUKAN KONEKSI INTERNET! Error: ${e.message}"
            )
        }

        // Konsep utama: HANYA dari API
        val mainConcept = apiSummary ?: "Gagal mendapatkan ringkasan dari API"

        // Poin penting: ekstrak lembaga atau definisi
        val keyPoints = extractKeyPoints(cleanedText)

        // Rumus: cari pola matematika
        val formulas = extractFormulas(cleanedText)

        // Contoh aplikasi: cari bagian yang mengandung "contoh" atau "misalnya"
        val example = extractExample(cleanedText)

        // Kata kunci: ekstrak istilah penting
        val keywords = extractKeywords(cleanedText, subject.first)

        val ringkasan = RingkasanMateri(
            mataPelajaran = subject.first,
            topik = topic,
            konsepUtama = mainConcept,
            poinPenting = keyPoints,
            rumus = if (formulas.isNotEmpty()) formulas else null,
            contohAplikasi = example,
            kataKunci = keywords
        )

        return@withContext SummaryResult(
            status = "success",
            mataPelajaran = subject.first,
            kelas = grade,
            topik = topic,
            ringkasan = ringkasan,
            errorMessage = null
        )
    }

    private fun cleanOcrText(text: String): String {
        return text
            .replace(Regex("""\d\s+,"""), ".") // Perbaiki titik
            .replace(Regex("""[^\w\s\.\?\!\-\(\)²³√∑π=]"""), "") // Hapus karakter aneh
            .replace(Regex("""\s+"""), " ") // Spasi ganda
            .trim()
    }

    private fun checkOcrQuality(original: String, cleaned: String): Double {
        val originalChars = original.filter { it.isLetterOrDigit() }.length
        val cleanedChars = cleaned.filter { it.isLetterOrDigit() }.length
        return if (originalChars > 0) cleanedChars.toDouble() / originalChars else 0.0
    }

    private fun detectSubject(text: String): Pair<String, Int> {
        val subjects = mapOf(
            "fisika" to "Fisika",
            "kimia" to "Kimia",
            "biologi" to "Biologi",
            "matematika" to "Matematika",
            "sejarah" to "Sejarah",
            "geografi" to "Geografi",
            "sosiologi" to "Sosiologi",
            "ekonomi" to "Ekonomi",
            "bahasa indonesia" to "Bahasa Indonesia",
            "bahasa inggris" to "Bahasa Inggris"
        )

        for ((key, subject) in subjects) {
            if (text.contains(key, ignoreCase = true)) {
                return subject to 10 // Default grade
            }
        }

        return "" to 0
    }

    private fun detectGrade(text: String): Int {
        // Detect class level from context (simplified)
        val gradeKeywords = mapOf(
            "kelas 10" to 10,
            "kelas 11" to 11,
            "kelas 12" to 12,
            "x" to 10,
            "xi" to 11,
            "xii" to 12
        )

        for ((key, grade) in gradeKeywords) {
            if (text.contains(key, ignoreCase = true)) {
                return grade
            }
        }

        return 10 // Default
    }

    private fun detectTopic(text: String): String {
        val lines = text.split("\n").filter { it.isNotBlank() }
        for (line in lines) {
            // Cari judul atau topik utama
            if (line.length in 10..100 && !line.contains("=") && !Regex("""\d+\.""").containsMatchIn(line)) {
                return line.take(50).trim()
            }
        }
        return "Topik Materi" // Default
    }

    private fun extractMainConcept(text: String): String {
        val sentences = text.split(Regex("""[.!?]""")).filter { it.isNotBlank() }
        val candidates = sentences.take(3).filter { !it.contains("=") }.joinToString(" ")
        return if (candidates.length <= 200) candidates else candidates.take(200) + "..."
    }

    private fun extractKeyPoints(text: String): List<PoinPenting> {
        val points = mutableListOf<PoinPenting>()
        val lines = text.split("\n").filter { it.isNotBlank() }

        for (line in lines) {
            if (line.startsWith("- ") || line.startsWith("• ")) {
                val content = line.removePrefix("- ").removePrefix("• ").trim()
                if (content.isNotBlank() && content.length <= 150) {
                    points.add(PoinPenting(content.take(30), content))
                }
            }
        }

        // Jika tidak ada bullet points, buat dari kalimat
        if (points.isEmpty()) {
            val sentences = text.split(Regex("""[.!?]""")).filter { it.isNotBlank() && !it.contains("=") }
            sentences.take(5).forEachIndexed { index, sentence ->
                if (sentence.trim().length <= 150) {
                    points.add(PoinPenting("Poin ${index + 1}", sentence.trim()))
                }
            }
        }

        return points.take(7) // Max 7 points
    }

    private fun extractFormulas(text: String): List<Rumus> {
        val formulas = mutableListOf<Rumus>()
        val formulaPattern = Regex("""([a-zA-Z]+\s*=\s*[^.\n]{3,50})(?:\s*\([^)]*\))?""")

        formulaPattern.findAll(text).forEach { match ->
            val formula = match.value
            val name = formula.split(" = ").firstOrNull()?.trim() ?: "Formula"
            formulas.add(Rumus(name, formula, null))
        }

        return formulas
    }

    private fun extractExample(text: String): String? {
        val exampleKeywords = listOf("contoh", "misalnya", "seperti ini", "aplikasi", "penggunaan")

        for (keyword in exampleKeywords) {
            val index = text.indexOf(keyword, ignoreCase = true)
            if (index != -1) {
                var example = text.substring(index)
                val endIndex = example.indexOfAny(listOf("\n\n", ". ", "! ", "? ")).takeIf { it != -1 } ?: example.length
                example = example.substring(0, minOf(endIndex, 300))
                return example.trim()
            }
        }

        return null
    }

    private fun extractKeywords(text: String, subject: String): List<String> {
        // Sederhana: ambil kata yang sering muncul (simplified)
        val ignoreWords = setOf("yang", "di", "ke", "dari", "dalam", "pada", "untuk", "dengan", "dan", "atau", "jika", "saat", "hal")
        val words = Regex("""\b[a-z]{4,}\b""").findAll(text.lowercase())
            .map { it.value }
            .filter { it !in ignoreWords }
            .groupingBy { it }
            .eachCount()
            .entries
            .sortedByDescending { it.value }
            .take(5)
            .map { it.key }

        val predefinedKeywords = when (subject) {
            "Fisika" -> listOf("Kecepatan", "Gaya", "Energi", "Gerak", "Massa")
            "Matematika" -> listOf("Variabel", "Fungsi", "Equations", "Angka", "Perhitungan")
            else -> words
        }

        return predefinedKeywords.take(5)
    }
}
