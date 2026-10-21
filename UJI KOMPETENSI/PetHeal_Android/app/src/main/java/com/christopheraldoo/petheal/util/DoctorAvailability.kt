package com.christopheraldoo.petheal.util

import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale

/**
 * Doctor availability helpers used by every schedule picker.
 *
 * The backend stores `available_days` as a JSON array but exposes it to the
 * Android contract as a comma-separated, lowercase English string
 * (for example "monday, wednesday, friday"). A missing value means the
 * schedule is unknown, so the UI must not disable dates based on a guess.
 */
fun isDoctorWorkingDay(availableDays: String?, date: LocalDate): Boolean {
    val days = normalizeAvailableDays(availableDays)
    if (days.isEmpty()) return true

    val dayName = date.dayOfWeek
        .getDisplayName(TextStyle.FULL, Locale.ENGLISH)
        .lowercase(Locale.ENGLISH)
    return dayName in days
}

/**
 * Finds the first working date at or after [from]. Returns null when the
 * doctor has no working day inside the visible booking horizon.
 */
fun firstDoctorWorkingDate(
    availableDays: String?,
    from: LocalDate,
    horizonDays: Int = 14
): LocalDate? {
    if (horizonDays <= 0) return null
    for (offset in 0 until horizonDays) {
        val date = from.plusDays(offset.toLong())
        if (isDoctorWorkingDay(availableDays, date)) return date
    }
    return null
}

private fun normalizeAvailableDays(availableDays: String?): Set<String> {
    val raw = availableDays
        ?.replace("[", "")
        ?.replace("]", "")
        ?.replace("\"", "")
        ?.trim()
        .orEmpty()
    if (raw.isBlank()) return emptySet()

    return raw
        .split(',', ';', '|')
        .map { it.trim().lowercase(Locale.ENGLISH) }
        .filter { it.isNotBlank() }
        .toSet()
}
