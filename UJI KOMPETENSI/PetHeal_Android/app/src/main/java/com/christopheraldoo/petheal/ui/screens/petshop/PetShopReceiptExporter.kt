package com.christopheraldoo.petheal.ui.screens.petshop

import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Typeface
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.core.content.FileProvider
import com.christopheraldoo.petheal.data.model.PetShopReceipt
import java.io.File
import java.io.FileOutputStream
import java.text.NumberFormat
import java.util.Locale

/**
 * Bukti pembayaran Pet Shop: render PNG + simpan/bagikan file PDF & PNG.
 *
 * PNG digambar langsung ke Canvas (bukan screenshot layar) supaya hasilnya
 * stabil di semua ukuran layar, resolusi cukup, dan tidak ada elemen terpotong.
 * File sementara disimpan di cache dan dibagikan lewat FileProvider; PDF yang
 * diunduh berasal dari endpoint backend (bukan dibuat di client).
 */
object PetShopReceiptExporter {

    private const val BITMAP_WIDTH = 1080

    private val rupiah: NumberFormat = NumberFormat.getNumberInstance(Locale("id", "ID")).apply {
        maximumFractionDigits = 0
    }

    fun fileName(receipt: PetShopReceipt, extension: String): String {
        val safe = receipt.orderNumber.replace(Regex("[^A-Za-z0-9\\-]"), "")
        val number = safe.ifBlank { receipt.receiptNumber.ifBlank { "ORDER" } }
        return "PetHeal-Receipt-$number.$extension"
    }

    // ── PNG ────────────────────────────────────────────────────────────────

