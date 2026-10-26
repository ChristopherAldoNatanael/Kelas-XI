package com.christopheraldoo.petheal.ui.components

import android.content.ClipData
import android.content.ClipboardManager
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.core.content.FileProvider
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.util.PaymentReceipt
import com.christopheraldoo.petheal.util.ReceiptStatus
import com.christopheraldoo.petheal.util.formatRupiah
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream
import kotlin.math.min

private const val RECEIPT_W = 1080
private const val RECEIPT_H = 1760

// A4 portrait dalam point (72 dpi). PDF tetap vector: canvas PdfDocument
// merekam perintah gambar, bukan menyalin screenshot aplikasi.
private const val PDF_PAGE_W = 595
private const val PDF_PAGE_H = 842
private const val RECEIPT_MARGIN = 72f
private const val CARD_TOP = 300f
private const val CARD_LEFT = 56f
private const val CARD_RIGHT = RECEIPT_W - 56f
private const val CONTENT_LEFT = 112f
private const val CONTENT_RIGHT = RECEIPT_W - 112f

/**
 * Menggambar bukti pembayaran ke PNG. Renderer ini sengaja memakai canvas
 * native supaya hasil simpan/bagikan selalu sama dengan data yang tampil di
 * layar, tanpa menangkap screenshot UI (yang ikut membawa tombol aplikasi).
 */
fun createPaymentReceiptBitmap(receipt: PaymentReceipt, accentColor: Color): Bitmap {
    val bitmap = Bitmap.createBitmap(RECEIPT_W, RECEIPT_H, Bitmap.Config.ARGB_8888)
    drawReceipt(Canvas(bitmap), receipt, accentColor)
    return bitmap
}

/**
 * Bukti pembayaran sebagai PDF A4 portrait.
 *
 * Memakai renderer yang sama dengan PNG (`drawReceipt`) lalu diskalakan utuh
 * ke halaman A4, sehingga layout, tipografi, dan angka tidak mungkin berbeda
 * antar format. Hasilnya vektor — teks tetap tajam saat dicetak.
 */
fun createPaymentReceiptPdf(receipt: PaymentReceipt, accentColor: Color): ByteArray? {
    val document = PdfDocument()
    return try {
        val pageInfo = PdfDocument.PageInfo.Builder(PDF_PAGE_W, PDF_PAGE_H, 1).create()
        val page = document.startPage(pageInfo)
        val canvas = page.canvas

        val scale = min(
            PDF_PAGE_W / RECEIPT_W.toFloat(),
            PDF_PAGE_H / RECEIPT_H.toFloat()
        )
        canvas.save()
        canvas.translate((PDF_PAGE_W - RECEIPT_W * scale) / 2f, 0f)
        canvas.scale(scale, scale)
        drawReceipt(canvas, receipt, accentColor)
        canvas.restore()

        document.finishPage(page)
        ByteArrayOutputStream().use { output ->
            document.writeTo(output)
            output.toByteArray()
        }
    } catch (_: Exception) {
        null
    } finally {
        document.close()
    }
}

/**
 * Menggambar bukti pada canvas berukuran 1080×1760. Satu implementasi untuk
 * PNG (Bitmap) dan PDF (halaman A4) agar tidak ada layout kedua yang bisa
 * menyimpang.
 */
