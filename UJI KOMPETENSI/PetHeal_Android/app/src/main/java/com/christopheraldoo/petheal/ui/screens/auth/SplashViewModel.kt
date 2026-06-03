package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.DeviceTokenRepository
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

@HiltViewModel
class SplashViewModel @Inject constructor(
    private val preferencesManager: PreferencesManager,
    private val deviceTokenRepository: DeviceTokenRepository
) : ViewModel() {

    val isLoggedIn: Flow<Boolean> = preferencesManager.isLoggedIn
    val hasSeenOnboarding: Flow<Boolean> = preferencesManager.hasSeenOnboarding

    private val _progress = MutableStateFlow(0f)
    val progress: StateFlow<Float> = _progress.asStateFlow()

    init {
        // Restore 3-second animated progress bar for splash screen
        viewModelScope.launch {
            val steps = 100
            val totalDurationMs = 3000L
            val stepDelay = totalDurationMs / steps
            for (i in 1..steps) {
                delay(stepDelay)
                _progress.value = i / steps.toFloat()
            }
        }

        // When already logged in, silently refresh & register the FCM token.
        // This handles: re-installs, token rotations, first launch after update.
        viewModelScope.launch {
            val loggedIn = preferencesManager.isLoggedIn.first()
            if (loggedIn) {
                try {
                    val token = FirebaseMessaging.getInstance().token.await()
                    deviceTokenRepository.saveDeviceToken(token, "android")
                } catch (_: Exception) {
                    // Non-fatal — FCM token will be registered on next login
                }
            }
        }
    }
}
