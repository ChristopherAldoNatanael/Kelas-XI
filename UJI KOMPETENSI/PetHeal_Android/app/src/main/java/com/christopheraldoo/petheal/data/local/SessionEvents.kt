package com.christopheraldoo.petheal.data.local

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * PHASE 7: app-wide auth-lifecycle events.
 *
 * The [com.christopheraldoo.petheal.data.remote.NetworkInterceptor] emits
 * [SessionExpired] when the backend answers 401 on a request that carried a
 * token. Navigation collects this and routes to login exactly once per
 * emission (extraBufferCapacity + non-suspending emit, conflated replay).
 */
object SessionEvents {
    data object SessionExpired

    private val _events = MutableSharedFlow<Any>(extraBufferCapacity = 1)
    val events: SharedFlow<Any> = _events.asSharedFlow()

    fun emitSessionExpired() {
        _events.tryEmit(SessionExpired)
    }
}