private fun drawReceipt(canvas: Canvas, receipt: PaymentReceipt, accentColor: Color) {
    val accent = accentColor.toArgb()
    val ink = OnBackground.toArgb()
    val secondary = TextSecondary.toArgb()
    val tertiary = TextTertiary.toArgb()
    val outline = Outline.toArgb()
    val surface = Surface.toArgb()
    val background = Background.toArgb()

    canvas.drawColor(background)

    // Header band + ornamental circles.
    canvas.drawRect(0f, 0f, RECEIPT_W.toFloat(), 330f, fill(accent))
    val decoration = fill(withAlpha(Color.White.toArgb(), 18))
    canvas.drawCircle(RECEIPT_W - 70f, 30f, 180f, decoration)
    canvas.drawCircle(70f, 320f, 130f, decoration)

    val eyebrowPaint = textPaint(22f, withAlpha(Color.White.toArgb(), 205), medium = true).apply {
        letterSpacing = 0.10f
    }
    val clinicPaint = textPaint(46f, Color.White.toArgb(), bold = true)
    val addressPaint = textPaint(23f, withAlpha(Color.White.toArgb(), 210))
    val cardPaint = fill(surface).apply {
        setShadowLayer(28f, 0f, 12f, withAlpha(0x000000, 26))
    }

    canvas.drawText("BUKTI PEMBAYARAN", RECEIPT_MARGIN, 92f, eyebrowPaint)
    drawEllipsized(
        canvas,
        receipt.clinicName.ifBlank { "Klinik" },
        RECEIPT_MARGIN,
        158f,
        clinicPaint,
        RECEIPT_W - RECEIPT_MARGIN - 180f
    )
    if (receipt.clinicAddress.isNotBlank()) {
        drawEllipsized(
            canvas,
            receipt.clinicAddress,
            RECEIPT_MARGIN,
            202f,
            addressPaint,
            RECEIPT_W - RECEIPT_MARGIN - 180f
        )
    }
    drawCheck(canvas, RECEIPT_W - 126f, 136f, 42f)

    val card = RectF(CARD_LEFT, CARD_TOP, CARD_RIGHT, RECEIPT_H - 56f)
    canvas.drawRoundRect(card, 44f, 44f, cardPaint)
    cardPaint.clearShadowLayer()
    canvas.drawRoundRect(card, 44f, 44f, stroke(outline, 2f))

    val statusColor = receiptStatusColor(receipt.status, accent)
    val statusPaint = textPaint(22f, statusColor, bold = true)
    val statusTextWidth = statusPaint.measureText(receipt.status.label)
    val pillRight = CONTENT_RIGHT
    val pillLeft = pillRight - statusTextWidth - 48f
    val pillTop = CARD_TOP + 46f
    canvas.drawRoundRect(
        RectF(pillLeft, pillTop, pillRight, pillTop + 48f),
        24f,
        24f,
        fill(withAlpha(statusColor, 24))
    )
    canvas.drawText(receipt.status.label, pillLeft + 24f, pillTop + 32f, statusPaint)

    val invoicePaint = textPaint(26f, secondary, medium = true)
    canvas.drawText(receipt.invoiceNumber, CONTENT_LEFT, CARD_TOP + 78f, invoicePaint)

    val amountLabelPaint = textPaint(21f, tertiary, medium = true).apply {
        letterSpacing = 0.08f
    }
    val amountPaint = textPaint(68f, ink, bold = true)
    val subtitlePaint = textPaint(25f, secondary)

    canvas.drawText(
        if (receipt.status == ReceiptStatus.DP) "DP DIBAYAR" else "TOTAL DIBAYAR",
        CONTENT_LEFT,
        CARD_TOP + 156f,
        amountLabelPaint
    )
    canvas.drawText(
        formatRupiah(receipt.paidAmount),
        CONTENT_LEFT,
        CARD_TOP + 238f,
        amountPaint
    )
    canvas.drawText(
        if (receipt.hasOutstanding) {
            "Sisa ${formatRupiah(receipt.remainingAmount)}"
        } else {
            "Pembayaran lunas — terima kasih"
        },
        CONTENT_LEFT,
        CARD_TOP + 286f,
        subtitlePaint
    )

    drawDashedLine(canvas, CARD_TOP + 336f, CONTENT_LEFT, CONTENT_RIGHT, outline)

    val labelPaint = textPaint(24f, secondary)
    val valuePaint = textPaint(27f, ink, medium = true)
    val detailStart = CARD_TOP + 398f
    drawDetailRow(canvas, "Hewan", receipt.petName, detailStart, labelPaint, valuePaint)
    drawDetailRow(canvas, "Dokter", receipt.doctorName, detailStart + 70f, labelPaint, valuePaint)
    drawDetailRow(canvas, "Layanan", receipt.serviceName, detailStart + 140f, labelPaint, valuePaint)
    drawDetailRow(canvas, "Jadwal", receipt.schedule, detailStart + 210f, labelPaint, valuePaint)
    drawDetailRow(
        canvas,
        "Metode",
        "${receipt.paymentMethod} · ${receipt.paymentType}",
        detailStart + 280f,
        labelPaint,
        valuePaint
    )

    drawDashedLine(canvas, CARD_TOP + 746f, CONTENT_LEFT, CONTENT_RIGHT, outline)

    val amountRowPaint = textPaint(26f, secondary)
    val amountValuePaint = textPaint(27f, ink, medium = true)
    val paidValuePaint = textPaint(27f, statusColor, bold = true)
    var amountY = CARD_TOP + 812f

    // Baris rincian (biaya tindakan/obat pada bukti tambahan rekam medis).
    // Kapasitas terukur: itemLines maks 2 dan dpAmount hanya dipakai bukti
    // booking (tanpa itemLines), jadi baseline maksimum 1076 < garis footer
    // 1148. Geometry kanvas 1080x1760 tidak pernah dilampaui.
    receipt.itemLines.forEach { (label, value) ->
        drawAmountRow(canvas, label, value, amountY, amountRowPaint, amountValuePaint)
        amountY += 66f
    }
    drawAmountRow(canvas, receipt.totalLabel, formatRupiah(receipt.totalAmount), amountY, amountRowPaint, amountValuePaint)
    amountY += 66f
    if (receipt.dpAmount > 0.01) {
        drawAmountRow(canvas, "DP awal", formatRupiah(receipt.dpAmount), amountY, amountRowPaint, amountValuePaint)
        amountY += 66f
    }
    drawAmountRow(canvas, "Dibayar", formatRupiah(receipt.paidAmount), amountY, amountRowPaint, paidValuePaint)
    amountY += 66f
    drawAmountRow(
        canvas,
        "Sisa",
        formatRupiah(receipt.remainingAmount),
        amountY,
        amountRowPaint,
        if (receipt.hasOutstanding) textPaint(27f, WarningDeep.toArgb(), bold = true) else paidValuePaint
    )

    val footerLineY = CARD_TOP + 1148f
    drawDashedLine(canvas, footerLineY, CONTENT_LEFT, CONTENT_RIGHT, outline)

    val footerLabelPaint = textPaint(20f, tertiary)
    val footerValuePaint = textPaint(21f, secondary, medium = true)
    var footerY = footerLineY + 52f
    receipt.reference?.takeIf { it.isNotBlank() }?.let {
        canvas.drawText("Referensi", CONTENT_LEFT, footerY, footerLabelPaint)
        drawEllipsized(canvas, it, CONTENT_LEFT + 210f, footerY, footerValuePaint, CONTENT_RIGHT - CONTENT_LEFT - 210f)
        footerY += 40f
    }
    canvas.drawText("Diterbitkan", CONTENT_LEFT, footerY, footerLabelPaint)
    canvas.drawText(receipt.issuedAt, CONTENT_LEFT + 210f, footerY, footerValuePaint)

    val notePaint = textPaint(20f, tertiary)
    canvas.drawText(
        "Dokumen ini dibuat otomatis dan tidak memerlukan tanda tangan.",
        CONTENT_LEFT,
        CARD_TOP + 1338f,
        notePaint
    )
    canvas.drawText(
        "Terima kasih telah mempercayakan perawatan hewan Anda.",
        CONTENT_LEFT,
        CARD_TOP + 1376f,
        notePaint
    )

    val footerAccent = fill(accent)
    canvas.drawRoundRect(
        RectF(CONTENT_LEFT, CARD_TOP + 1392f, CONTENT_LEFT + 72f, CARD_TOP + 1398f),
        3f,
        3f,
        footerAccent
    )
}

