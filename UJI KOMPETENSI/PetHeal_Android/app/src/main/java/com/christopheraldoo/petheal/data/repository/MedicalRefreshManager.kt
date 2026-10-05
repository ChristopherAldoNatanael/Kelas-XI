package com.christopheraldoo.petheal.data.repository

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Sinyal refresh lintas-screen untuk Medical Record — pola yang sama dengan
 * [BookingRefreshManager] yang sudah ada. Payment extra (additional) yang
 * sukses memanggil [requestRefresh]; [MedicalRecordsViewModel] mengamati
 * versi ini dan memaksa re-fetch (melewati cache RAM) sehingga status
 * terkunci → tersedia berubah tanpa restart aplikasi.
 */
@Singleton
class MedicalRefreshManager @Inject constructor() {
    private val _refreshVersion = MutableStateFlow(0L)
    val refreshVersion: StateFlow<Long> = _refreshVersion

    fun requestRefresh() {
        _refreshVersion.value = _refreshVersion.value + 1L
    }
}
