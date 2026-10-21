package com.christopheraldoo.petheal.util

import com.christopheraldoo.petheal.data.model.TimeSlot
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.format.DateTimeFormatter

/**
 * Aturan ketersediaan slot di sisi klien.
 *
 * Backend (`DoctorController::generateTimeSlots`) hanya menandai slot yang
 * sudah dibooking; slot yang JAMNYA SUDAH LEWAT tetap dikirim dengan
 * `available = true`. Akibatnya booking hari ini jam 10.00 masih bisa dipilih
 * padahal sekarang sudah jam 18.00.
 *
 * Klien menurunkan slot seperti itu menjadi tidak tersedia TANPA mengubah
 * data lain: slot tetap terlihat (agar pengguna paham jam praktik dokter),
 * tetapi tidak dapat dipilih. Slot dianggap lewat saat waktu mulainya sudah
 * tercapai atau terlewati.
 */
fun isSlotExpired(
    date: LocalDate,
    time: String,
    now: LocalDateTime = LocalDateTime.now()
): Boolean {
    val slotStart = parseSlotStartDateTime(date, time) ?: return false
    return !slotStart.isAfter(now)
}

/**
 * Salinan daftar slot dengan slot yang sudah lewat ditandai tidak tersedia.
 * Format jam yang tidak dikenali dibiarkan apa adanya supaya kontrak backend
 * yang berubah tidak membuat seluruh jadwal hilang.
 */
fun List<TimeSlot>.withExpiredSlotsUnavailable(
    date: LocalDate,
    now: LocalDateTime = LocalDateTime.now()
): List<TimeSlot> = map { slot ->
    if (slot.available && isSlotExpired(date, slot.time, now)) {
        slot.copy(available = false)
    } else {
        slot
    }
}

/** True bila seluruh slot pada [date] sudah lewat jamnya (mis. hari ini malam). */
fun List<TimeSlot>.allSlotsExpired(
    date: LocalDate,
    now: LocalDateTime = LocalDateTime.now()
): Boolean = isNotEmpty() && all { isSlotExpired(date, it.time, now) }

/**
 * Gabungkan tanggal + jam slot menjadi [LocalDateTime].
 * Menerima "HH:mm", "HH:mm:ss", dan "H:mm". Null bila format tidak dikenal.
 */
fun parseSlotStartDateTime(date: LocalDate, time: String): LocalDateTime? {
    val slotTime = parseSlotTime(time) ?: return null
    return LocalDateTime.of(date, slotTime)
}

private fun parseSlotTime(time: String): LocalTime? {
    val raw = time.trim()
    if (raw.isEmpty()) return null
    val pattern = when (raw.count { it == ':' }) {
        1 -> "H:mm"
        2 -> "H:mm:ss"
        else -> return null
    }
    return runCatching {
        LocalTime.parse(raw, DateTimeFormatter.ofPattern(pattern))
    }.getOrNull()
}
