package com.christopheraldoo.petheal.util

import android.content.Context
import android.os.Build
import android.view.HapticFeedbackConstants
import android.view.View
import android.view.accessibility.AccessibilityManager

/**
 * Utility object for providing haptic feedback across the app.
 * Respects user's accessibility settings and provides fallbacks.
 */
object HapticFeedback {
    
    /**
     * Perform light haptic feedback (click/tap sensation)
     */
    fun performClick(view: View) {
        if (isHapticEnabled(view.context)) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
            } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
            } else {
                @Suppress("DEPRECATION")
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
            }
        }
    }
    
    /**
     * Perform medium haptic feedback (selection/change sensation)
     */
    fun performSelection(view: View) {
        if (isHapticEnabled(view.context)) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                view.performHapticFeedback(HapticFeedbackConstants.CONTEXT_CLICK)
            } else {
                @Suppress("DEPRECATION")
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
            }
        }
    }
    
    /**
     * Perform heavy haptic feedback (success/confirmation sensation)
     */
    fun performSuccess(view: View) {
        if (isHapticEnabled(view.context)) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
            } else {
                @Suppress("DEPRECATION")
                view.performHapticFeedback(HapticFeedbackConstants.LONG_PRESS)
            }
        }
    }
    
    /**
     * Perform error/rejection haptic feedback (double tap sensation)
     */
    fun performError(view: View) {
        if (isHapticEnabled(view.context)) {
            // Use LONG_PRESS for error feedback on all Android versions
            // DECLINE is only available on Android 11+
            @Suppress("DEPRECATION")
            view.performHapticFeedback(HapticFeedbackConstants.LONG_PRESS)
        }
    }
    
    /**
     * Check if haptic feedback is enabled in accessibility settings
     */
    private fun isHapticEnabled(context: Context): Boolean {
        val accessibilityManager = context.getSystemService(Context.ACCESSIBILITY_SERVICE) as AccessibilityManager
        return !accessibilityManager.isEnabled && !accessibilityManager.isTouchExplorationEnabled
    }
}