    fun renderBitmap(receipt: PetShopReceipt): Bitmap {
        val margin = 56f
        val width = BITMAP_WIDTH
        val body = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = Color.parseColor("#334155")
            textSize = 30f
            typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.NORMAL)
        }
        val small = Paint(body).apply { textSize = 26f }
        val label = Paint(body).apply { textSize = 26f; color = Color.parseColor("#64748B") }
        val bold = Paint(body).apply {
            color = Color.parseColor("#0F172A")
            textSize = 34f
            typeface = Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD)
        }
        val h1 = Paint(bold).apply { textSize = 52f; color = Color.parseColor("#0F766E") }
        val h2 = Paint(bold).apply { textSize = 36f }
        val badge = Paint(bold).apply {
            textSize = 28f
            color = if (receipt.isSettled) Color.parseColor("#166534") else Color.parseColor("#92400E")
        }
        val rule = Paint().apply { color = Color.parseColor("#E2E8F0"); strokeWidth = 2f }

        // Hitung tinggi total lebih dulu agar tidak ada teks terpotong.
        var height = margin
        height += 60f // header
        height += 70f // meta
        height += 60f + receipt.items.size * 92f
        height += 220f // totals
        height += 190f // pickup box
        height += 220f // pembayaran + footer
        height += margin

        val bitmap = Bitmap.createBitmap(width, height.toInt(), Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bitmap)
        canvas.drawColor(Color.WHITE)

        var y = margin + 46f
        canvas.drawText("PetHeal", margin, y, h1)
        y += 36f
        canvas.drawText("Bukti Pembayaran Pet Shop", margin, y, small)
        canvas.drawText(
            if (receipt.isSettled) "LUNAS" else "MENUNGGU PEMBAYARAN",
            width - margin - badge.measureText(if (receipt.isSettled) "LUNAS" else "MENUNGGU PEMBAYARAN"),
            margin + 44f,
            badge
        )
        canvas.drawText(receipt.receiptNumber, margin, y + 34f, label)

        y += 46f
        canvas.drawLine(margin, y, width - margin, y, rule)
        y += 40f
        canvas.drawText(receipt.clinic?.name.orEmpty(), margin, y, h2)
        y += 34f
        drawWrapped(canvas, receipt.clinic?.address.orEmpty(), margin, y, width - margin * 2, small, 3)
        y += 34f
        canvas.drawText("No. pesanan: ${receipt.orderNumber}", margin, y, small)
        y += 32f
        canvas.drawText("Pelanggan: ${receipt.customer?.name.orEmpty()}", margin, y, small)
        y += 32f
        canvas.drawText(
            "Tanggal: ${formatDate(receipt.timeline?.createdAt)}",
            margin,
            y,
            small
        )

        y += 46f
        canvas.drawLine(margin, y, width - margin, y, rule)
        y += 40f
        canvas.drawText("PRODUK", margin, y, label)
        y += 18f

        receipt.items.forEach { item ->
            y += 36f
            val name = listOfNotNull(item.productName, item.variantName?.takeIf { it.isNotBlank() })
                .joinToString(" — ")
            drawWrapped(canvas, name, margin, y, width - margin * 2 - 220f, body, 2)
            val qtyLabel = "x${item.quantity}"
            canvas.drawText(qtyLabel, width - margin - 210f, y, small)
            canvas.drawText("Rp ${rupiah.format(item.subtotal)}", width - margin - bold.measureText("Rp ${rupiah.format(item.subtotal)}"), y, body)
            y += 34f
            canvas.drawText("Rp ${rupiah.format(item.unitPrice)} / item", margin, y, label)
            y += 22f
        }

        y += 46f
        canvas.drawLine(margin, y, width - margin, y, rule)
        y += 40f

        val totals = receipt.totals
        if (totals != null) {
            y = drawTotalRow(canvas, "Subtotal", totals.subtotal, y, label, body, width, margin)
            if (totals.discountTotal > 0) {
                y = drawTotalRow(canvas, "Diskon", -totals.discountTotal, y, label, body, width, margin)
            }
            y = drawTotalRow(canvas, "Total", totals.totalAmount, y, label, bold, width, margin)
            if (totals.paidAmount > 0) {
                y = drawTotalRow(canvas, "Diterima", totals.paidAmount, y, label, body, width, margin)
            }
        }

        y += 40f
        canvas.drawLine(margin, y, width - margin, y, rule)
        y += 42f
        canvas.drawText("PEMBAYARAN", margin, y, label)
        y += 34f
        canvas.drawText(receipt.payment?.methodLabel ?: receipt.payment?.method.orEmpty(), margin, y, body)
        y += 30f
        canvas.drawText("Status: ${receipt.statusLabel.orEmpty()}", margin, y, small)
        receipt.payment?.paidAt?.let { paidAt ->
            y += 30f
            canvas.drawText("Dibayar: ${formatDate(paidAt)}", margin, y, small)
        }
        receipt.payment?.paymentCode?.let { code ->
            y += 30f
            canvas.drawText("Kode pembayaran: $code", margin, y, small)
        }

        y += 48f
        canvas.drawRect(margin, y, width - margin, y + 150f, Paint().apply { color = Color.parseColor("#F8FAFC") })
        y += 40f
        canvas.drawText("PENGAMBILAN", margin + 24f, y, label)
        y += 32f
        drawWrapped(canvas, receipt.pickup?.clinicName.orEmpty(), margin + 24f, y, width - margin * 2 - 48f, body, 2)
        y += 32f
        drawWrapped(canvas, "Produk diambil langsung di klinik. Tunjukkan QR Pickup Pass atau kode pengambilan kepada petugas.", margin + 24f, y, width - margin * 2 - 48f, small, 2)

        y += 190f
        canvas.drawText(
            "Bukti pembayaran dibuat otomatis oleh PetHeal.",
            margin,
            y,
            label
        )

        return bitmap
    }

    fun savePngToGallery(context: Context, bitmap: Bitmap, fileName: String): Boolean {
        return runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Images.Media.DISPLAY_NAME, fileName)
                    put(MediaStore.Images.Media.MIME_TYPE, "image/png")
                    put(MediaStore.Images.Media.RELATIVE_PATH, Environment.DIRECTORY_PICTURES + "/PetHeal")
                }
                val uri = context.contentResolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values)
                    ?: return false
                context.contentResolver.openOutputStream(uri)?.use { it.write(bitmapToBytes(bitmap)) }
                true
            } else {
                val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_PICTURES), "PetHeal").apply { mkdirs() }
                val file = File(dir, fileName)
                FileOutputStream(file).use { it.write(bitmapToBytes(bitmap)) }
                true
            }
        }.getOrDefault(false)
    }

    fun sharePng(context: Context, bitmap: Bitmap, fileName: String) {
        val file = cacheFile(context, fileName)
        FileOutputStream(file).use { it.write(bitmapToBytes(bitmap)) }
        shareFile(context, file, "image/png", "Bagikan bukti pembayaran")
    }

    // ── PDF ────────────────────────────────────────────────────────────────

    fun savePdfToDownloads(context: Context, bytes: ByteArray, fileName: String): Boolean {
        return runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Downloads.DISPLAY_NAME, fileName)
                    put(MediaStore.Downloads.MIME_TYPE, "application/pdf")
                    put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS + "/PetHeal")
                }
                val uri = context.contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
                    ?: return false
                context.contentResolver.openOutputStream(uri)?.use { it.write(bytes) }
                true
            } else {
                val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "PetHeal").apply { mkdirs() }
                File(dir, fileName).writeBytes(bytes)
                true
            }
        }.getOrDefault(false)
    }

    fun sharePdf(context: Context, bytes: ByteArray, fileName: String) {
        val file = cacheFile(context, fileName)
        file.writeBytes(bytes)
        shareFile(context, file, "application/pdf", "Bagikan bukti pembayaran")
    }

    // ── Util ───────────────────────────────────────────────────────────────

    private fun shareFile(context: Context, file: File, mime: String, title: String) {
        val uri: Uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )

        val intent = Intent(Intent.ACTION_SEND).apply {
            type = mime
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }

        // Context aplikasi dipakai (bukan Activity), jadi chooser butuh task baru.
        context.startActivity(
            Intent.createChooser(intent, title).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        )
    }

    private fun cacheFile(context: Context, fileName: String): File {
        val dir = File(context.cacheDir, "receipts").apply { mkdirs() }
        return File(dir, fileName)
    }

    private fun bitmapToBytes(bitmap: Bitmap): ByteArray {
        return java.io.ByteArrayOutputStream().use { stream ->
            bitmap.compress(Bitmap.CompressFormat.PNG, 100, stream)
            stream.toByteArray()
        }
    }

    private fun drawTotalRow(
        canvas: Canvas,
        label: String,
        amount: Long,
        y: Float,
        labelPaint: Paint,
        valuePaint: Paint,
        width: Int,
        margin: Float
    ): Float {
        canvas.drawText(label, margin, y, labelPaint)
        val value = "Rp ${rupiah.format(amount)}"
        canvas.drawText(value, width - margin - valuePaint.measureText(value), y, valuePaint)
        return y + 40f
    }

    private fun drawWrapped(
        canvas: Canvas,
        text: String,
        x: Float,
        y: Float,
        maxWidth: Float,
        paint: Paint,
        maxLines: Int
    ) {
        if (text.isBlank()) return
        var line = ""
        var lines = 0

        text.split(" ").forEach { word ->
            val candidate = if (line.isEmpty()) word else "$line $word"
            if (paint.measureText(candidate) > maxWidth && line.isNotEmpty()) {
                if (lines < maxLines) {
                    canvas.drawText(line, x, y + lines * 34f, paint)
                    lines++
                }
                line = word
            } else {
                line = candidate
            }
        }

        if (line.isNotEmpty() && lines < maxLines) {
            canvas.drawText(line, x, y + lines * 34f, paint)
        }
    }

    private fun formatDate(iso: String?): String {
        if (iso.isNullOrBlank()) return "-"
        return runCatching {
            val parsed = java.time.OffsetDateTime.parse(iso)
                .atZoneSameInstant(java.time.ZoneId.of("Asia/Jakarta"))
            parsed.format(java.time.format.DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm", Locale("id", "ID")))
        }.getOrDefault(iso)
    }
}
