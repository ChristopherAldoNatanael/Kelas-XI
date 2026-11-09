package com.christopheraldoo.petheal.util

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import androidx.core.content.ContextCompat
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.math.asin
import kotlin.math.cos
import kotlin.math.roundToInt
import kotlin.math.sin
import kotlin.math.sqrt
import kotlin.coroutines.resume

/**
 * MULTI-CLINIC DISCOVERY — pembacaan lokasi pengguna TANPA dependency baru
 * (keputusan user: pakai `LocationManager` bawaan, bukan play-services-location).
 *
 * Semua kegagalan dikembalikan sebagai state eksplisit, bukan exception:
 *  - [LocationResult.PermissionDenied]  → tampilkan penjelasan + input manual
 *  - [LocationResult.ServicesDisabled]  → GPS mati, tawarkan input manual
 *  - [LocationResult.Timeout]           → tidak dapat fix, tawarkan input manual
 *  - [LocationResult.Unavailable]       → perangkat tanpa provider
 *
 * Lokasi TIDAK PERNAH wajib: aplikasi tetap bisa dipakai tanpa izin lokasi
 * (klinik tampil tanpa jarak, "Lokasi belum diatur").
 */
object LocationHelper {

    private const val FIX_TIMEOUT_MS = 12_000L

    fun hasPermission(context: Context): Boolean {
        val fine = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
        val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)
        return fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED
    }

    fun isLocationEnabled(context: Context): Boolean {
        val manager = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return false
        return runCatching {
            manager.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)
        }.getOrDefault(false)
    }

    /**
     * Ambil satu fix terakhir. `progress` terakhir (cache provider) dipakai
     * lebih dulu supaya layar tidak menunggu lama, lalu di-upgrade bila ada
     * fix GPS yang lebih akurat dalam batas timeout.
     */
    @SuppressLint("MissingPermission")
    suspend fun currentLocation(
        context: Context,
        timeoutMs: Long = FIX_TIMEOUT_MS
    ): LocationResult {
        if (!hasPermission(context)) return LocationResult.PermissionDenied
        if (!isLocationEnabled(context)) return LocationResult.ServicesDisabled

        val manager = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
            ?: return LocationResult.Unavailable

        val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .filter { runCatching { manager.isProviderEnabled(it) }.getOrDefault(false) }
        if (providers.isEmpty()) return LocationResult.ServicesDisabled

        // 1. Fix cache (instan, biasanya cukup untuk urutkan jarak).
        val lastKnown = providers
            .mapNotNull { provider -> runCatching { manager.getLastKnownLocation(provider) }.getOrNull() }
            .maxByOrNull { it.time }

        val fresh = withTimeoutOrNull(timeoutMs) {
            suspendCancellableCoroutine { cont ->
                var delivered = false
                val listener = object : LocationListener {
                    override fun onLocationChanged(location: Location) {
                        if (!delivered) {
                            delivered = true
                            runCatching { manager.removeUpdates(this) }
                            if (cont.isActive) cont.resume(location)
                        }
                    }

                    @Deprecated("Deprecated in API 29", ReplaceWith(""))
                    override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) = Unit

                    override fun onProviderEnabled(provider: String) = Unit

                    override fun onProviderDisabled(provider: String) = Unit
                }

                providers.forEach { provider ->
                    runCatching {
                        manager.requestLocationUpdates(provider, 0L, 0f, listener, Looper.getMainLooper())
                    }
                }

                cont.invokeOnCancellation { runCatching { manager.removeUpdates(listener) } }
            }
        }

        val best = listOfNotNull(fresh, lastKnown).maxByOrNull { it.time }
            ?: return LocationResult.Timeout

        return LocationResult.Success(
            latitude = best.latitude,
            longitude = best.longitude,
            accuracyMeters = if (best.hasAccuracy()) best.accuracy.toInt() else null
        )
    }

    /**
     * Haversine — sama dengan rumus backend (`Clinic::distanceFrom`) supaya
     * angka jarak di app dan server tidak pernah berbeda.
     */
    fun haversineKm(lat1: Double, lng1: Double, lat2: Double, lng2: Double): Double {
        val earthRadius = 6371.0
        val dLat = Math.toRadians(lat2 - lat1)
        val dLng = Math.toRadians(lng2 - lng1)
        val a = sin(dLat / 2) * sin(dLat / 2) +
            cos(Math.toRadians(lat1)) * cos(Math.toRadians(lat2)) * sin(dLng / 2) * sin(dLng / 2)
        val c = 2 * asin(sqrt(a))
        return (earthRadius * c * 10).roundToInt() / 10.0
    }

    /** Format jarak: <1 km → meter, selebihnya km dengan satu desimal. */
    fun formatDistance(km: Double?): String? {
        if (km == null) return null
        return if (km < 1.0) {
            "${(km * 1000).roundToInt()} m"
        } else {
            String.format(java.util.Locale.US, "%.1f km", km)
        }
    }
}

sealed interface LocationResult {
    data class Success(
        val latitude: Double,
        val longitude: Double,
        val accuracyMeters: Int? = null
    ) : LocationResult

    data object PermissionDenied : LocationResult
    data object ServicesDisabled : LocationResult
    data object Timeout : LocationResult
    data object Unavailable : LocationResult
}
