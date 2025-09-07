package com.christopheraldoo.sosialmediaapp.ui

import android.app.AlertDialog
import android.content.Context
import android.content.DialogInterface
import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.sosialmediaapp.MainActivity
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.databinding.FragmentSettingsBinding
import com.christopheraldoo.sosialmediaapp.utils.PreferenceManager

/**
 * Fragment untuk menampilkan pengaturan aplikasi
 * Menggunakan ViewBinding untuk UI binding
 */
class SettingsFragment : Fragment() {

    private var _binding: FragmentSettingsBinding? = null
    private val binding get() = _binding!!
    private lateinit var preferenceManager: PreferenceManager

    override fun onAttach(context: Context) {
        val preferenceManager = PreferenceManager(context)
        val contextWithLanguage = preferenceManager.applyLanguage(context)
        super.onAttach(contextWithLanguage)
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentSettingsBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            preferenceManager = PreferenceManager(requireContext())
            setupClickListeners()
            updateCurrentSettings()
        } catch (e: Exception) {
            e.printStackTrace()
            // Show error message to user
            Toast.makeText(context, "Error initializing settings", Toast.LENGTH_SHORT).show()
        }
    }private fun setupClickListeners() {
        // Theme Setting
        try {
            binding.llThemeSetting.setOnClickListener {
                showThemeDialog()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        
        // Language Setting
        try {
            binding.llLanguageSetting.setOnClickListener {
                showLanguageDialog()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        
        // Privacy Setting - check if exists before setting listener
        try {
            binding.llPrivacySetting?.setOnClickListener {
                showNotImplementedToast("Privacy Settings")
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        
        // About Setting - check if exists before setting listener
        try {
            binding.llAboutSetting?.setOnClickListener {
                showAboutDialog()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        
        // Logout - check if exists before setting listener
        try {
            binding.llLogout?.setOnClickListener {
                showLogoutConfirmation()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }    private fun updateCurrentSettings() {
        try {
            // Update current theme display
            val currentTheme = when (preferenceManager.getThemeMode()) {
                PreferenceManager.THEME_LIGHT -> getString(R.string.theme_light)
                PreferenceManager.THEME_DARK -> getString(R.string.theme_dark)
                PreferenceManager.THEME_SYSTEM -> getString(R.string.theme_system)
                else -> getString(R.string.theme_system)
            }
            binding.tvCurrentTheme?.text = currentTheme
            
            // Update current language display
            val currentLanguage = when (preferenceManager.getLanguage()) {
                PreferenceManager.LANGUAGE_ENGLISH -> getString(R.string.language_english)
                PreferenceManager.LANGUAGE_INDONESIAN -> getString(R.string.language_indonesian)
                else -> getString(R.string.language_english)
            }
            binding.tvCurrentLanguage?.text = currentLanguage
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }    private fun showThemeDialog() {
        try {
            val themeOptions = arrayOf(
                getString(R.string.theme_light),
                getString(R.string.theme_dark),
                getString(R.string.theme_system)
            )
            
            val currentTheme = preferenceManager.getThemeMode()
            val selectedIndex = when (currentTheme) {
                PreferenceManager.THEME_LIGHT -> 0
                PreferenceManager.THEME_DARK -> 1
                PreferenceManager.THEME_SYSTEM -> 2
                else -> 2
            }
            
            AlertDialog.Builder(requireContext())
                .setTitle(getString(R.string.select_theme))                .setSingleChoiceItems(themeOptions, selectedIndex) { dialog: DialogInterface, which: Int ->
                    val selectedTheme = when (which) {
                        0 -> PreferenceManager.THEME_LIGHT
                        1 -> PreferenceManager.THEME_DARK
                        2 -> PreferenceManager.THEME_SYSTEM
                        else -> PreferenceManager.THEME_SYSTEM
                    }
                    
                    preferenceManager.setThemeMode(selectedTheme)
                    updateCurrentSettings()
                    
                    dialog.dismiss()
                    
                    // Show confirmation toast
                    Toast.makeText(
                        requireContext(),
                        "Theme changed to ${themeOptions[which]}",
                        Toast.LENGTH_SHORT
                    ).show()
                }
                .setNegativeButton(getString(R.string.cancel)) { dialog: DialogInterface, _: Int ->
                    dialog.dismiss()
                }
                .show()
        } catch (e: Exception) {
            e.printStackTrace()
            showNotImplementedToast("Theme Settings")
        }
    }    private fun showLanguageDialog() {
        try {
            val languageOptions = arrayOf(
                getString(R.string.language_english),
                getString(R.string.language_indonesian)
            )
            
            val currentLanguage = preferenceManager.getLanguage()
            val selectedIndex = when (currentLanguage) {
                PreferenceManager.LANGUAGE_ENGLISH -> 0
                PreferenceManager.LANGUAGE_INDONESIAN -> 1
                else -> 0
            }
            
            AlertDialog.Builder(requireContext())
                .setTitle(getString(R.string.select_language))                .setSingleChoiceItems(languageOptions, selectedIndex) { dialog: DialogInterface, which: Int ->
                    val selectedLanguage = when (which) {
                        0 -> PreferenceManager.LANGUAGE_ENGLISH
                        1 -> PreferenceManager.LANGUAGE_INDONESIAN
                        else -> PreferenceManager.LANGUAGE_ENGLISH
                    }
                    
                    if (selectedLanguage != currentLanguage) {
                        preferenceManager.setLanguage(selectedLanguage)
                        dialog.dismiss()
                        showRestartDialog()
                    } else {
                        dialog.dismiss()
                    }
                }
                .setNegativeButton(getString(R.string.cancel)) { dialog: DialogInterface, _: Int ->
                    dialog.dismiss()
                }
                .show()
        } catch (e: Exception) {
            e.printStackTrace()
            showNotImplementedToast("Language Settings")
        }
    }private fun showRestartDialog() {
        AlertDialog.Builder(requireContext())
            .setTitle(getString(R.string.language_changed_title))
            .setMessage(getString(R.string.language_changed_message))            .setPositiveButton(getString(R.string.restart)) { dialog: DialogInterface, _: Int ->
                restartApp()
                dialog.dismiss()
            }
            .setNegativeButton(getString(R.string.later)) { dialog: DialogInterface, _: Int ->
                updateCurrentSettings()
                dialog.dismiss()
            }
            .show()
    }    private fun restartApp() {
        try {
            // Clear current task and restart with new language context
            val intent = requireActivity().packageManager
                .getLaunchIntentForPackage(requireActivity().packageName)
            intent?.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_NEW_TASK)
            intent?.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK)
            
            intent?.let {
                startActivity(it)
                
                // Force close current activity
                requireActivity().finishAffinity()
                
                // Kill current process to ensure clean restart
                android.os.Process.killProcess(android.os.Process.myPid())
                System.exit(0)
            }
        } catch (e: Exception) {
            e.printStackTrace()
            // Fallback to simple activity restart
            try {
                val intent = Intent(requireContext(), MainActivity::class.java)
                intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_NEW_TASK)
                startActivity(intent)
                requireActivity().finish()
            } catch (ex: Exception) {
                ex.printStackTrace()
            }
        }
    }

    private fun showNotImplementedToast(feature: String) {
        Toast.makeText(
            context, 
            "$feature feature will be implemented in future versions", 
            Toast.LENGTH_SHORT
        ).show()
    }    private fun showAboutDialog() {
        AlertDialog.Builder(requireContext())
            .setTitle("About SosialMediaApp")
            .setMessage("SosialMediaApp v1.0.0\n\nA Twitter/X clone built with Kotlin and XML.\n\nBuilt with modern Android architecture components including MVVM, LiveData, and Navigation Component.")            .setPositiveButton("OK") { dialog: DialogInterface, _: Int ->
                dialog.dismiss()
            }
            .show()
    }

    private fun showLogoutConfirmation() {
        AlertDialog.Builder(requireContext())
            .setTitle("Logout")
            .setMessage("Are you sure you want to logout?")            .setPositiveButton("Logout") { dialog: DialogInterface, _: Int ->
                performLogout()
                dialog.dismiss()
            }
            .setNegativeButton("Cancel") { dialog: DialogInterface, _: Int ->
                dialog.dismiss()
            }
            .show()
    }

    private fun performLogout() {
        // Clear any stored user session data
        val sharedPrefs = requireContext().getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        sharedPrefs.edit().clear().apply()
        
        // Navigate back to home/feed
        findNavController().popBackStack()
          // Show logout confirmation
        Toast.makeText(
            context, 
            "Logged out successfully", 
            Toast.LENGTH_SHORT
        ).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
