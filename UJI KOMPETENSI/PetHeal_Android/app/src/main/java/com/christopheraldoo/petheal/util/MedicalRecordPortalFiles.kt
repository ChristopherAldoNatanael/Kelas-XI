package com.christopheraldoo.petheal.util

import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.core.content.FileProvider
import java.io.File

sealed class MedicalRecordPdfResult {
    data class Saved(val displayName: String) : MedicalRecordPdfResult()
    data object Opened : MedicalRecordPdfResult()
    data object Failed : MedicalRecordPdfResult()
}

/**
 * Simpan salinan rekam medis ke Downloads/PetHeal pada Android 10+.
 * Android 7–9 membuka PDF dari cache lewat FileProvider karena MediaStore
 * Downloads belum tersedia. Tidak ada izin storage lebar yang diminta.
 */
fun saveOrOpenMedicalRecordPdf(
    context: Context,
    bytes: ByteArray,
    recordId: Int
): MedicalRecordPdfResult = saveOrOpenPdf(context, bytes, "rekam-medis-$recordId.pdf")

/**
 * Simpan/buka PDF generik (mis. salinan data PDP) tanpa izin storage lebar.
 */
fun saveOrOpenPdf(
    context: Context,
    bytes: ByteArray,
    fileName: String
): MedicalRecordPdfResult {
    if (bytes.isEmpty()) return MedicalRecordPdfResult.Failed

    val name = fileName.takeIf { it.endsWith(".pdf", ignoreCase = true) } ?: "$fileName.pdf"

    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        val resolver = context.contentResolver
        val values = ContentValues().apply {
            put(MediaStore.Downloads.DISPLAY_NAME, name)
            put(MediaStore.Downloads.MIME_TYPE, "application/pdf")
            put(MediaStore.Downloads.RELATIVE_PATH, "${Environment.DIRECTORY_DOWNLOADS}/PetHeal")
            put(MediaStore.Downloads.IS_PENDING, 1)
        }
        val uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
            ?: return MedicalRecordPdfResult.Failed

        return try {
            resolver.openOutputStream(uri)?.use { it.write(bytes) }
                ?: throw IllegalStateException("Gagal membuka berkas")
            values.clear()
            values.put(MediaStore.Downloads.IS_PENDING, 0)
            resolver.update(uri, values, null, null)
            MedicalRecordPdfResult.Saved(name)
        } catch (_: Exception) {
            resolver.delete(uri, null, null)
            MedicalRecordPdfResult.Failed
        }
    }

    return try {
        val dir = File(context.cacheDir, "medical-records").apply { mkdirs() }
        val file = File(dir, name)
        file.writeBytes(bytes)
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, "application/pdf")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(intent)
        MedicalRecordPdfResult.Opened
    } catch (_: Exception) {
        MedicalRecordPdfResult.Failed
    }
}

/** Ekspos hanya untuk pengujian/utility tanpa membuka file picker. */
fun medicalRecordPdfCacheUri(context: Context, recordId: Int): Uri? = try {
    val dir = File(context.cacheDir, "medical-records").apply { mkdirs() }
    val file = File(dir, "rekam-medis-$recordId.pdf")
    FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
} catch (_: Exception) {
    null
}
