package com.christopheraldoo.petheal.ui.theme

import androidx.compose.ui.graphics.Color

/**
 * PHASE 7: dynamic per-clinic branding.
 *
 * The backend owns the brand ([Clinic.primaryColor], logo, name).
 * Parsing is total: malformed/null input falls back to the app primary,
 * so a bad tenant row can never crash rendering.
 */
object ClinicTheme {
    val FallbackPrimary = Color(0xFF18C964)

    private val HEX_6 = Regex("^#[0-9A-Fa-f]{6}$")
    private val HEX_8 = Regex("^#[0-9A-Fa-f]{8}$")

    fun parsePrimaryColor(raw: String?): Color {
        val value = raw?.trim().orEmpty()
        return try {
            when {
                HEX_6.matches(value) -> Color(android.graphics.Color.parseColor(value))
                HEX_8.matches(value) -> Color(android.graphics.Color.parseColor(value))
                value.matches(Regex("^[0-9A-Fa-f]{6}$")) ->
                    Color(android.graphics.Color.parseColor("#$value"))
                else -> FallbackPrimary
            }
        } catch (e: Exception) {
            FallbackPrimary
        }
    }

    fun isValidBrandColor(raw: String?): Boolean {
        val value = raw?.trim().orEmpty()
        return HEX_6.matches(value) || HEX_8.matches(value)
    }
}