fun saveReceiptToGallery(
    context: Context,
    bitmap: Bitmap,
    fileName: String
): Uri? {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return null
    val resolver = context.contentResolver
    val values = ContentValues().apply {
        put(MediaStore.Images.Media.DISPLAY_NAME, fileName)
        put(MediaStore.Images.Media.MIME_TYPE, "image/png")
        put(
            MediaStore.Images.Media.RELATIVE_PATH,
            "${Environment.DIRECTORY_PICTURES}/PetHeal"
        )
        put(MediaStore.Images.Media.IS_PENDING, 1)
    }
    val uri = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values) ?: return null
    return try {
        resolver.openOutputStream(uri)?.use { out ->
            if (!bitmap.compress(Bitmap.CompressFormat.PNG, 100, out)) {
                throw IllegalStateException("Gagal mengompres gambar")
            }
        } ?: throw IllegalStateException("Gagal membuka file")
        values.clear()
        values.put(MediaStore.Images.Media.IS_PENDING, 0)
        resolver.update(uri, values, null, null)
        uri
    } catch (_: Exception) {
        resolver.delete(uri, null, null)
        null
    }
}

fun writeReceiptToUri(context: Context, bitmap: Bitmap, uri: Uri): Boolean =
    try {
        context.contentResolver.openOutputStream(uri)?.use { out ->
            bitmap.compress(Bitmap.CompressFormat.PNG, 100, out)
        } ?: false
    } catch (_: Exception) {
        false
    }

