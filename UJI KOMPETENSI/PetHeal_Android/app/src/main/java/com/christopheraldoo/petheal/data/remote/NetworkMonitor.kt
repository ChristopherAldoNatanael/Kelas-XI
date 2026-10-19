package com.christopheraldoo.petheal.data.remote

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.conflate
import kotlinx.coroutines.flow.distinctUntilChanged
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Single source of truth for "does this device currently have a usable
 * network?". Used by the splash so its progress reflects the real connection
 * instead of a fixed timer.
 *
 * A connection counts as usable when the active network advertises
 * NET_CAPABILITY_INTERNET over a real transport (Wi-Fi / cellular / ethernet /
 * VPN). The capability is intentionally checked without NET_CAPABILITY_VALIDATED
 * because the dev backend is reached over ngrok / an emulator loopback where
 * captive-portal validation is not available.
 */
@Singleton
class NetworkMonitor @Inject constructor(
    @ApplicationContext private val context: Context
) {

    private val connectivityManager: ConnectivityManager? =
        context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager

    /** Emits the current state on subscribe, then on every connectivity change. */
    val isOnline: Flow<Boolean> = callbackFlow {
        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                trySend(isOnlineNow())
            }

            override fun onLost(network: Network) {
                trySend(isOnlineNow())
            }

            override fun onCapabilitiesChanged(
                network: Network,
                networkCapabilities: NetworkCapabilities
            ) {
                trySend(isOnlineNow())
            }

            override fun onUnavailable() {
                trySend(false)
            }
        }

        trySend(isOnlineNow())

        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()

        val registered = connectivityManager?.let { manager ->
            runCatching { manager.registerNetworkCallback(request, callback) }.isSuccess
        } ?: false

        awaitClose {
            if (registered) {
                runCatching { connectivityManager?.unregisterNetworkCallback(callback) }
            }
        }
    }.distinctUntilChanged().conflate()

    /** One-shot, non-suspending check — safe to call from anywhere. */
    fun isOnlineNow(): Boolean {
        val manager = connectivityManager ?: return false
        val capabilities = manager.activeNetwork?.let { manager.getNetworkCapabilities(it) }
            ?: return false

        if (!capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) {
            return false
        }

        return capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) ||
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) ||
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) ||
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN)
    }
}
