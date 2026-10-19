package com.christopheraldoo.petheal.ui.screens.auth

import android.os.SystemClock
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.remote.NetworkInterceptor
import com.christopheraldoo.petheal.data.remote.NetworkMonitor
import com.christopheraldoo.petheal.data.repository.DeviceTokenRepository
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.merge
import kotlinx.coroutines.flow.receiveAsFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

/**
 * Step of app start-up the splash is currently showing. The screen maps each
 * step to a string resource, so all copy lives in `strings.xml`.
 */
enum class SplashStage {
    PREPARING,
    RESTORING_SESSION,
    CHECKING_CONNECTION,
    SYNCING,
    WAITING_FOR_NETWORK,
    READY
}

data class SplashUiState(
    val progress: Float = 0f,
    val stage: SplashStage = SplashStage.PREPARING,
    /** True while the device has no usable connection. */
    val isOffline: Boolean = false,
    /** True once start-up finished and the screen may route onwards. */
    val isReady: Boolean = false
)

@HiltViewModel
class SplashViewModel @Inject constructor(
    private val preferencesManager: PreferencesManager,
    private val deviceTokenRepository: DeviceTokenRepository,
    private val networkInterceptor: NetworkInterceptor,
    private val networkMonitor: NetworkMonitor
) : ViewModel() {

    val isLoggedIn: Flow<Boolean> = preferencesManager.isLoggedIn
    val hasSeenOnboarding: Flow<Boolean> = preferencesManager.hasSeenOnboarding

    private val _state = MutableStateFlow(SplashUiState())
    val state: StateFlow<SplashUiState> = _state.asStateFlow()

    /**
     * Conflated so a tap that arrives while start-up is between checks is never
     * lost (a plain SharedFlow would drop it if no collector was active).
     */
    private val retrySignal = Channel<Unit>(Channel.CONFLATED)

    /** Set when the user chooses to enter the app without a connection. */
    @Volatile
    private var offlineBypass = false

    init {
        viewModelScope.launch { runStartup() }
    }

    /** Re-checks connectivity after the user taps "Coba lagi". */
    fun retryConnection() {
        retrySignal.trySend(Unit)
    }

    /** Skips the connection wait when the user taps "Lanjutkan". */
    fun continueWithoutConnection() {
        offlineBypass = true
        retrySignal.trySend(Unit)
    }

    private suspend fun runStartup() {
        val startedAt = SystemClock.elapsedRealtime()

        update(stage = SplashStage.PREPARING, progress = 0.16f)

        // Local (DataStore) first — the persisted session must be readable even
        // with no network, otherwise an offline user would be sent to login.
        val loggedIn = preferencesManager.isLoggedIn.first()
        update(stage = SplashStage.RESTORING_SESSION, progress = 0.42f)

        awaitConnection()

        if (!offlineBypass) {
            delay(STAGE_READABLE_MS)
            update(stage = SplashStage.CHECKING_CONNECTION, progress = 0.6f)
            delay(STAGE_READABLE_MS)
            if (loggedIn) {
                update(stage = SplashStage.SYNCING, progress = 0.78f)
                syncSession()
            }
        }

        // Keep the brand moment from flashing on very fast devices. Every step
        // above is real work, so this only pads the fast path — never adds a
        // fixed delay on top of slow ones.
        val elapsed = SystemClock.elapsedRealtime() - startedAt
        if (elapsed < MIN_BRAND_DISPLAY_MS) delay(MIN_BRAND_DISPLAY_MS - elapsed)

        update(stage = SplashStage.READY, progress = 1f, ready = true)
    }

    /**
     * Suspends until the device reports a usable connection. While offline the
     * splash parks on [SplashStage.WAITING_FOR_NETWORK] (the UI shows the
     * offline card) and resumes by itself the moment the connection is back.
     */
    private suspend fun awaitConnection() {
        while (!networkMonitor.isOnlineNow() && !offlineBypass) {
            _state.update {
                it.copy(stage = SplashStage.WAITING_FOR_NETWORK, isOffline = true)
            }
            merge(
                networkMonitor.isOnline.filter { online -> online }.map { },
                retrySignal.receiveAsFlow()
            ).first()
        }
        _state.update { it.copy(isOffline = false) }
    }

    /**
     * Refreshes the auth token in the interceptor and re-registers the FCM
     * token. Best-effort: a failure here must never hold the user on the splash.
     */
    private suspend fun syncSession() {
        try {
            preferencesManager.authToken.first()?.let { networkInterceptor.updateToken(it) }
            val token = FirebaseMessaging.getInstance().token.await()
            deviceTokenRepository.saveDeviceToken(token, "android")
        } catch (_: Exception) {
            // Non-fatal — the token is registered again on the next login.
        }
    }

    private fun update(stage: SplashStage, progress: Float, ready: Boolean = false) {
        _state.update { it.copy(stage = stage, progress = progress, isReady = ready) }
    }

    companion object {
        /** Minimum time the brand is on screen, so it never flashes. */
        private const val MIN_BRAND_DISPLAY_MS = 1000L

        /** Minimum time each step stays visible, so the labels are readable. */
        private const val STAGE_READABLE_MS = 200L
    }
}
