package com.christopheraldoo.bukuringkasapp.ui.screens

import android.Manifest
import android.content.pm.PackageManager
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavController
import coil.compose.AsyncImage
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.data.repository.OcrService
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue
import com.christopheraldoo.bukuringkasapp.viewmodel.ScanViewModel
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Scan Screen - Untuk memfoto dan scan buku pelajaran
 * Menggunakan kamera dan OCR untuk membaca teks dari gambar
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ScanScreen(navController: NavController) {
    val context = LocalContext.current
    var capturedImageUri by remember { mutableStateOf<Uri?>(null) }
    var extractedText by remember { mutableStateOf("") }
    var isProcessing by remember { mutableStateOf(false) }
    var showResultDialog by remember { mutableStateOf(false) }
    var summaryResult by remember { mutableStateOf<SummaryData?>(null) }

    // Camera permission launcher
    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            // Permission granted, open camera
            openCamera(context) { uri ->
                capturedImageUri = uri
            }
        }
    }

    // Gallery launcher for selecting images
    val galleryLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri ->
        capturedImageUri = uri
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Header
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(PrimaryBlue)
                .padding(24.dp)
        ) {
            Column {
                Text(
                    text = "📸 Scan Buku Pelajaran",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Foto halaman buku untuk diekstrak teks dan diringkas",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
        }

        // Main Content
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Camera Section
            item {
                CameraSection(
                    capturedImageUri = capturedImageUri,
                    onTakePhoto = {
                        when (PackageManager.PERMISSION_GRANTED) {
                            ContextCompat.checkSelfPermission(
                                context,
                                Manifest.permission.CAMERA
                            ) -> {
                                openCamera(context) { uri ->
                                    capturedImageUri = uri
                                }
                            }
                            else -> {
                                cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                            }
                        }
                    },
                    onSelectFromGallery = {
                        galleryLauncher.launch("image/*")
                    },
                    onRemoveImage = {
                        capturedImageUri = null
                        extractedText = ""
                    }
                )
            }

            // Extracted Text Section
            if (capturedImageUri != null) {
                item {
                    ExtractedTextSection(
                        extractedText = extractedText,
                        isProcessing = isProcessing,
                        onExtractText = {
                            capturedImageUri?.let { uri ->
                                isProcessing = true
                                val ocrService = OcrService(context)

                                kotlinx.coroutines.CoroutineScope(
                                    kotlinx.coroutines.Dispatchers.IO
                                ).launch {
                                    try {
                                        val result = ocrService.extractTextFromUri(uri)
                                        withContext(kotlinx.coroutines.Dispatchers.Main) {
                                            result.fold(
                                                onSuccess = { text ->
                                                    extractedText = ocrService.cleanOcrText(text)
                                                },
                                                onFailure = { error ->
                                                    // Handle error silently
                                                }
                                            )
                                            isProcessing = false
                                        }
                                    } catch (e: Exception) {
                                        withContext(kotlinx.coroutines.Dispatchers.Main) {
                                            isProcessing = false
                                            // Show error toast or message
                                        }
                                    }
                                }
                            }
                        }
                    )
                }
            }

            // Action Buttons
            if (extractedText.isNotEmpty()) {
                item {
                    ActionButtonsSection(
                        onSummarize = {
                            // Process text and show result
                            processTextAndShowResult(extractedText) { result ->
                                summaryResult = result
                                showResultDialog = true
                            }
                        },
                        onAskQuestion = {
                            // Navigate to Ask AI screen with context
                            navController.navigate("ask_ai")
                        }
                    )
                }
            }

            // Tips Section
            item {
                ScanTipsCard()
            }

            // Bottom spacing
            item {
                Spacer(modifier = Modifier.height(32.dp))
            }
        }
    }

    // Result Dialog
    if (showResultDialog && summaryResult != null) {
        SummaryResultDialog(
            summary = summaryResult!!,
            onDismiss = { showResultDialog = false },
            onSave = {
                // Save to history
                showResultDialog = false
            }
        )
    }
}

/**
 * Section untuk kamera dan pemilihan gambar
 */
@Composable
fun CameraSection(
    capturedImageUri: Uri?,
    onTakePhoto: () -> Unit,
    onSelectFromGallery: () -> Unit,
    onRemoveImage: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "Pilih Gambar",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(16.dp))

            if (capturedImageUri != null) {
                // Show captured image
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(MaterialTheme.colorScheme.surfaceVariant),
                    contentAlignment = Alignment.Center
                ) {
                    AsyncImage(
                        model = capturedImageUri,
                        contentDescription = "Captured image",
                        modifier = Modifier
                            .fillMaxSize()
                            .clip(RoundedCornerShape(12.dp))
                    )

                    // Remove button
                    IconButton(
                        onClick = onRemoveImage,
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(8.dp)
                            .background(
                                MaterialTheme.colorScheme.error.copy(alpha = 0.8f),
                                CircleShape
                            )
                    ) {
                        Icon(
                            painter = painterResource(id = android.R.drawable.ic_menu_close_clear_cancel),
                            contentDescription = "Remove image",
                            tint = Color.White
                        )
                    }
                }
            } else {
                // Show placeholder
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(MaterialTheme.colorScheme.surfaceVariant),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            painter = painterResource(id = android.R.drawable.ic_menu_camera),
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "Belum ada gambar",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Action buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedButton(
                    onClick = onTakePhoto,
                    modifier = Modifier.weight(1f)
                ) {
                    Icon(
                        painter = painterResource(id = android.R.drawable.ic_menu_camera),
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Kamera")
                }

                OutlinedButton(
                    onClick = onSelectFromGallery,
                    modifier = Modifier.weight(1f)
                ) {
                    Icon(
                        painter = painterResource(id = android.R.drawable.ic_menu_gallery),
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Galeri")
                }
            }
        }
    }
}

