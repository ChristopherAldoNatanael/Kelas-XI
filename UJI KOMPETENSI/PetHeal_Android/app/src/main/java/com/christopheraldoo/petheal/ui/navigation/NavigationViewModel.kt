package com.christopheraldoo.petheal.ui.navigation

import androidx.lifecycle.ViewModel
import com.christopheraldoo.petheal.data.local.PreferencesManager
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject

@HiltViewModel
class NavigationViewModel @Inject constructor(
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    val isLoggedIn: Flow<Boolean> = preferencesManager.isLoggedIn
}
