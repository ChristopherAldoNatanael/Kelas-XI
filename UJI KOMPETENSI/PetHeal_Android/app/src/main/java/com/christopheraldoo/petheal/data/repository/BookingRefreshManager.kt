package com.christopheraldoo.petheal.data.repository

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class BookingRefreshManager @Inject constructor() {
    private val _refreshVersion = MutableStateFlow(0L)
    val refreshVersion: StateFlow<Long> = _refreshVersion

    fun requestRefresh() {
        _refreshVersion.value = _refreshVersion.value + 1L
    }
}
