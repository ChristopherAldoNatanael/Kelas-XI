package com.christopheraldoo.petheal.util

import com.christopheraldoo.petheal.data.model.DoctorScheduleException
import com.christopheraldoo.petheal.data.model.SlotMeta
import com.christopheraldoo.petheal.data.model.TimeSlot
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeParseException

/**
 * Alasan satu tanggal tidak bisa dipesan. Dipakai slot picker supaya setiap
 * keadaan punya pesan sendiri — bukan satu empty state generik untuk semua.
 *
 * Urutan prioritas penting: cuti dari server menang atas aturan hari praktik,
 * sehingga dokter yang cuti di hari kerja tidak terlihat seperti "belum ada
 * slot" atau "jam sudah lewat".
 */
enum class SlotDayStatus {
    AVAILABLE,
    CLOSED_LEAVE,
    NOT_PRACTICING,
    NO_SLOTS,
    HOURS_PASSED,
    FULLY_BOOKED
}

fun resolveSlotDayStatus(
    slots: List<TimeSlot>,
    meta: SlotMeta?,
    isWorkingDay: Boolean,
    date: LocalDate?,
    now: LocalDateTime = LocalDateTime.now()
): SlotDayStatus {
    if (meta?.isClosed == true) return SlotDayStatus.CLOSED_LEAVE
    // Server adalah otoritas: jam khusus bisa membuka hari yang biasanya
    // tutup, jadi `meta.isWorkingDay` menang atas available_days lokal.
    val working = meta?.isWorkingDay ?: isWorkingDay
    if (!working) return SlotDayStatus.NOT_PRACTICING
    if (slots.isEmpty()) return SlotDayStatus.NO_SLOTS
    if (date != null && slots.allSlotsExpired(date, now)) return SlotDayStatus.HOURS_PASSED
    if (slots.none { it.available }) return SlotDayStatus.FULLY_BOOKED
    return SlotDayStatus.AVAILABLE
}

/**
 * Tanggal cuti dari detail dokter → map untuk ditandai di date rail.
 * Tanggal yang formatnya tidak dikenali diabaikan (lebih baik rail normal
 * daripada crash karena satu baris aneh).
 */
fun List<DoctorScheduleException>?.toLeaveDates(): Map<LocalDate, String?> {
    if (this.isNullOrEmpty()) return emptyMap()
    return mapNotNull { exception ->
        val date = exception.date?.trim()?.take(10)?.let { raw ->
            try {
                LocalDate.parse(raw)
            } catch (_: DateTimeParseException) {
                null
            }
        } ?: return@mapNotNull null
        if (!exception.isClosed) return@mapNotNull null
        date to exception.reason?.takeIf { it.isNotBlank() }
    }.toMap()
}

/**
 * Tanggal yang dibuka lewat jam khusus (dokter biasanya tutup di hari itu).
 * Dipakai date rail agar tanggal tambahan tidak tampak nonaktif.
 */
fun List<DoctorScheduleException>?.toCustomHoursDates(): Set<LocalDate> {
    if (this.isNullOrEmpty()) return emptySet()
    return mapNotNull { exception ->
        if (exception.isClosed) return@mapNotNull null
        exception.date?.trim()?.take(10)?.let { raw ->
            try {
                LocalDate.parse(raw)
            } catch (_: DateTimeParseException) {
                null
            }
        }
    }.toSet()
}

/**
 * Label jam efektif satu tanggal, mis. "10:00 - 12:00". Null bila tidak ada
 * jam khusus.
 */
fun SlotMeta?.customHoursLabel(): String? {
    if (this == null || !hasException || isClosed) return null
    val start = startTime?.takeIf { it.isNotBlank() } ?: return null
    val end = endTime?.takeIf { it.isNotBlank() } ?: return null
    return "$start - $end"
}
