package com.christopheraldoo.bukuringkasapp

import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.Uri
import android.os.Bundle
import android.provider.MediaStore
import android.provider.Settings
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.activity.result.ActivityResult
import androidx.activity.result.ActivityResultLauncher
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.bukuringkasapp.data.model.AppDatabase
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.google.gson.Gson
import kotlinx.coroutines.launch
import java.io.File
import java.io.IOException
import java.text.SimpleDateFormat
import java.util.*

class MultiImageCameraActivity : AppCompatActivity() {

    private lateinit var database: AppDatabase
    private lateinit var summarizer: Summarizer
    private val gson = Gson()

    private lateinit var imageRecyclerView: RecyclerView
    private lateinit var addImageButton: Button
    private lateinit var summarizeButton: Button
    private lateinit var resultCard: CardView
    private lateinit var resultContent: TextView
    private lateinit var progressBar: ProgressBar
    private lateinit var selectedImagesText: TextView

    private val selectedImages = mutableListOf<Uri>()
    private lateinit var imageAdapter: ImageAdapter
    private lateinit var currentPhotoUri: Uri    // Activity result launchers
    private lateinit var cameraLauncher: ActivityResultLauncher<Uri>
    private lateinit var galleryLauncher: ActivityResultLauncher<String>
    private lateinit var permissionLauncher: ActivityResultLauncher<Array<String>>
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_multi_image_camera)
        database = AppDatabase.getDatabase(this)
        summarizer = Summarizer(applicationContext)

        setupToolbar()
        setupViews()
        setupActivityResultLaunchers()
        setupEventListeners()
    }

    private fun setupToolbar() {
        val toolbar = findViewById<androidx.appcompat.widget.Toolbar>(R.id.toolbar)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Multi-Image Scanner"
    }

    private fun setupViews() {
        imageRecyclerView = findViewById(R.id.imageRecyclerView)
        addImageButton = findViewById(R.id.addImageButton)
        summarizeButton = findViewById(R.id.summarizeButton)
        resultCard = findViewById(R.id.resultCard)
        resultContent = findViewById(R.id.resultContent)
        progressBar = findViewById(R.id.progressBar)
        selectedImagesText = findViewById(R.id.selectedImagesText)

        // Setup RecyclerView
        imageRecyclerView.layoutManager = GridLayoutManager(this, 2)
        imageAdapter = ImageAdapter { uri ->
            removeImage(uri)
        }
        imageRecyclerView.adapter = imageAdapter

        // Initially hide result and summarize button
        resultCard.visibility = View.GONE
        summarizeButton.visibility = View.GONE
        updateSelectedImagesText()
    }

    private fun setupActivityResultLaunchers() {
        // Camera launcher
        cameraLauncher = registerForActivityResult(
            ActivityResultContracts.TakePicture()
        ) { success ->
            if (success) {
                currentPhotoUri?.let { uri ->
                    addImageToList(uri)
                }
            }
        }

        // Gallery launcher
        galleryLauncher = registerForActivityResult(
            ActivityResultContracts.GetMultipleContents()
        ) { uris ->
            uris.forEach { uri ->
                addImageToList(uri)
            }
        }

        // Permission launcher
        permissionLauncher = registerForActivityResult(
            ActivityResultContracts.RequestMultiplePermissions()
        ) { permissions ->
            val cameraGranted = permissions[android.Manifest.permission.CAMERA] ?: false
            val storageGranted = permissions[android.Manifest.permission.READ_EXTERNAL_STORAGE] ?: false

            if (cameraGranted || storageGranted) {
                showImageSourceDialog()
            } else {
                showPermissionDeniedDialog()
            }
        }
    }

    private fun setupEventListeners() {
        addImageButton.setOnClickListener {
            if (checkPermissions()) {
                showImageSourceDialog()
            } else {
                requestPermissions()
            }
        }

        summarizeButton.setOnClickListener {
            if (selectedImages.isNotEmpty()) {
                processAllImages()
            } else {
                Toast.makeText(this, "Pilih gambar terlebih dahulu", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun checkPermissions(): Boolean {
        val cameraPermission = ContextCompat.checkSelfPermission(
            this, android.Manifest.permission.CAMERA
        ) == PackageManager.PERMISSION_GRANTED

        val storagePermission = ContextCompat.checkSelfPermission(
            this, android.Manifest.permission.READ_EXTERNAL_STORAGE
        ) == PackageManager.PERMISSION_GRANTED

        return cameraPermission || storagePermission
    }

    private fun requestPermissions() {
        val permissions = arrayOf(
            android.Manifest.permission.CAMERA,
            android.Manifest.permission.READ_EXTERNAL_STORAGE
        )
        permissionLauncher.launch(permissions)
    }

    private fun showImageSourceDialog() {
        val options = arrayOf("Kamera", "Galeri", "Batal")
        AlertDialog.Builder(this)
            .setTitle("Pilih Sumber Gambar")
            .setItems(options) { _, which ->
                when (which) {
                    0 -> openCamera()
                    1 -> openGallery()
                }
            }
            .show()
    }

    private fun openCamera() {
        try {
            val photoFile = createImageFile()
            currentPhotoUri = FileProvider.getUriForFile(
                this,
                "${applicationContext.packageName}.provider",
                photoFile
            )
            cameraLauncher.launch(currentPhotoUri)
        } catch (e: IOException) {
            Toast.makeText(this, "Error creating file", Toast.LENGTH_SHORT).show()
        }
    }

    private fun openGallery() {
        galleryLauncher.launch("image/*")
    }

    @Throws(IOException::class)
    private fun createImageFile(): File {
        val timeStamp: String = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        val storageDir: File = File(cacheDir, "images")
        if (!storageDir.exists()) storageDir.mkdirs()
        return File(storageDir, "JPEG_${timeStamp}_.jpg")
    }

    private fun addImageToList(uri: Uri) {
        if (selectedImages.size < 10) { // Limit to 10 images
            selectedImages.add(uri)
            imageAdapter.updateImages(selectedImages)
            updateSelectedImagesText()
            summarizeButton.visibility = View.VISIBLE
        } else {
            Toast.makeText(this, "Maksimal 10 gambar", Toast.LENGTH_SHORT).show()
        }
    }

    private fun removeImage(uri: Uri) {
        selectedImages.remove(uri)
        imageAdapter.updateImages(selectedImages)
        updateSelectedImagesText()

        if (selectedImages.isEmpty()) {
            summarizeButton.visibility = View.GONE
            resultCard.visibility = View.GONE
        }
    }

    private fun updateSelectedImagesText() {
        selectedImagesText.text = "Gambar terpilih: ${selectedImages.size}/10"
    }

    private fun processAllImages() {
        progressBar.visibility = View.VISIBLE
        summarizeButton.isEnabled = false

        lifecycleScope.launch {
            try {
                val combinedText = StringBuilder()

                // Process each image (simulate OCR)
                selectedImages.forEach { uri ->
                    val ocrText = simulateOCRFromImage(uri)
                    combinedText.append(ocrText).append("\n\n")
                }

                // Summarize combined text
                val result = summarizer.summarizeText(combinedText.toString())

                progressBar.visibility = View.GONE
                summarizeButton.isEnabled = true

                if (result.status == "success") {
                    displayResult(result)
                    saveToHistory(result)
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

    private fun simulateOCRFromImage(uri: Uri): String {
        // Simulate OCR processing with different sample texts
        val sampleTexts = listOf(
            """
            Hukum Newton tentang Gerak

            Gerak adalah perubahan posisi suatu benda terhadap waktu berjalan. Ada beberapa jenis gerak yaitu gerak lurus beraturan (GLB), gerak lurus berubah beraturan (GLBB), dan gerak melingkar.

            Hukum Newton yang pertama: Suatu benda akan tetap dalam keadaan diam atau gerak lurus beraturan jika tidak ada gaya yang bekerja pada benda tersebut atau jika resultan gaya yang bekerja pada benda tersebut sama dengan nol.

            F = m * a

            Contoh: Sebuah mobil balap melaju dengan konstan 100 km/jam. Jika tidak ada gesekan atau gaya lain, maka mobil tersebut akan terus melaju dengan kecepatan konstan tersebut.

            Kunci penting: Perubahan gerak suatu benda disebabkan oleh gaya.
            """.trimIndent(),
            """
            Struktur Atom dan Molekul

            Atom terdiri dari proton, neutron, dan elektron. Proton dan neutron berada di inti atom, sedangkan elektron mengelilingi inti.

            Ikatan kimia terjadi ketika atom-atom berbagi atau mentransfer elektron untuk mencapai konfigurasi elektron yang stabil.

            Ada tiga jenis ikatan utama:
            1. Ikatan ionik - transfer elektron
            2. Ikatan kovalen - berbagi elektron
            3. Ikatan logam - elektron bebas bergerak

            Molekul adalah kumpulan atom yang terikat bersama. Rumus kimia menunjukkan komposisi atom dalam molekul.
            """.trimIndent(),
            """
            Sistem Pernapasan Manusia

            Sistem pernapasan manusia terdiri dari hidung, tenggorokan, batang tenggorokan, bronkus, dan paru-paru.

            Proses pernapasan meliputi:
            1. Inspirasi - menghirup udara
            2. Ekspirasi - mengeluarkan udara

            Pertukaran gas terjadi di alveoli paru-paru. Oksigen dari udara masuk ke darah, sedangkan karbon dioksida dari darah dikeluarkan.

            Kapasitas vital paru-paru adalah volume udara maksimal yang dapat dihirup dan dikeluarkan seseorang.
            """.trimIndent()
        )

        return sampleTexts.random()
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

    private fun saveToHistory(result: SummaryResult) {
        result.ringkasan?.let { ringkasan ->
            lifecycleScope.launch {
                try {
                    val historyItem = HistoryItem(
                        title = "Multi-Scan: ${ringkasan.topik.take(30)}...",
                        subject = result.mataPelajaran ?: "Multi-Image Scan",
                        grade = result.kelas ?: 10,
                        content = gson.toJson(ringkasan)
                    )
                    database.historyDao().insert(historyItem)
                    Toast.makeText(this@MultiImageCameraActivity, "Ringkasan tersimpan", Toast.LENGTH_SHORT).show()
                } catch (e: Exception) {
                    // Handle error silently
                }
            }
        }
    }

    private fun showError(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }

    private fun showPermissionDeniedDialog() {
        AlertDialog.Builder(this)
            .setTitle("Izin Diperlukan")
            .setMessage("Aplikasi memerlukan izin kamera dan penyimpanan untuk mengakses gambar. Buka pengaturan untuk memberikan izin?")
            .setPositiveButton("Pengaturan") { _, _ ->
                val intent = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                val uri = Uri.fromParts("package", packageName, null)
                intent.data = uri
                startActivity(intent)
            }
            .setNegativeButton("Batal") { dialog, _ -> dialog.dismiss() }
            .show()
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }

    inner class ImageAdapter(private val onRemoveClick: (Uri) -> Unit) :
        RecyclerView.Adapter<ImageAdapter.ImageViewHolder>() {

        private var images: List<Uri> = emptyList()

        fun updateImages(newImages: List<Uri>) {
            images = newImages
            notifyDataSetChanged()
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ImageViewHolder {
            val view = layoutInflater.inflate(R.layout.item_image, parent, false)
            return ImageViewHolder(view)
        }

        override fun onBindViewHolder(holder: ImageViewHolder, position: Int) {
            holder.bind(images[position])
        }

        override fun getItemCount() = images.size

        inner class ImageViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
            private val imageView: ImageView = itemView.findViewById(R.id.imageView)
            private val removeButton: ImageButton = itemView.findViewById(R.id.removeButton)

            fun bind(uri: Uri) {
                try {
                    imageView.setImageURI(uri)
                    removeButton.setOnClickListener {
                        onRemoveClick(uri)
                    }
                } catch (e: Exception) {
                    // Handle error loading image
                    imageView.setImageResource(android.R.drawable.ic_menu_report_image)
                }
            }
        }
    }
}
