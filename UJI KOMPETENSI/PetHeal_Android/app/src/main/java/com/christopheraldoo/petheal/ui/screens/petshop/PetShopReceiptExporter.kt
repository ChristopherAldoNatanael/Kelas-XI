package com.christopheraldoo.petheal.ui.screens.petshop

import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.DashPathEffect
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.core.content.FileProvider
import com.christopheraldoo.petheal.data.model.PetShopReceipt
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.math.min

/**
 * Bukti pembayaran Pet Shop.
 *
 * Satu mesin gambar dipakai untuk dua format sekaligus:
 *
 * - **PNG** — kartu struk 1080 px yang dibagikan/diunggah ke galeri.
 * - **PDF** — halaman dokumen dengan teks vektor, dibuat di perangkat.
 *
 * PDF sengaja dirender di sisi aplikasi, bukan diambil dari server: hasilnya
 * tetap bisa diunduh saat jaringan buruk, bentuknya identik dengan PNG, dan
 * ukuran halaman mengikuti panjang isi sehingga tidak pernah terpotong.
 *
 * Semua angka berasal dari payload bukti pembayaran server. Renderer tidak
 * pernah menyimpulkan "lunas" sendiri: label LUNAS hanya muncul saat
 * `receipt.isSettled == true`.
 */
object PetShopReceiptExporter {

    private const val PNG_WIDTH = 1080

    // Palet mengikuti token aplikasi (Color.kt) tetapi ditulis sebagai Int
    // karena Paint membutuhkan warna ARGB mentah di luar Compose.
    private const val INK = 0xFF0F172A.toInt()
    private const val MUTED = 0xFF64748B.toInt()
    private const val FAINT = 0xFF94A3B8.toInt()
    private const val LINE = 0xFFE2E8F0.toInt()
    private const val LINE_SOFT = 0xFFEEF2F6.toInt()
    private const val BRAND = 0xFF087A3A.toInt()
    private const val BRAND_BRIGHT = 0xFF18C964.toInt()
    private const val BRAND_SOFT = 0xFFE8F9EF.toInt()
    private const val PANEL = 0xFFF6F8F6.toInt()
    private const val WARN_SOFT = 0xFFFEF3C7.toInt()
    private const val WARN_INK = 0xFF92400E.toInt()
    private const val PAGE_BG = 0xFFF1F5F4.toInt()

    private val rupiah: NumberFormat = NumberFormat.getNumberInstance(Locale("id", "ID")).apply {
        maximumFractionDigits = 0
    }

    fun fileName(receipt: PetShopReceipt, extension: String): String {
        val safe = receipt.orderNumber.replace(Regex("[^A-Za-z0-9\\-]"), "")
        val number = safe.ifBlank { receipt.receiptNumber.replace(Regex("[^A-Za-z0-9\\-]"), "") }
        return "PetHeal-Receipt-${number.ifBlank { "ORDER" }}.$extension"
    }

    // ── PNG ────────────────────────────────────────────────────────────────

    /** Kartu struk siap bagikan: latar lembut, kartu putih, pita merek. */
    fun renderPng(receipt: PetShopReceipt): Bitmap {
        val inset = 40f
        val cardPadding = 60f
        val contentLeft = inset + cardPadding
        val contentWidth = PNG_WIDTH - contentLeft * 2
        val cardTop = inset
        val contentTop = cardTop + 12f + cardPadding

        // Pass 1: ukur tinggi isi.
        val probe = Canvas(Bitmap.createBitmap(PNG_WIDTH, 16, Bitmap.Config.ARGB_8888))
        val bottom = drawReceipt(probe, receipt, contentLeft, contentWidth, contentTop)

        val height = (bottom + cardPadding + inset + 26f).toInt()
        val bitmap = Bitmap.createBitmap(PNG_WIDTH, height, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bitmap)
        canvas.drawColor(PAGE_BG)

        val cardRect = RectF(inset, cardTop, PNG_WIDTH - inset, height - inset)
        val cardRadius = 34f

        // Bayangan kartu digambar sebagai beberapa lapis tepi transparan:
        // lebih murah dan hasilnya sama halusnya dengan shadow layer.
        val shadow = Paint(Paint.ANTI_ALIAS_FLAG).apply { style = Paint.Style.STROKE }
        for (i in 12 downTo 1) {
            shadow.color = Color.argb((10 - i) * 2, 15, 23, 42)
            shadow.strokeWidth = i * 2.6f
            canvas.drawRoundRect(cardRect, cardRadius, cardRadius, shadow)
        }

        val cardPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.WHITE }
        canvas.drawRoundRect(cardRect, cardRadius, cardRadius, cardPaint)

