package com.christopheraldoo.petheal.util

/**
 * Central parser for decimal number input (weight in kg, etc.).
 *
 * Accepts BOTH Indonesian decimal separator ("5,8") and English ("5.8") —
 * the weight dialog literally tells users to type "Contoh: 5,8", so the
 * parser must accept what it asks for. Mixed separators ("5,8.2") and
 * repeated separators are rejected instead of being silently normalized,
 * so an ambiguous value never turns into a wrong weight on the server.
 *
 * Returns null for blank, malformed, non-finite, or out-of-range input.
 * The valid range guard (0 < value <= 500 kg) only rejects physiologically
 * impossible values; it does not round or clamp real input.
 *
 * The result is a plain Double; serialization to the API contract is
 * unchanged (still a JSON number).
 */
private val DECIMAL_REGEX = Regex("\\d{1,3}(?:[.,]\\d+)?|\\d{4,}(?:[.,]\\d+)?")

fun parseDecimalInput(raw: String?): Double? {
    val trimmed = raw?.trim()?.takeIf { it.isNotEmpty() } ?: return null
    // Exactly one separator kind, at most once, digits on both sides.
    if (!trimmed.matches(DECIMAL_REGEX)) return null
    val normalized = trimmed.replace(',', '.')
    val value = normalized.toDoubleOrNull()?.takeIf { it.isFinite() } ?: return null
    return value.takeIf { it > 0.0 && it <= 500.0 }
}