fun shareReceiptImage(context: Context, bitmap: Bitmap, receipt: PaymentReceipt): Boolean =
    try {
        val dir = File(context.cacheDir, "receipts").apply { mkdirs() }
        val file = File(dir, receipt.fileName())
        FileOutputStream(file).use { out ->
            if (!bitmap.compress(Bitmap.CompressFormat.PNG, 100, out)) {
                return false
            }
        }
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "image/png"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_TEXT, receipt.asText())
            clipData = ClipData.newRawUri("Bukti pembayaran", uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "Bagikan bukti pembayaran"))
        true
    } catch (_: Exception) {
        false
    }

/**
 * Simpan PDF ke Downloads/PetHeal (Android 10+). Android 9 ke bawah memakai
 * pemilih lokasi dari sheet, karena MediaStore.Downloads belum ada.
 */
fun saveReceiptPdfToDownloads(
    context: Context,
    bytes: ByteArray,
    fileName: String
): Uri? {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return null
    val resolver = context.contentResolver
    val values = ContentValues().apply {
        put(MediaStore.Downloads.DISPLAY_NAME, fileName)
        put(MediaStore.Downloads.MIME_TYPE, "application/pdf")
        put(
            MediaStore.Downloads.RELATIVE_PATH,
            "${Environment.DIRECTORY_DOWNLOADS}/PetHeal"
        )
        put(MediaStore.Downloads.IS_PENDING, 1)
    }
    val uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values) ?: return null
    return try {
        resolver.openOutputStream(uri)?.use { it.write(bytes) }
            ?: throw IllegalStateException("Gagal membuka file")
        values.clear()
        values.put(MediaStore.Downloads.IS_PENDING, 0)
        resolver.update(uri, values, null, null)
        uri
    } catch (_: Exception) {
        resolver.delete(uri, null, null)
        null
    }
}

fun writeReceiptPdfToUri(context: Context, bytes: ByteArray, uri: Uri): Boolean =
    try {
        context.contentResolver.openOutputStream(uri)?.use { it.write(bytes) } != null
    } catch (_: Exception) {
        false
    }

fun shareReceiptPdf(context: Context, bytes: ByteArray, receipt: PaymentReceipt): Boolean =
    try {
        val dir = File(context.cacheDir, "receipts").apply { mkdirs() }
        val file = File(dir, receipt.pdfFileName())
        FileOutputStream(file).use { it.write(bytes) }
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "application/pdf"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_TEXT, receipt.asText())
            clipData = ClipData.newRawUri("Bukti pembayaran", uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "Bagikan bukti pembayaran (PDF)"))
        true
    } catch (_: Exception) {
        false
    }

