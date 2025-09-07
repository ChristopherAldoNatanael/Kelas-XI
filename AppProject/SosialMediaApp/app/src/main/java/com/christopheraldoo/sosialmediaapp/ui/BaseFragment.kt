package com.christopheraldoo.sosialmediaapp.ui

import android.content.Context
import android.os.Bundle
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.sosialmediaapp.MainActivity
import com.christopheraldoo.sosialmediaapp.utils.PreferenceManager

/**
 * Base Fragment yang menangani context wrapping untuk language support
 * Semua fragment lain harus extend dari BaseFragment ini
 */
abstract class BaseFragment : Fragment() {
    
    override fun onAttach(context: Context) {
        val preferenceManager = PreferenceManager(context)
        val contextWithLanguage = preferenceManager.applyLanguage(context)
        super.onAttach(contextWithLanguage)
    }
    
    /**
     * Safe navigation method that handles both NavController and fallback approaches
     */
    protected fun safeNavigate(actionId: Int, args: Bundle? = null) {
        try {
            findNavController().navigate(actionId, args)
        } catch (e: Exception) {
            android.util.Log.w("BaseFragment", "Navigation failed with NavController: ${e.message}")
            // Fallback: Let MainActivity handle the navigation
            handleFallbackNavigation(actionId, args)
        }
    }
    
    private fun handleFallbackNavigation(actionId: Int, args: Bundle?) {
        // This is a simplified fallback - in a real app you'd want more sophisticated handling
        val activity = activity as? MainActivity
        activity?.let {
            // For now, just log the navigation attempt
            android.util.Log.i("BaseFragment", "Fallback navigation attempted for action: $actionId")
        }
    }
}
