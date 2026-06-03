package com.christopheraldoo.petheal.util

import com.christopheraldoo.petheal.di.AppModule

/**
 * Convert any photo value returned by the backend into a fully-qualified URL
 * that Coil (AsyncImage) can load on the device.
 *
 * Backend now stores relative paths like "pets/filename.jpg".
 * This function prepends STORAGE_URL so the full URL becomes:
 *   https://radia-unswaggering-lisandra.ngrok-free.dev/storage/pets/filename.jpg
 *
 * If the value is already a full URL (starts with http), it is returned as-is.
 * If null / blank, returns null so callers can show a placeholder.
 */
fun buildPhotoUrl(photo: String?): String? {
    if (photo.isNullOrBlank()) return null
    // Already a full URL (old data or external URL)
    if (photo.startsWith("http://") || photo.startsWith("https://")) return photo
    // Relative path stored by backend (e.g. "pets/abc.jpg")
    return "${AppModule.STORAGE_URL}${photo}"
}