/**
 * Section untuk menampilkan teks yang diekstrak
 */
@Composable
fun ExtractedTextSection(
    extractedText: String,
    isProcessing: Boolean,
    onExtractText: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Teks yang Diekstrak",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )

                if (extractedText.isEmpty() && !isProcessing) {
                    Button(
                        onClick = onExtractText,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = PrimaryBlue
                        )
                    ) {
                        Text("Ekstrak Teks")
                    }
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            if (isProcessing) {
                // Loading state
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(120.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        androidx.compose.material3.CircularProgressIndicator(
                            color = PrimaryBlue
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "Memproses gambar...",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                        )
                    }
                }
            } else if (extractedText.isNotEmpty()) {
                // Show extracted text
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(120.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                        .padding(12.dp)
                ) {
                    Text(
                        text = extractedText.take(300) + if (extractedText.length > 300) "..." else "",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
            }
        }
    }
}

/**
 * Section untuk tombol aksi setelah teks diekstrak
 */
@Composable
fun ActionButtonsSection(
    onSummarize: () -> Unit,
    onAskQuestion: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "Aksi",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(16.dp))

            Button(
                onClick = onSummarize,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = PrimaryBlue
                )
            ) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_menu_edit),
                    contentDescription = null,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Ringkas Teks")
            }

            Spacer(modifier = Modifier.height(12.dp))

            OutlinedButton(
                onClick = onAskQuestion,
                modifier = Modifier.fillMaxWidth()
            ) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_menu_help),
                    contentDescription = null,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Tanya tentang Teks Ini")
            }
        }
    }
}

/**
 * Dialog untuk menampilkan hasil ringkasan
 */
@Composable
fun SummaryResultDialog(
    summary: SummaryData,
    onDismiss: () -> Unit,
    onSave: () -> Unit
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface
            )
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                Text(
                    text = "✅ Ringkasan Berhasil!",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = summary.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )

                Text(
                    text = "${summary.subject} • Kelas ${summary.grade}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )

                Spacer(modifier = Modifier.height(12.dp))

                Text(
                    text = "Konsep Utama:",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )

                Text(
                    text = summary.mainConcept,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(vertical = 8.dp)
                )

                Spacer(modifier = Modifier.height(16.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    OutlinedButton(onClick = onDismiss) {
                        Text("Tutup")
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = onSave,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = PrimaryBlue
                        )
                    ) {
                        Text("Simpan")
                    }
                }
            }
        }
    }
}

/**
 * Card untuk tips scan yang baik
 */
@Composable
fun ScanTipsCard() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.1f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "💡 Tips Foto yang Bagus",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(12.dp))

            listOf(
                "📸 Pastikan pencahayaan cukup dan merata",
                "📐 Foto tegak lurus dengan halaman buku",
                "🎯 Fokus pada teks yang ingin diekstrak",
                "📱 Gunakan mode makro jika tersedia",
                "✋ Hindari bayangan dan refleksi"
            ).forEach { tip ->
                Text(
                    text = tip,
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.padding(vertical = 2.dp)
                )
            }
        }
    }
}

// Helper functions
private fun openCamera(context: android.content.Context, onImageCaptured: (Uri) -> Unit) {
    try {
        val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        val storageDir = File(context.cacheDir, "images").apply { mkdirs() }
        val photoFile = File(storageDir, "JPEG_${timeStamp}_.jpg")
        val photoURI = FileProvider.getUriForFile(
            context,
            "${context.packageName}.provider",
            photoFile
        )
        onImageCaptured(photoURI)
    } catch (e: Exception) {
        // Handle error
    }
}

private fun processTextAndShowResult(text: String, onResult: (SummaryData) -> Unit) {
    // Simple processing without ViewModel for now
    kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.Main).launch {
        kotlinx.coroutines.delay(1500) // Simulate processing
        
        val summary = SummaryData(
            title = "Ringkasan Materi",
            subject = "Fisika",
            grade = 11,
            mainConcept = "Hukum Newton tentang gerak adalah prinsip dasar mekanika klasik yang menjelaskan hubungan antara gaya yang bekerja pada benda dan percepatan yang dihasilkan.",
            keyPoints = listOf(
                com.christopheraldoo.bukuringkasapp.data.model.KeyPoint(
                    title = "Hukum I Newton",
                    explanation = "Benda diam tetap diam, benda bergerak lurus beraturan tetap demikian kecuali ada gaya luar"
                ),
                com.christopheraldoo.bukuringkasapp.data.model.KeyPoint(
                    title = "Hukum II Newton",
                    explanation = "Percepatan benda sebanding dengan gaya resultan dan berbanding terbalik dengan massa"
                )
            ),
            keywords = listOf("gaya", "massa", "percepatan", "gerak", "mekanika")
        )
        
        onResult(summary)
    }
}

// Sample OCR text untuk testing
const val SAMPLE_OCR_TEXT = """
Hukum Newton tentang Gerak

Gerak adalah perubahan posisi suatu benda terhadap waktu berjalan. Ada beberapa jenis gerak yaitu gerak lurus beraturan (GLB), gerak lurus berubah beraturan (GLBB), dan gerak melingkar.

Hukum Newton yang pertama: Suatu benda akan tetap dalam keadaan diam atau gerak lurus beraturan jika tidak ada gaya yang bekerja pada benda tersebut atau jika resultan gaya yang bekerja pada benda tersebut sama dengan nol.

F = m * a

Contoh: Sebuah mobil balap melaju dengan konstan 100 km/jam. Jika tidak ada gesekan atau gaya lain, maka mobil tersebut akan terus melaju dengan kecepatan konstan tersebut.

Kunci penting: Perubahan gerak suatu benda disebabkan oleh gaya.
"""