fun copyReceiptText(context: Context, receipt: PaymentReceipt): Boolean =
    try {
        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        clipboard.setPrimaryClip(ClipData.newPlainText("Bukti pembayaran", receipt.asText()))
        true
    } catch (_: Exception) {
        false
    }

private fun receiptStatusColor(status: ReceiptStatus, accent: Int): Int = when (status) {
    ReceiptStatus.PAID -> accent
    ReceiptStatus.DP -> Tertiary.toArgb()
    ReceiptStatus.PARTIAL -> InfoStrong.toArgb()
    ReceiptStatus.PENDING -> WarningDeep.toArgb()
}

private fun drawDetailRow(
    canvas: Canvas,
    label: String,
    value: String,
    baseline: Float,
    labelPaint: Paint,
    valuePaint: Paint
) {
    canvas.drawText(label, CONTENT_LEFT, baseline, labelPaint)
    drawEllipsized(canvas, value, CONTENT_LEFT + 190f, baseline, valuePaint, CONTENT_RIGHT - CONTENT_LEFT - 190f)
}

private fun drawAmountRow(
    canvas: Canvas,
    label: String,
    value: String,
    baseline: Float,
    labelPaint: Paint,
    valuePaint: Paint
) {
    canvas.drawText(label, CONTENT_LEFT, baseline, labelPaint)
    val oldAlign = valuePaint.textAlign
    valuePaint.textAlign = Paint.Align.RIGHT
    canvas.drawText(value, CONTENT_RIGHT, baseline, valuePaint)
    valuePaint.textAlign = oldAlign
}

private fun drawDashedLine(
    canvas: Canvas,
    y: Float,
    startX: Float,
    endX: Float,
    color: Int
) {
    val paint = stroke(color, 2f)
    var x = startX
    while (x < endX) {
        canvas.drawLine(x, y, (x + 14f).coerceAtMost(endX), y, paint)
        x += 26f
    }
}

private fun drawEllipsized(
    canvas: Canvas,
    text: String,
    x: Float,
    baseline: Float,
    paint: Paint,
    maxWidth: Float
) {
    if (text.isBlank() || maxWidth <= 0f) return
    if (paint.measureText(text) <= maxWidth) {
        canvas.drawText(text, x, baseline, paint)
        return
    }
    val ellipsis = "…"
    val available = (maxWidth - paint.measureText(ellipsis)).coerceAtLeast(0f)
    val count = paint.breakText(text, true, available, null)
    canvas.drawText(text.take(count).trimEnd() + ellipsis, x, baseline, paint)
}

private fun drawCheck(canvas: Canvas, cx: Float, cy: Float, radius: Float) {
    canvas.drawCircle(cx, cy, radius, fill(withAlpha(Color.White.toArgb(), 36)))
    val path = Path().apply {
        moveTo(cx - radius * 0.46f, cy)
        lineTo(cx - radius * 0.12f, cy + radius * 0.34f)
        lineTo(cx + radius * 0.50f, cy - radius * 0.34f)
    }
    val paint = stroke(Color.White.toArgb(), 7f).apply {
        strokeCap = Paint.Cap.ROUND
        strokeJoin = Paint.Join.ROUND
    }
    canvas.drawPath(path, paint)
}

private fun textPaint(
    size: Float,
    color: Int,
    bold: Boolean = false,
    medium: Boolean = false
): Paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
    textSize = size
    this.color = color
    typeface = Typeface.create(
        if (medium) "sans-serif-medium" else "sans-serif",
        if (bold) Typeface.BOLD else Typeface.NORMAL
    )
}

private fun fill(color: Int): Paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
    this.color = color
    style = Paint.Style.FILL
}

private fun stroke(color: Int, width: Float): Paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
    this.color = color
    style = Paint.Style.STROKE
    strokeWidth = width
}

private fun withAlpha(color: Int, alpha: Int): Int =
    (color and 0x00FFFFFF) or ((alpha.coerceIn(0, 255)) shl 24)
