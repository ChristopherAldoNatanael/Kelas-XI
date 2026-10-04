package com.christopheraldoo.petheal.ui.navigation

import androidx.lifecycle.ViewModel
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.local.SessionEvents
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.SharedFlow
import javax.inject.Inject

@HiltViewModel
class NavigationViewModel @Inject constructor(
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    val isLoggedIn: Flow<Boolean> = preferencesManager.isLoggedIn

    /** PHASE 7 (§21): collected once in NavHost → navigate to login. */
    val sessionEvents: SharedFlow<Any> = SessionEvents.events
}
