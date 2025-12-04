package com.christopheraldoo.bukuringkasapp

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.bukuringkasapp.data.model.AppDatabase
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.google.gson.Gson
import kotlinx.coroutines.launch

class TextInputActivity : AppCompatActivity() {

    private lateinit var database: AppDatabase
    private lateinit var summarizer: Summarizer
    private val gson = Gson()

    private lateinit var inputText: EditText
    private lateinit var summarizeButton: Button
    private lateinit var clearButton: Button
    private lateinit var resultCard: CardView
    private lateinit var resultTitle: TextView
    private lateinit var resultContent: TextView
    private lateinit var progressBar: ProgressBar
    private lateinit var savedIndicator: TextView    // New UI elements for feedback and regeneration
    private lateinit var summaryActions: LinearLayout
    private lateinit var regenerateButton: Button
    private lateinit var editButton: Button
    private lateinit var feedbackSection: LinearLayout
    private lateinit var feedbackInput: EditText
    private lateinit var sendFeedbackButton: Button

    // Store current summary data for regeneration
    private var currentOriginalText: String = ""
    private var currentSummaryResult: SummaryResult? = null
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_text_input)
        database = AppDatabase.getDatabase(this)
        summarizer = Summarizer(applicationContext)

        setupToolbar()
        setupViews()
        setupEventListeners()
    }

    private fun setupToolbar() {
        val toolbar = findViewById<androidx.appcompat.widget.Toolbar>(R.id.toolbar)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Input Teks Manual"
    }

    private fun setupViews() {
        inputText = findViewById(R.id.inputText)
        summarizeButton = findViewById(R.id.summarizeButton)
        clearButton = findViewById(R.id.clearButton)
        resultCard = findViewById(R.id.resultCard)
        resultTitle = findViewById(R.id.resultTitle)
        resultContent = findViewById(R.id.resultContent)
        progressBar = findViewById(R.id.progressBar)
        savedIndicator = findViewById(R.id.savedIndicator)

        // Initialize new UI elements
        summaryActions = findViewById(R.id.summaryActions)
        regenerateButton = findViewById(R.id.regenerateButton)
        editButton = findViewById(R.id.editButton)
        feedbackSection = findViewById(R.id.feedbackSection)
        feedbackInput = findViewById(R.id.feedbackInput)
        sendFeedbackButton = findViewById(R.id.sendFeedbackButton)

        // Initially hide result card
        resultCard.visibility = View.GONE
    }

    private fun setupEventListeners() {
        clearButton.setOnClickListener {
            inputText.text.clear()
            resultCard.visibility = View.GONE
            savedIndicator.visibility = View.GONE
        }

        summarizeButton.setOnClickListener {
            val text = inputText.text.toString().trim()
            if (text.isNotEmpty()) {
                performSummarization(text)
            } else {
                Toast.makeText(this, "Masukkan teks terlebih dahulu", Toast.LENGTH_SHORT).show()
            }
        }

        inputText.addTextChangedListener(object : TextWatcher {
            override fun afterTextChanged(s: Editable?) {
                savedIndicator.visibility = View.GONE
            }
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
        })

        // New event listeners for summary interaction
        regenerateButton.setOnClickListener {
            currentOriginalText.takeIf { it.isNotEmpty() }?.let {
                performSummarization(it)
            } ?: Toast.makeText(this, "Tidak ada teks untuk diringkas ulang", Toast.LENGTH_SHORT).show()
        }

        editButton.setOnClickListener {
            showEditMode()
        }

        sendFeedbackButton.setOnClickListener {
            val feedback = feedbackInput.text.toString().trim()
            if (feedback.isNotEmpty()) {
                processFeedbackAndRegenerate(feedback)
            } else {
                Toast.makeText(this, "Masukkan feedback terlebih dahulu", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun performSummarization(text: String) {
        // Store original text for regeneration
        currentOriginalText = text

        // Show progress
        progressBar.visibility = View.VISIBLE
        summarizeButton.isEnabled = false
        resultCard.visibility = View.GONE

        lifecycleScope.launch {
            try {
                val result = summarizer.summarizeText(text)

                // Hide progress
                progressBar.visibility = View.GONE
                summarizeButton.isEnabled = true

                if (result.status == "success") {
                    displayResult(result)
                    autoSaveToHistory(result)
                } else {
                    showError(result.errorMessage ?: "Terjadi kesalahan")
                }
            } catch (e: Exception) {
                progressBar.visibility = View.GONE
                summarizeButton.isEnabled = true
                showError("Terjadi kesalahan: ${e.message}")
            }
        }
    }

    private fun displayResult(result: SummaryResult) {
        resultCard.visibility = View.VISIBLE

        resultTitle.text = "✅ Ringkasan"

        result.ringkasan?.let { ringkasan ->
            // Build result content
            val stringBuilder = StringBuilder()

            stringBuilder.append("🎯 KONSEP UTAMA:\n${ringkasan.konsepUtama}\n\n")

            if (ringkasan.poinPenting.isNotEmpty()) {
                stringBuilder.append("💡 POIN-POIN PENTING:\n")
                ringkasan.poinPenting.forEach { point ->
                    stringBuilder.append("• ${point.judul}: ${point.penjelasan}\n")
                }
                stringBuilder.append("\n")
            }

            ringkasan.rumus?.let { rumusList ->
                if (rumusList.isNotEmpty()) {
                    stringBuilder.append("📐 RUMUS KUNCI:\n")
                    rumusList.forEach { rumus ->
                        stringBuilder.append("• ${rumus.formula}\n")
                        rumus.keterangan?.let { stringBuilder.append("$it\n") }
                    }
                    stringBuilder.append("\n")
                }
            }

            ringkasan.contohAplikasi?.let {
                stringBuilder.append("📝 CONTOH APLIKASI:\n$it\n\n")
            }

            if (ringkasan.kataKunci.isNotEmpty()) {
                stringBuilder.append("🔑 KATA KUNCI: ${ringkasan.kataKunci.joinToString(" • ")}")
            }

            resultContent.text = stringBuilder.toString()
        }

        // Show action buttons for summary interaction
        summaryActions.visibility = View.VISIBLE
        feedbackSection.visibility = View.GONE

        // Store current data for regeneration
        currentSummaryResult = result
    }

    private fun autoSaveToHistory(result: SummaryResult) {
        result.ringkasan?.let { ringkasan ->
            lifecycleScope.launch {
                try {
                    val historyItem = HistoryItem(
                        title = ringkasan.topik,
                        subject = result.mataPelajaran ?: "Unknown",
                        grade = result.kelas ?: 10,
                        content = gson.toJson(ringkasan)
                    )
                    database.historyDao().insert(historyItem)
                    savedIndicator.visibility = View.VISIBLE
                } catch (e: Exception) {
                    // Handle error silently
                }
            }
        }
    }

    private fun showEditMode() {
        // Hide action buttons and show feedback section
        summaryActions.visibility = View.GONE
        feedbackSection.visibility = View.VISIBLE

        // Update feedback input hint to suggest editing
        feedbackInput.hint = "Edit ringkasan: tambahkan poin baru, perbaiki konsep, dll"
        resultTitle.text = "✏️ Mode Edit Ringkasan"

        // Pre-fill with current summary for editing
        currentSummaryResult?.ringkasan?.let { ringkasan ->
            val currentContent = buildString {
                append("KONSEP UTAMA: ${ringkasan.konsepUtama}\n\n")
                if (ringkasan.poinPenting.isNotEmpty()) {
                    append("POIN PENTING:\n")
                    ringkasan.poinPenting.forEach { point ->
                        append("- ${point.judul}: ${point.penjelasan}\n")
                    }
                    append("\n")
                }
                ringkasan.rumus?.let { rumusList ->
                    if (rumusList.isNotEmpty()) {
                        append("RUMUS:\n")
                        rumusList.forEach { rumus ->
                            append("- ${rumus.formula}\n")
                        }
                        append("\n")
                    }
                }
                ringkasan.contohAplikasi?.let {
                    append("CONTOH: $it\n\n")
                }
                append("KATA KUNCI: ${ringkasan.kataKunci.joinToString(", ")}")
            }
            feedbackInput.setText(currentContent)
        }
    }

    private fun processFeedbackAndRegenerate(feedback: String) {
        // Show loading state
        sendFeedbackButton.isEnabled = false
        sendFeedbackButton.text = "⏳ Memproses..."
        resultTitle.text = "🔄 Memproses Feedback..."

        // Simulate processing feedback with enhanced text
        val enhancedText = enhanceOriginalTextWithFeedback(currentOriginalText, feedback)

        lifecycleScope.launch {
            try {
                // Add a small delay to show processing
                kotlinx.coroutines.delay(1500)

                val result = summarizer.summarizeText(enhancedText)

                // Reset UI state
                sendFeedbackButton.isEnabled = true
                sendFeedbackButton.text = "📤 Kirim Feedback & Ringkas Ulang"

                if (result.status == "success") {
                    // Show success message
                    Toast.makeText(this@TextInputActivity,
                        "✅ Feedback diproses! Ringkasan baru telah dibuat.",
                        Toast.LENGTH_LONG).show()

                    displayResult(result)
                    autoSaveToHistory(result)

                    // Clear feedback input
                    feedbackInput.text.clear()
                } else {
                    showError(result.errorMessage ?: "Gagal memproses feedback")
                    // Reset to normal view
                    summaryActions.visibility = View.VISIBLE
                    feedbackSection.visibility = View.GONE
                    resultTitle.text = "✅ Ringkasan"
                }
            } catch (e: Exception) {
                sendFeedbackButton.isEnabled = true
                sendFeedbackButton.text = "📤 Kirim Feedback & Ringkas Ulang"
                showError("Terjadi kesalahan: ${e.message}")

                // Reset to normal view
                summaryActions.visibility = View.VISIBLE
                feedbackSection.visibility = View.GONE
                resultTitle.text = "✅ Ringkasan"
            }
        }
    }

    private fun enhanceOriginalTextWithFeedback(originalText: String, feedback: String): String {
        // Simple enhancement: append feedback as additional context
        return if (feedback.contains("tambahkan", ignoreCase = true) ||
                   feedback.contains("tambah", ignoreCase = true) ||
                   feedback.contains("plus", ignoreCase = true) ||
                   feedback.contains("ditambah", ignoreCase = true) ||
                   feedback.contains("kurang", ignoreCase = true) ||
                   feedback.contains("hapus", ignoreCase = true) ||
                   feedback.contains("remove", ignoreCase = true) ||
                   feedback.contains("perbaiki", ignoreCase = true) ||
                   feedback.contains("koreksi", ignoreCase = true) ||
                   feedback.contains("fix", ignoreCase = true)) {

            // For edit requests, combine original text with feedback
            "$originalText\n\n---\nFEEDBACK UNTUK PERBAIKAN:\n$feedback"
        } else {
            // For general feedback, treat as additional context
            "$originalText\n\n---\nINSTRUKSI KHUSUS:\n$feedback"
        }
    }

    private fun showError(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }
}
