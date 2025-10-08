package com.christopheraldoo.bukuringkasapp

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.os.Bundle
import android.provider.MediaStore
import android.view.View
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.google.gson.Gson
import kotlinx.coroutines.launch

class CameraActivity : AppCompatActivity() {

    private lateinit var database: AppDatabase
    private lateinit var summarizer: Summarizer
    private val gson = Gson()

    private lateinit var cameraPreview: ImageView
    private lateinit var captureButton: Button
    private lateinit var ocrTextView: TextView
    private lateinit var summarizeButton: Button
    private lateinit var resultCard: CardView
    private lateinit var resultContent: TextView
    private lateinit var progressBar: ProgressBar

    private val CAMERA_PERMISSION_CODE = 100
    private val CAMERA_REQUEST_CODE = 200

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_camera)

        database = AppDatabase.getDatabase(this)
        summarizer = Summarizer()

        setupToolbar()
        setupViews()
        setupEventListeners()
        checkCameraPermission()
    }

    private fun setupToolbar() {
        val toolbar = findViewById<androidx.appcompat.widget.Toolbar>(R.id.toolbar)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Scan Buku Pelajaran"
    }

    private fun setupViews() {
        cameraPreview = findViewById(R.id.cameraPreview)
        captureButton = findViewById(R.id.captureButton)
        ocrTextView = findViewById(R.id.ocrTextView)
        summarizeButton = findViewById(R.id.summarizeButton)
        resultCard = findViewById(R.id.resultCard)
        resultContent = findViewById(R.id.resultContent)
        progressBar = findViewById(R.id.progressBar)

        // Initially hide OCR and result sections
        ocrTextView.visibility = View.GONE
        summarizeButton.visibility = View.GONE
        resultCard.visibility = View.GONE
    }

    private fun setupEventListeners() {
        captureButton.setOnClickListener {
            if (checkCameraPermission()) {
                openCamera()
            }
        }

        summarizeButton.setOnClickListener {
            val ocrText = ocrTextView.text.toString()
            if (ocrText.isNotEmpty()) {
                performSummarization(ocrText)
            }
        }

        cameraPreview.setOnClickListener {
            if (checkCameraPermission()) {
                openCamera()
            }
        }
    }

    private fun checkCameraPermission(): Boolean {
        return if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                this,
                arrayOf(Manifest.permission.CAMERA),
                CAMERA_PERMISSION_CODE
            )
            false
        } else {
            true
        }
    }

    private fun openCamera() {
        val intent = Intent(MediaStore.ACTION_IMAGE_CAPTURE)
        startActivityForResult(intent, CAMERA_REQUEST_CODE)
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == CAMERA_REQUEST_CODE && resultCode == RESULT_OK) {
            val photo = data?.extras?.get("data") as Bitmap?
            if (photo != null) {
                cameraPreview.setImageBitmap(photo)
                cameraPreview.visibility = View.VISIBLE
                captureButton.visibility = View.GONE

                // Simulate OCR processing
                processImageWithOCR(photo)
            }
        }
    }

    private fun processImageWithOCR(bitmap: Bitmap) {
        progressBar.visibility = View.VISIBLE

        lifecycleScope.launch {
            try {
                // Simulate OCR processing delay
                kotlinx.coroutines.delay(2000)

                // Use sample OCR text for now
                val ocrText = SAMPLE_OCR_TEXT
                ocrTextView.text = ocrText
                ocrTextView.visibility = View.VISIBLE
                summarizeButton.visibility = View.VISIBLE
            } catch (e: Exception) {
                Toast.makeText(this@CameraActivity, "Error processing image", Toast.LENGTH_SHORT).show()
            } finally {
                progressBar.visibility = View.GONE
            }
        }
    }

    private fun performSummarization(text: String) {
        progressBar.visibility = View.VISIBLE
        summarizeButton.isEnabled = false
        resultCard.visibility = View.GONE

        lifecycleScope.launch {
            try {
                val result = summarizer.summarizeText(text)

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

        result.ringkasan?.let { ringkasan ->
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
                    }
                    stringBuilder.append("\n")
                }
            }

            resultContent.text = stringBuilder.toString()
        }
    }

    private fun autoSaveToHistory(result: SummaryResult) {
        result.ringkasan?.let { ringkasan ->
            lifecycleScope.launch {
                try {
                    val historyItem = HistoryItem(
                        title = "Scan ${ringkasan.topik.take(20)}...",
                        subject = result.mataPelajaran ?: "Buku Pelajaran",
                        grade = result.kelas,
                        createdDate = System.currentTimeMillis(),
                        summaryData = gson.toJson(ringkasan)
                    )
                    database.historyDao().insert(historyItem)
                    Toast.makeText(this@CameraActivity, "Ringkasan tersimpan", Toast.LENGTH_SHORT).show()
                } catch (e: Exception) {
                    // Handle error silently
                }
            }
        }
    }

    private fun showError(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == CAMERA_PERMISSION_CODE) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                openCamera()
            } else {
                Toast.makeText(this, "Camera permission required", Toast.LENGTH_SHORT).show()
            }
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }

    companion object {
        const val SAMPLE_OCR_TEXT = """
        Hukum Newton tentang Gerak

        Gerak adalah perubahan posisi suatu benda terhadap waktu berjalan. Ada beberapa jenis gerak yaitu gerak lurus beraturan (GLB), gerak lurus berubah beraturan (GLBB), dan gerak melingkar.

        Hukum Newton yang pertama: Suatu benda akan tetap dalam keadaan diam atau gerak lurus beraturan jika tidak ada gaya yang bekerja pada benda tersebut atau jika resultan gaya yang bekerja pada benda tersebut sama dengan nol.

        F = m * a

        Contoh: Sebuah mobil balap melaju dengan konstan 100 km/jam. Jika tidak ada gesekan atau gaya lain, maka mobil tersebut akan terus melaju dengan kecepatan konstan tersebut.

        Kunci penting: Perubahan gerak suatu benda disebabkan oleh gaya.
        """
    }
}
