package com.christopheraldoo.simpleweatherapp.location

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.os.Looper
import androidx.core.app.ActivityCompat
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.Granularity
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult as GmsLocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.launch

class LocationService(private val context: Context) {

    private val fusedLocationClient: FusedLocationProviderClient =
        LocationServices.getFusedLocationProviderClient(context)

    fun getCurrentLocation(): Flow<LocationResult> = callbackFlow {
        // Check if location permissions are granted
        if (ActivityCompat.checkSelfPermission(
                context,
                Manifest.permission.ACCESS_FINE_LOCATION
            ) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.checkSelfPermission(
                context,
                Manifest.permission.ACCESS_COARSE_LOCATION
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            trySend(LocationResult.Error("Location permissions not granted"))
            close()
            return@callbackFlow
        }

        trySend(LocationResult.Loading)

        // First try to get last known location
        fusedLocationClient.lastLocation
            .addOnSuccessListener { location: Location? ->
                if (location != null && isLocationFresh(location)) {
                    launch {
                        trySend(LocationResult.Success(location))
                    }
                } else {
                    // If no fresh location, request current location
                    requestCurrentLocation(this)
                }
            }
            .addOnFailureListener {
                // If last location fails, request current location
                requestCurrentLocation(this)
            }

        awaitClose {
            // Cleanup will be handled by removeLocationUpdates
        }
    }

    @SuppressLint("MissingPermission")
    private fun requestCurrentLocation(producerScope: kotlinx.coroutines.channels.ProducerScope<LocationResult>) {
        try {
            val locationRequest = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 10000)
                .setMinUpdateIntervalMillis(5000)
                .setMaxUpdates(1)
                .setGranularity(Granularity.GRANULARITY_PERMISSION_LEVEL)
                .build()

            var locationCallback: LocationCallback? = null
            locationCallback = object : LocationCallback() {
                override fun onLocationResult(locationResult: GmsLocationResult) {
                    producerScope.launch {
                        locationResult.lastLocation?.let { location ->
                            producerScope.trySend(LocationResult.Success(location))
                        } ?: run {
                            producerScope.trySend(LocationResult.Error("No location available"))
                        }
                        // Remove location updates after getting result
                        locationCallback?.let { callback ->
                            try {
                                fusedLocationClient.removeLocationUpdates(callback)
                            } catch (e: Exception) {
                                // Ignore cleanup errors
                            }
                        }
                    }
                }
            }

            fusedLocationClient.requestLocationUpdates(
                locationRequest,
                locationCallback,
                Looper.getMainLooper()
            )
        } catch (e: Exception) {
            producerScope.launch {
                producerScope.trySend(LocationResult.Error("Failed to request location: ${e.message}"))
            }
        }
    }

    private fun isLocationFresh(location: Location): Boolean {
        // Consider location fresh if it's less than 30 minutes old
        val thirtyMinutesInMs = 30 * 60 * 1000L
        return (System.currentTimeMillis() - location.time) < thirtyMinutesInMs
    }
}

sealed class LocationResult {
    object Loading : LocationResult()
    data class Success(val location: Location) : LocationResult()
    data class Error(val message: String) : LocationResult()
}
