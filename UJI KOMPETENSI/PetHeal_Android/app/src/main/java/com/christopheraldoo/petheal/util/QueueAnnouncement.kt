package com.christopheraldoo.petheal.util

/**
 * Menyusun kalimat pengumuman antrian untuk Text-to-Speech.
 *
 * Fungsi murni (tanpa Android API) supaya bisa diuji unit: teks yang
 * diucapkan berbeda dari teks yang tampil di layar. Label "A-01" enak dibaca
 * mata, tetapi TTS bisa mengucapkannya "a strip nol satu" atau melewatkan
 * nol di depan. Karena itu nomor diubah ke kata ("A, nol satu") sebelum
 * diucapkan.
 */
object QueueAnnouncement {

    /**
     * Contoh hasil:
     * "Nomor antrian A, nol satu dipanggil. Silakan menuju ruang pemeriksaan dokter Christopher."
     */
    fun buildText(label: String?, doctorName: String?, room: String?): String {
        val doctor = cleanDoctorName(doctorName)
        val roomLabel = room?.trim()?.takeIf { it.isNotEmpty() }

        val destination = when {
            roomLabel != null && doctor != null -> "Silakan menuju $roomLabel, dokter $doctor."
            roomLabel != null -> "Silakan menuju $roomLabel."
            doctor != null -> "Silakan menuju ruang pemeriksaan dokter $doctor."
            else -> "Silakan menuju ruang pemeriksaan."
        }

        val spoken = spokenLabel(label)
        val call = if (spoken == null) {
            "Nomor antrian Anda dipanggil."
        } else {
            "Nomor antrian $spoken dipanggil."
        }

        return "$call $destination"
    }

    /**
     * "A-01" -> "A, nol satu" (nol di depan dibaca satu per satu).
     * "A-12" -> "A, 12" (TTS bahasa Indonesia membaca "dua belas").
     * Label tanpa tanda hubung dikembalikan apa adanya.
     */
    fun spokenLabel(label: String?): String? {
        val raw = label?.trim()?.takeIf { it.isNotEmpty() } ?: return null
        val separator = raw.indexOf('-')
        if (separator <= 0 || separator == raw.lastIndex) return raw

        val prefix = raw.substring(0, separator)
        val digits = raw.substring(separator + 1)
        if (digits.any { !it.isDigit() }) return raw.replace('-', ' ')

        val spokenNumber = if (digits.length > 1 && digits.first() == '0') {
            digits.map { digitWord(it) }.joinToString(" ")
        } else {
            digits
        }

        return "$prefix, $spokenNumber"
    }

    /**
     * Nama dokter di database sering berawalan "Dr." / "dr." / "Dokter".
     * Kalimat pengumuman sudah menyebut "dokter", jadi awalan itu dibuang
     * agar tidak terdengar "dokter dr. Emily".
     */
    private fun cleanDoctorName(doctorName: String?): String? {
        val raw = doctorName?.trim()?.takeIf { it.isNotEmpty() } ?: return null
        val cleaned = raw.replace(Regex("^(dr\\.?|dokter)\\s+", RegexOption.IGNORE_CASE), "").trim()
        return cleaned.ifEmpty { raw }
    }

    private fun digitWord(digit: Char): String = when (digit) {
        '0' -> "nol"
        '1' -> "satu"
        '2' -> "dua"
        '3' -> "tiga"
        '4' -> "empat"
        '5' -> "lima"
        '6' -> "enam"
        '7' -> "tujuh"
        '8' -> "delapan"
        '9' -> "sembilan"
        else -> digit.toString()
    }
}
