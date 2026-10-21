package com.christopheraldoo.petheal.util

import java.net.URLEncoder
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

/**
 * Kontak klinik yang dipakai tombol Bantuan.
 *
 * Sumbernya `clinics.phone` / `clinics.email` yang sudah diisi admin di
 * Clinic Profile — tidak ada setting baru di aplikasi. Backend sudah
 * mengekspos keduanya lewat `GET public/clinics/{slug}`.
 */
data class ClinicContact(
    val clinicName: String? = null,
    val phone: String? = null,
    val email: String? = null
)

/**
 * Normalisasi nomor telepon klinik menjadi format WhatsApp (`62...`).
 *
 * Admin bebas mengetik "0812-3456-7890", "+62 812 3456 7890", atau
 * "62812...". Null dikembalikan bila tidak ada digit yang bisa dipakai
 * atau panjangnya tidak masuk akal, supaya tombol WA tidak membuka chat
 * ke nomor yang salah.
 */
fun normalizeWhatsAppNumber(raw: String?): String? {
    val trimmed = raw?.trim().orEmpty()
    if (trimmed.isBlank()) return null

    var digits = trimmed.filter(Char::isDigit)
    if (digits.isBlank()) return null

    // "+62..." → digit saja sudah 62..., "0062..." → buang prefix 00.
    if (trimmed.startsWith("00")) digits = digits.removePrefix("00")

    digits = when {
        digits.startsWith("0") -> "62" + digits.removePrefix("0")
        // Nomor lokal tanpa 0 di depan (mis. 812...) → tetap nomor Indonesia.
        digits.startsWith("8") -> "62$digits"
        else -> digits
    }

    if (digits.length !in 10..15) return null
    return digits
}

/** Link universal `wa.me`; tetap jalan walau WhatsApp tidak terpasang. */
fun buildWhatsAppUrl(number: String, message: String): String? {
    val normalized = normalizeWhatsAppNumber(number) ?: return null
    val encoded = URLEncoder.encode(message, "UTF-8").replace("+", "%20")
    return "https://wa.me/$normalized?text=$encoded"
}

/**
 * Pesan pembuka yang sudah membawa konteks booking, sehingga admin tidak
 * perlu menanyakan ulang dokter/jadwal. Data medis hewan sengaja tidak
 * dimasukkan — itu bukan urusan chat pembuka.
 */
fun buildClinicHelpMessage(
    clinicName: String?,
    userName: String?,
    doctorName: String?,
    serviceName: String?,
    date: LocalDate?,
    time: String?,
    topic: String? = null
): String {
    val clinic = clinicName?.trim()?.takeIf { it.isNotBlank() }
    val user = userName?.trim()?.takeIf { it.isNotBlank() }
    val about = topic?.trim()?.takeIf { it.isNotBlank() } ?: "booking"

    val greeting = buildString {
        append("Halo Admin")
        if (clinic != null) append(" $clinic")
        append(", ")
        if (user != null) {
            append("saya $user ")
        } else {
            append("saya ")
        }
        append("ingin bertanya tentang $about di aplikasi.")
    }

    val details = buildList {
        doctorName?.trim()?.takeIf { it.isNotBlank() }?.let { add("Dokter: $it") }
        serviceName?.trim()?.takeIf { it.isNotBlank() }?.let { add("Layanan: $it") }
        val schedule = buildScheduleLabel(date, time)
        if (schedule != null) add("Jadwal: $schedule")
    }

    return if (details.isEmpty()) greeting else greeting + "\n\n" + details.joinToString("\n")
}

/** Pesan untuk kartu "Laporkan Masalah" di halaman Bantuan. */
fun buildClinicReportMessage(clinicName: String?, userName: String?): String {
    val clinic = clinicName?.trim()?.takeIf { it.isNotBlank() }
    val user = userName?.trim()?.takeIf { it.isNotBlank() }

    return buildString {
        append("Halo Admin")
        if (clinic != null) append(" $clinic")
        append(", ")
        append(if (user != null) "saya $user " else "saya ")
        append("ingin melaporkan masalah pada aplikasi. ")
        append("Mohon dibantu untuk menindaklanjutinya.")
    }
}

private fun buildScheduleLabel(date: LocalDate?, time: String?): String? {
    val cleanTime = time?.trim()?.takeIf { it.isNotBlank() }
    if (date == null) return cleanTime
    val day = date.format(DateTimeFormatter.ofPattern("EEEE, d MMMM yyyy", Locale("id", "ID")))
    return if (cleanTime != null) "$day, $cleanTime" else day
}
