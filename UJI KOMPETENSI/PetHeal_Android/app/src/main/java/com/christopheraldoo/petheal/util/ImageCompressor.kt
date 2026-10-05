package com.christopheraldoo.petheal.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.util.Log
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream

private const val TAG = "ImageCompressor"

/**
 * Batas validasi backend untuk field `photo` adalah 4096 KB. Foto kamera
 * modern (8–20 MB) dikirim mentah sehingga selalu ditolak dengan 422
 * "the photo field must not be greater than 4096 kilobytes".
 *
 * Helper ini mengecilkan + mengompres gambar ke file JPEG baru yang
 * DIJAMIN di bawah [maxSizeKB] (default 3072 KB → margin 1 MB dari batas
 * backend untuk overhead multipart). Dipakai sebelum setiap upload foto.
 *
 * @return file terkompresi di cacheDir, atau null bila gagal (caller
 * memakai file asli / menampilkan error ramah).
 */
fun compressImageFile(
    context: Context,
    source: File,
    maxSizeKB: Int = 3072,
    maxDimension: Int = 1600
): File? {
    return try {
        if (!source.exists()) return null
        // Sudah kecil → langsung pakai, tanpa degradasi kualitas.
        if (source.length() <= maxSizeKB * 1024L) return source

        var bitmap = decodeSampledBitmap(source, maxDimension) ?: return null
        bitmap = applyExifOrientation(source, bitmap)

        var quality = 92
        var data: ByteArray
        // Turunkan quality sampai di bawah batas (atau quality mentok).
        while (true) {
            data = bitmapToJpeg(bitmap, quality)
            if (data.size <= maxSizeKB * 1024 || quality <= 40) break
            quality -= 8
        }
        // Masih kebesaran (foto sangat detail) → kecilkan dimensi lalu ulangi.
        var dimension = maxDimension
        while (data.size > maxSizeKB * 1024 && dimension > 512) {
            dimension /= 2
            val scaled = Bitmap.createScaledBitmap(
                bitmap,
                (bitmap.width * dimension / maxDimension).coerceAtLeast(1),
                (bitmap.height * dimension / maxDimension).coerceAtLeast(1),
                true
            )
            if (scaled != bitmap) bitmap.recycle()
            bitmap = applyExifOrientation(source, scaled)
            quality = 85
            while (true) {
                data = bitmapToJpeg(bitmap, quality)
                if (data.size <= maxSizeKB * 1024 || quality <= 40) break
                quality -= 8
            }
        }

        val outFile = File(
            context.cacheDir,
            "compressed_${System.currentTimeMillis()}.jpg"
        )
        FileOutputStream(outFile).use { it.write(data) }
        Log.d(TAG, "Compressed ${source.length() / 1024}KB → ${outFile.length() / 1024}KB (q=$quality)")
        if (outFile.length() > maxSizeKB * 1024L) {
            Log.w(TAG, "Still over limit after compression: ${outFile.length() / 1024}KB")
            outFile.delete()
            return null
        }
        bitmap.recycle()
        outFile
    } catch (e: Exception) {
        Log.e(TAG, "compressImageFile failed", e)
        null
    }
}

/** Decode dengan inSampleSize agar dimensi terpanjang ≤ [maxDimension]. */
private fun decodeSampledBitmap(source: File, maxDimension: Int): Bitmap? {
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeFile(source.absolutePath, bounds)
    if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null

    var inSampleSize = 1
    val longest = maxOf(bounds.outWidth, bounds.outHeight)
    while (longest / inSampleSize > maxDimension) inSampleSize *= 2

    val opts = BitmapFactory.Options().apply { this.inSampleSize = inSampleSize }
    return BitmapFactory.decodeFile(source.absolutePath, opts)
}

/** Perbaiki rotasi foto kamera berdasarkan EXIF agar tidak miring. */
private fun applyExifOrientation(source: File, bitmap: Bitmap): Bitmap {
    val orientation = try {
        ExifInterface(source.absolutePath).getAttributeInt(
            ExifInterface.TAG_ORIENTATION,
            ExifInterface.ORIENTATION_NORMAL
        )
    } catch (_: Exception) {
        ExifInterface.ORIENTATION_NORMAL
    }
    val matrix = Matrix()
    when (orientation) {
        ExifInterface.ORIENTATION_ROTATE_90 -> matrix.postRotate(90f)
        ExifInterface.ORIENTATION_ROTATE_180 -> matrix.postRotate(180f)
        ExifInterface.ORIENTATION_ROTATE_270 -> matrix.postRotate(270f)
        ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> matrix.preScale(-1f, 1f)
        ExifInterface.ORIENTATION_FLIP_VERTICAL -> matrix.preScale(1f, -1f)
        else -> return bitmap
    }
    return try {
        val fixed = Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
        if (fixed != bitmap) bitmap.recycle()
        fixed
    } catch (_: Exception) {
        bitmap
    }
}

private fun bitmapToJpeg(bitmap: Bitmap, quality: Int): ByteArray {
    val stream = ByteArrayOutputStream()
    bitmap.compress(Bitmap.CompressFormat.JPEG, quality, stream)
    return stream.toByteArray()
}