        // Pita merek di bibir kartu, dipotong mengikuti sudut kartu.
        canvas.save()
        canvas.clipPath(Path().apply { addRoundRect(cardRect, cardRadius, cardRadius, Path.Direction.CW) })
        canvas.drawRect(cardRect.left, cardRect.top, cardRect.right, cardRect.top + 10f,
            Paint().apply { color = BRAND_BRIGHT })
        canvas.restore()

        drawReceipt(canvas, receipt, contentLeft, contentWidth, contentTop)
        return bitmap
    }

    fun savePngToGallery(context: Context, bitmap: Bitmap, fileName: String): Boolean {
        return runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Images.Media.DISPLAY_NAME, fileName)
                    put(MediaStore.Images.Media.MIME_TYPE, "image/png")
                    put(
                        MediaStore.Images.Media.RELATIVE_PATH,
                        Environment.DIRECTORY_PICTURES + "/PetHeal"
                    )
                }
                val uri = context.contentResolver
                    .insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values)
                    ?: return false
                context.contentResolver.openOutputStream(uri)?.use {
                    it.write(bitmapToBytes(bitmap))
                } ?: return false
                true
            } else {
                // Android 7–9: tidak ada MediaStore.RelativePath, simpan ke folder
                // gambar milik aplikasi agar tetap muncul di Galeri.
                val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_PICTURES), "PetHeal")
                    .apply { mkdirs() }
                FileOutputStream(File(dir, fileName)).use { it.write(bitmapToBytes(bitmap)) }
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

    /**
     * PDF satu halaman A4 dengan teks vektor.
     *
     * Isi digambar dengan komposisi yang sama seperti kartu PNG, lalu diperkecil
     * hanya bila perlu supaya seluruh struk muat di satu lembar A4 — tidak ada
     * halaman kedua berisi potongan total, dan tidak ada teks yang terpotong.
     */
    fun renderPdf(receipt: PetShopReceipt): ByteArray {
        val pageWidth = 595f // A4 dalam poin
        val pageHeight = 842f
        val margin = 40f
        val contentWidth = pageWidth - margin * 2
        val top = 40f

        val probe = Canvas(Bitmap.createBitmap(pageWidth.toInt(), 16, Bitmap.Config.ARGB_8888))
        val contentBottom = drawReceipt(probe, receipt, margin, contentWidth, top)
        val contentHeight = contentBottom + top
        val scale = min(1f, pageHeight / contentHeight)

        val document = PdfDocument()
        val page = document.startPage(
            PdfDocument.PageInfo.Builder(pageWidth.toInt(), pageHeight.toInt(), 1).create()
        )
        val canvas = page.canvas
        canvas.drawColor(Color.WHITE)
        canvas.save()
        // Saat diperkecil, isi tetap di tengah secara horizontal.
        canvas.translate((pageWidth - pageWidth * scale) / 2f, 0f)
        canvas.scale(scale, scale)
        drawReceipt(canvas, receipt, margin, contentWidth, top)
        canvas.restore()
        document.finishPage(page)

        return ByteArrayOutputStream().use { out ->
            document.writeTo(out)
            document.close()
            out.toByteArray()
        }
    }

    fun savePdfToDownloads(context: Context, bytes: ByteArray, fileName: String): Boolean {
        return runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Downloads.DISPLAY_NAME, fileName)
                    put(MediaStore.Downloads.MIME_TYPE, "application/pdf")
                    put(
                        MediaStore.Downloads.RELATIVE_PATH,
                        Environment.DIRECTORY_DOWNLOADS + "/PetHeal"
                    )
                }
                val uri = context.contentResolver
                    .insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
                    ?: return false
                context.contentResolver.openOutputStream(uri)?.use { it.write(bytes) } ?: return false
                true
            } else {
                val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "PetHeal")
                    .apply { mkdirs() }
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

    // ── Mesin gambar (dipakai PNG & PDF) ───────────────────────────────────

    /**
     * Menggambar seluruh isi struk dan mengembalikan posisi Y akhir.
     *
     * Semua ukuran bersifat proporsional terhadap lebar konten (`u` = 1% lebar),
     * sehingga fungsi yang sama menghasilkan kartu 1080 px dan halaman A4 yang
     * ritmenya identik. Warna isi mengikuti token merek, tidak ada gradasi.
     */
    private fun drawReceipt(
        canvas: Canvas,
        receipt: PetShopReceipt,
        left: Float,
        contentWidth: Float,
        top: Float
    ): Float {
        val u = contentWidth / 100f
        val right = left + contentWidth
        var y = top

        fun text(
            value: String,
            x: Float,
            baseline: Float,
            size: Float,
            color: Int,
            bold: Boolean = false,
            letterSpacing: Float = 0f,
            align: Paint.Align = Paint.Align.LEFT
        ) {
            val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                this.color = color
                textSize = size
                isAntiAlias = true
                textAlign = align
                typeface = if (bold) {
                    Typeface.create("sans-serif-medium", Typeface.BOLD)
                } else {
                    Typeface.create("sans-serif", Typeface.NORMAL)
                }
                if (letterSpacing > 0f) this.letterSpacing = letterSpacing / size
            }
            val xx = when (align) {
                Paint.Align.RIGHT -> right
                Paint.Align.CENTER -> left + contentWidth / 2f
                else -> x
            }
            canvas.drawText(value, xx, baseline, paint)
        }

        fun dashed(baseline: Float) {
            val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = LINE
                strokeWidth = 0.16f * u
                style = Paint.Style.STROKE
                pathEffect = DashPathEffect(floatArrayOf(3.4f * u, 2.6f * u), 0f)
            }
            canvas.drawLine(left, baseline, right, baseline, paint)
        }

        fun hairline(baseline: Float, color: Int = LINE) {
            val paint = Paint().apply {
                this.color = color
                strokeWidth = 0.15f * u
            }
            canvas.drawLine(left, baseline, right, baseline, paint)
        }

        // ── Kepala: merek di kiri, status di kanan ────────────────────────
        // Tiga baris kiri (merek → jenis dokumen → nomor bukti) supaya nomor
        // bukti jadi identitas dokumen, bukan tempelan kecil di sudut.
        val wordmarkBaseline = y + 7.2f * u
        text("PetHeal", left, wordmarkBaseline, 7.2f * u, BRAND, bold = true)
        text(
            "BUKTI PEMBAYARAN",
            left,
            wordmarkBaseline + 5.4f * u,
            2.8f * u,
            FAINT,
            letterSpacing = 0.32f * u
        )
        text(
            receipt.receiptNumber,
            left,
            wordmarkBaseline + 10.6f * u,
            3.3f * u,
            INK,
            bold = true
        )

        val pillLabel = if (receipt.isSettled) "LUNAS" else "BELUM LUNAS"
        val pillPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.1f * u
            typeface = Typeface.create("sans-serif-medium", Typeface.BOLD)
            letterSpacing = 0.06f
        }
        val pillTextWidth = pillPaint.measureText(pillLabel)
        val pillPadX = 4.2f * u
        val pillHeight = 7.2f * u
        val pillRight = right
        val pillRect = RectF(
            pillRight - pillTextWidth - pillPadX * 2f,
            y + 0.7f * u,
            pillRight,
            y + 0.7f * u + pillHeight
        )
        canvas.drawRoundRect(
            pillRect,
            pillHeight / 2f,
            pillHeight / 2f,
            Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = if (receipt.isSettled) BRAND_SOFT else WARN_SOFT
            }
        )
        // Titik status: penanda visual sebelum teks, bukan hiasan.
        canvas.drawCircle(
            pillRect.left + pillPadX * 0.7f,
            pillRect.centerY(),
            1.05f * u,
            Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = if (receipt.isSettled) BRAND_BRIGHT else WARN_INK
            }
        )
        pillPaint.color = if (receipt.isSettled) BRAND else WARN_INK
        canvas.drawText(
            pillLabel,
            pillRect.left + pillPadX * 1.7f,
            pillRect.centerY() + pillPaint.textSize * 0.36f,
            pillPaint
        )

        // ── Klinik ────────────────────────────────────────────────────────
        y = wordmarkBaseline + 10.6f * u + 7.4f * u
        dashed(y)
        y += 8.0f * u

        val clinicName = receipt.clinic?.name.orEmpty().ifBlank { "Klinik mitra PetHeal" }
        val clinicPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 4.4f * u
            typeface = Typeface.create("sans-serif-medium", Typeface.BOLD)
        }
        canvas.drawText(clinicName, left, y, clinicPaint)
        y += 5.2f * u

        val smallPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.0f * u
            color = MUTED
        }
        y = drawWrapped(canvas, receipt.clinic?.address.orEmpty(), left, y, contentWidth, smallPaint, 2, 4.0f * u)

        receipt.clinic?.phone?.takeIf { it.isNotBlank() }?.let { phone ->
            y += 4.0f * u
            canvas.drawText(phone, left, y, smallPaint)
            y += 0f
        }

        // ── Meta 2 kolom ──────────────────────────────────────────────────
        y += 8.0f * u
        val colGap = 6f * u
        val colWidth = (contentWidth - colGap) / 2f
        val firstBaseline = y

        fun metaCell(col: Int, row: Int, label: String, value: String) {
            val x = left + col * (colWidth + colGap)
            val base = firstBaseline + row * 9.6f * u
            text(label, x, base, 2.7f * u, FAINT, letterSpacing = 0.1f * u)
            val valuePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                textSize = 3.2f * u
                color = INK
                typeface = Typeface.create("sans-serif-medium", Typeface.NORMAL)
            }
            val clipped = trimToWidth(value, valuePaint, colWidth)
            canvas.drawText(clipped, x, base + 4.4f * u, valuePaint)
        }

        metaCell(0, 0, "NO. PESANAN", receipt.orderNumber)
        metaCell(1, 0, "TANGGAL", formatDate(receipt.timeline?.createdAt))
        metaCell(0, 1, "PELANGGAN", receipt.customer?.name.orEmpty().ifBlank { "-" })
        metaCell(1, 1, "METODE", receipt.payment?.methodLabel ?: receipt.payment?.method.orEmpty().ifBlank { "-" })
        y = firstBaseline + 2 * 9.6f * u

        // ── Produk ────────────────────────────────────────────────────────
        y += 3.4f * u
        dashed(y)
        y += 7.6f * u
        text("PRODUK", left, y, 2.7f * u, FAINT, letterSpacing = 0.36f * u)
        val totalUnits = receipt.items.sumOf { it.quantity }
        text(
            if (totalUnits == 1) "1 item" else "$totalUnits item",
            left,
            y,
            2.7f * u,
            FAINT,
            align = Paint.Align.RIGHT
        )
        y += 4.6f * u

        val namePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.3f * u
            color = INK
            typeface = Typeface.create("sans-serif-medium", Typeface.BOLD)
        }
        val subPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 2.7f * u
            color = MUTED
        }
        val amountPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.4f * u
            color = INK
            typeface = Typeface.create("sans-serif-medium", Typeface.BOLD)
            textAlign = Paint.Align.RIGHT
        }
        val qtyPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 2.7f * u
            color = FAINT
            textAlign = Paint.Align.RIGHT
        }
        val amountColumn = 26f * u
        val nameWidth = contentWidth - amountColumn - 3f * u

        receipt.items.forEachIndexed { index, item ->
            if (index > 0) {
                y += 4.2f * u
                hairline(y, LINE_SOFT)
                y += 5.4f * u
            }

            val startY = y
            val titlePaint = Paint(namePaint)
            val nameLines = wrapLines(
                listOfNotNull(item.productName, item.variantName?.takeIf { it.isNotBlank() })
                    .joinToString(" — "),
                titlePaint,
                nameWidth,
                2
            )
            nameLines.forEachIndexed { i, line ->
                canvas.drawText(line, left, startY + i * 4.4f * u, titlePaint)
            }

            val detailBase = startY + (nameLines.size - 1) * 4.4f * u + 4.3f * u
            val unit = item.discountPrice ?: item.unitPrice
            val variantText = item.variantName?.takeIf { it.isNotBlank() }
            val skuText = item.sku?.takeIf { it.isNotBlank() }
            val detailText = listOfNotNull(variantText, skuText?.let { "SKU $it" })
                .joinToString(" · ")
            if (detailText.isNotBlank()) {
                canvas.drawText(
                    trimToWidth(detailText, subPaint, nameWidth),
                    left,
                    detailBase,
                    subPaint
                )
            }

            canvas.drawText(rupiahLabel(item.subtotal), right, startY, amountPaint)
            canvas.drawText(
                "${item.quantity} × ${rupiahLabel(unit)}",
                right,
                detailBase,
                qtyPaint
            )

            y = detailBase
        }

        // ── Total ─────────────────────────────────────────────────────────
        y += 6.0f * u
        dashed(y)
        y += 8.4f * u

        val totalLabelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.0f * u
            color = MUTED
        }
        val totalValuePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.1f * u
            color = INK
            textAlign = Paint.Align.RIGHT
            typeface = Typeface.create("sans-serif-medium", Typeface.NORMAL)
        }

        fun totalRow(label: String, amount: Long, color: Int = INK) {
            val paint = Paint(totalLabelPaint)
            canvas.drawText(label, left, y, paint)
            totalValuePaint.color = color
            canvas.drawText("Rp ${rupiah.format(amount)}", right, y, totalValuePaint)
            y += 6.4f * u
        }

        val totals = receipt.totals
        if (totals != null) {
            totalRow("Subtotal", totals.subtotal)
            if (totals.discountTotal > 0) {
                totalRow("Diskon", -totals.discountTotal, BRAND)
            }
            if (totals.refundedAmount > 0) {
                totalRow("Dikembalikan", -totals.refundedAmount, BRAND)
            }
        }

        y += 1.6f * u
        val panelHeight = 15f * u
        canvas.drawRoundRect(
            RectF(left, y, right, y + panelHeight),
            4.5f * u,
            4.5f * u,
            Paint(Paint.ANTI_ALIAS_FLAG).apply { color = BRAND_SOFT }
        )
        text(
            "TOTAL",
            left + 5.4f * u,
            y + panelHeight / 2f + 1.35f * u,
            3.2f * u,
            BRAND,
            bold = true,
            letterSpacing = 0.16f * u
        )
        text(
            "Rp ${rupiah.format(totals?.totalAmount ?: 0L)}",
            right - 5.4f * u,
            y + panelHeight / 2f + 2.0f * u,
            5.6f * u,
            BRAND,
            bold = true,
            align = Paint.Align.RIGHT
        )
        y += panelHeight + 2.4f * u

        // "Dibayar" hanya ditampilkan bila berbeda dari total; kalau sama,
        // baris itu hanya mengulang angka yang sudah ada di panel total.
        if (totals != null && totals.paidAmount > 0 && totals.paidAmount != totals.totalAmount) {
            totalValuePaint.color = MUTED
            canvas.drawText("Dibayar", left, y + 3.4f * u, totalLabelPaint)
            canvas.drawText("Rp ${rupiah.format(totals.paidAmount)}", right, y + 3.4f * u, totalValuePaint)
            y += 6.4f * u
        }

        // ── Pembayaran ────────────────────────────────────────────────────
        y += 4.0f * u
        dashed(y)
        y += 7.6f * u
        text("PEMBAYARAN", left, y, 2.7f * u, FAINT, letterSpacing = 0.36f * u)
        y += 6.0f * u

        val rows = buildList {
            receipt.payment?.methodLabel?.let { add("Metode" to it) }
            receipt.statusLabel?.let { add("Status pesanan" to it) }
            (receipt.payment?.receiptStatusLabel
                ?: if (receipt.isSettled) "Lunas" else "Belum lunas")
                .let { add("Status pembayaran" to it) }
            receipt.payment?.paidAt?.let { add("Waktu bayar" to formatDate(it)) }
            receipt.payment?.paymentCode?.takeIf { it.isNotBlank() }
                ?.let { add("Kode pembayaran" to it) }
        }

        val rowLabelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 2.9f * u
            color = FAINT
        }
        val rowValuePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.0f * u
            color = INK
            textAlign = Paint.Align.RIGHT
            typeface = Typeface.create("sans-serif-medium", Typeface.NORMAL)
        }
        val labelColumn = 34f * u
        rows.forEach { (label, value) ->
            canvas.drawText(label, left, y, rowLabelPaint)
            val clipped = trimToWidth(value, rowValuePaint, contentWidth - labelColumn)
            rowValuePaint.color = if (label == "Status pembayaran" && !receipt.isSettled) WARN_INK else INK
            canvas.drawText(clipped, right, y, rowValuePaint)
            y += 5.6f * u
        }

        // ── Pengambilan ───────────────────────────────────────────────────
        y += 3.0f * u
        val pickupName = receipt.pickup?.clinicName?.takeIf { it.isNotBlank() }
            ?: receipt.clinic?.name.orEmpty()
        val pickupAddress = receipt.pickup?.clinicAddress?.takeIf { it.isNotBlank() }
            ?: receipt.clinic?.address.orEmpty()
        val note = receipt.pickup?.note?.takeIf { it.isNotBlank() }
            ?: "Tunjukkan bukti pembayaran ini atau QR Pickup Pass kepada petugas klinik saat mengambil produk."

        // Tinggi panel diukur dari jumlah baris teks agar tidak pernah terpotong.
        val notePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 2.8f * u
            color = MUTED
        }
        val noteLines = wrapLines(note, notePaint, contentWidth - 11f * u, 3)
        val hasAddress = pickupAddress.isNotBlank()
        // Label (6,4) + nama (11,6) + alamat opsional + baris catatan + napas bawah.
        val pickupPanelHeight = 16.4f * u +
            (if (hasAddress) 4.6f * u else 0f) +
            (noteLines.size.coerceAtLeast(1) - 1) * 4.2f * u +
            6.4f * u
        canvas.drawRoundRect(
            RectF(left, y, right, y + pickupPanelHeight),
            3.4f * u,
            3.4f * u,
            Paint(Paint.ANTI_ALIAS_FLAG).apply { color = PANEL }
        )
        text(
            "PENGAMBILAN",
            left + 5.4f * u,
            y + 6.4f * u,
            2.7f * u,
            FAINT,
            letterSpacing = 0.36f * u
        )
        val pickupNamePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = 3.2f * u
            color = INK
            typeface = Typeface.create("sans-serif-medium", Typeface.BOLD)
        }
        canvas.drawText(
            trimToWidth(pickupName, pickupNamePaint, contentWidth - 11f * u),
            left + 5.4f * u,
            y + 11.6f * u,
            pickupNamePaint
        )
        var noteY = y + 16.4f * u
        if (hasAddress) {
            val addressPaint = Paint(notePaint).apply { color = FAINT }
            addressPaint.textSize = 2.7f * u
            canvas.drawText(
                trimToWidth(pickupAddress, addressPaint, contentWidth - 11f * u),
                left + 5.4f * u,
                noteY,
                addressPaint
            )
            noteY += 4.6f * u
        }
        noteLines.forEach { line ->
            canvas.drawText(line, left + 5.4f * u, noteY, notePaint)
            noteY += 4.2f * u
        }
        y += pickupPanelHeight

        // ── Kaki dokumen ──────────────────────────────────────────────────
        y += 7.0f * u
        hairline(y)
        y += 7.0f * u
        text(
            "Bukti pembayaran dibuat otomatis oleh PetHeal.",
            left,
            y,
            2.6f * u,
            FAINT,
            align = Paint.Align.CENTER
        )
        y += 4.4f * u
        text(
            "Dicetak ${stampNow()} · Simpan sebagai arsip transaksi.",
            left,
            y,
            2.6f * u,
            FAINT,
            align = Paint.Align.CENTER
        )

        return y + 6.0f * u
    }

    private fun rupiahLabel(amount: Long): String = "Rp ${rupiah.format(amount)}"

    // ── Bantuan teks ───────────────────────────────────────────────────────

    private fun wrapLines(text: String, paint: Paint, maxWidth: Float, maxLines: Int): List<String> {
        if (text.isBlank()) return emptyList()
        val lines = mutableListOf<String>()
        var current = StringBuilder()

        text.split(" ").forEach { word ->
            val candidate = if (current.isEmpty()) word else "$current $word"
            if (paint.measureText(candidate) > maxWidth && current.isNotEmpty()) {
                lines += current.toString()
                current = StringBuilder(word)
            } else {
                current = StringBuilder(candidate)
            }
        }
        if (current.isNotEmpty()) lines += current.toString()

        return if (lines.size <= maxLines) {
            lines
        } else {
            val kept = lines.take(maxLines).toMutableList()
            kept[maxLines - 1] = ellipsize(kept[maxLines - 1], paint, maxWidth)
            kept
        }
    }

    private fun drawWrapped(
        canvas: Canvas,
        text: String,
        x: Float,
        y: Float,
        maxWidth: Float,
        paint: Paint,
        maxLines: Int,
        lineHeight: Float
    ): Float {
        val lines = wrapLines(text, paint, maxWidth, maxLines)
        lines.forEachIndexed { index, line ->
            canvas.drawText(line, x, y + index * lineHeight, paint)
        }
        return y + (lines.size - 1).coerceAtLeast(0) * lineHeight
    }

    private fun ellipsize(text: String, paint: Paint, maxWidth: Float): String {
        var value = text
        while (value.isNotEmpty() && paint.measureText("$value…") > maxWidth) {
            value = value.dropLast(1)
        }
        return "$value…"
    }

    private fun trimToWidth(text: String, paint: Paint, maxWidth: Float): String {
        if (paint.measureText(text) <= maxWidth) return text
        return ellipsize(text, paint, maxWidth)
    }

    // ── Berbagi & berkas ───────────────────────────────────────────────────

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

    private fun bitmapToBytes(bitmap: Bitmap): ByteArray =
        ByteArrayOutputStream().use { stream ->
            bitmap.compress(Bitmap.CompressFormat.PNG, 100, stream)
            stream.toByteArray()
        }

    private fun stampNow(): String =
        SimpleDateFormat("d MMM yyyy HH:mm", Locale("id", "ID")).format(Date())

    private fun formatDate(iso: String?): String {
        if (iso.isNullOrBlank()) return "-"
        return runCatching {
            java.time.OffsetDateTime.parse(iso)
                .atZoneSameInstant(java.time.ZoneId.of("Asia/Jakarta"))
                .format(java.time.format.DateTimeFormatter.ofPattern("d MMM yyyy, HH:mm", Locale("id", "ID")))
        }.getOrDefault(iso)
    }
}
