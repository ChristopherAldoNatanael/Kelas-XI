package com.christopheraldoo.wavesoffood.ui.onboarding

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.animation.AnimationUtils
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentOnboardingBinding

/**
 * OnboardingFragment - Fragment untuk menampilkan onboarding screen
 * 
 * Fragment ini menampilkan ilustrasi makanan, judul tentang kualitas makanan restoran,
 * page indicator, dan tombol untuk melanjutkan ke login screen
 * User dapat navigasi ke screen berikutnya dengan tombol Next
 */
class OnboardingFragment : Fragment() {

    // View binding untuk akses mudah ke view tanpa findViewById
    private var _binding: FragmentOnboardingBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        // Inflate layout menggunakan view binding
        _binding = FragmentOnboardingBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Setup animasi masuk untuk elemen-elemen UI
        setupAnimations()
          // Setup click listeners untuk tombol-tombol
        setupClickListeners()
    }

    /**
     * Setup animasi masuk untuk semua elemen UI
     * Setiap elemen akan muncul secara berurutan dengan delay yang berbeda
     */
    private fun setupAnimations() {
        // Animasi untuk illustration container (slide down + fade in)
        binding.illustrationContainer.apply {
            alpha = 0f
            translationY = -100f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(800)
                .setStartDelay(200)
                .start()
        }
        
        // Animasi untuk title container (slide up + fade in)
        binding.titleContainer.apply {
            alpha = 0f
            translationY = 50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(600)
                .start()
        }
          // Animasi untuk tombol Next (scale + fade in)
        binding.btnNext.apply {
            alpha = 0f
            scaleX = 0.8f
            scaleY = 0.8f
            animate()
                .alpha(1f)
                .scaleX(1f)
                .scaleY(1f)
                .setDuration(500)
                .setStartDelay(1000)
                .start()
        }
    }/**
     * Setup click listeners untuk semua tombol yang dapat diklik
     */
    private fun setupClickListeners() {
        // Click listener untuk tombol Next utama
        binding.btnNext.setOnClickListener {
            // Tambahkan animasi klik untuk feedback visual
            animateButtonClick(it) {
                navigateToLogin()
            }
        }    }

    /**
     * Animasi untuk memberikan feedback visual saat tombol diklik
     * @param view tombol yang diklik
     * @param onAnimationEnd callback yang dijalankan setelah animasi selesai
     */
    private fun animateButtonClick(view: View, onAnimationEnd: () -> Unit) {
        view.animate()
            .scaleX(0.95f)
            .scaleY(0.95f)
            .setDuration(100)
            .withEndAction {
                view.animate()
                    .scaleX(1f)
                    .scaleY(1f)
                    .setDuration(100)
                    .withEndAction {
                        onAnimationEnd()
                    }
                    .start()
            }
            .start()
    }    /**
     * Navigasi ke LoginFragment menggunakan Navigation Component
     */
    private fun navigateToLogin() {
        try {
            // Pastikan fragment masih attached dan context tersedia
            if (isAdded && context != null) {
                findNavController().navigate(
                    R.id.action_onboardingFragment_to_loginFragment
                )
            }
        } catch (e: Exception) {
            // Handle jika navigation gagal
            e.printStackTrace()
        }    }

    /**
     * Cleanup resources saat fragment di destroy
     */
    override fun onDestroyView() {
        super.onDestroyView()
        // Clear view binding reference untuk mencegah memory leak
        _binding = null
    }
}
