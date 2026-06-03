package com.christopheraldoo.petheal.util

import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.testTag

/**
 * Accessibility utility functions for improving screen reader support
 * and semantic labels across the application.
 */

/**
 * Add a content description for screen readers.
 * This is essential for visually impaired users who use TalkBack.
 */
fun Modifier.accessibilityLabel(label: String): Modifier = composed {
    this.semantics { contentDescription = label }
}

/**
 * Add a test tag for UI testing and accessibility.
 */
fun Modifier.accessibilityTestTag(tag: String): Modifier = composed {
    this.semantics { testTag = tag }
}

/**
 * Format booking status for accessibility.
 * Provides a clear, descriptive status message.
 */
fun formatBookingStatusForAccessibility(status: String?): String {
    return when (status?.lowercase()) {
        "pending" -> "Status: Pending - Awaiting confirmation"
        "confirmed" -> "Status: Confirmed - Your appointment is scheduled"
        "completed" -> "Status: Completed - Visit finished"
        "cancelled" -> "Status: Cancelled - Appointment cancelled"
        else -> "Status: Unknown"
    }
}

/**
 * Format payment status for accessibility.
 */
fun formatPaymentStatusForAccessibility(status: String?): String {
    return when (status?.lowercase()) {
        "paid" -> "Payment: Fully PAID"
        "dp_paid" -> "Payment: DOWN PAYMENT PAID - 50% paid, remaining balance due"
        "dp_pending" -> "Payment: DOWN PAYMENT PENDING - Awaiting down payment"
        "partial" -> "Payment: PARTIALLY PAID - Some amount paid, balance due"
        "pending" -> "Payment: UNPAID - Payment required"
        "failed" -> "Payment: FAILED - Transaction unsuccessful"
        null -> "Payment: No payment information available"
        else -> "Payment: $status"
    }
}

/**
 * Format date and time for accessibility.
 * Provides a clear, descriptive date-time string.
 */
fun formatDateTimeForAccessibility(date: String?, time: String?): String {
    val datePart = date ?: "Date not set"
    val timePart = time ?: "Time not set"
    return "Appointment on $datePart at $timePart"
}

/**
 * Format doctor information for accessibility.
 */
fun formatDoctorInfoForAccessibility(name: String?, specialization: String?): String {
    val doctorName = name ?: "Doctor name not available"
    val spec = specialization ?: "Veterinarian"
    return "Doctor: $doctorName, Specialization: $spec"
}

/**
 * Format pet information for accessibility.
 */
fun formatPetInfoForAccessibility(name: String?, species: String?): String {
    val petName = name ?: "Pet name not available"
    val petSpecies = species ?: "Species not specified"
    return "Pet: $petName, Species: $petSpecies"
}

/**
 * Format notification for accessibility.
 */
fun formatNotificationForAccessibility(title: String?, message: String?, isRead: Boolean): String {
    val readStatus = if (isRead) "Read" else "Unread"
    val notifTitle = title ?: "Notification"
    val notifMessage = message ?: ""
    return "$readStatus notification: $notifTitle. $notifMessage"
}
