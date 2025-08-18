package com.christopheraldoo.wavesoffood.ui.splash

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.animation.AnimationUtils
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentSplashBinding

/**
 * SplashFragment - Fragment untuk menampilkan splash screen
 * 
 * Fragment ini menampilkan logo aplikasi, nama aplikasi, dan tagline
 * dengan animasi loading dots yang bergerak
 * Setelah 3 detik akan otomatis pindah ke OnboardingFragment
 */
class SplashFragment : Fragment() {

    // View binding untuk akses mudah ke view tanpa findViewById
    private var _binding: FragmentSplashBinding? = null
    private val binding get() = _binding!!

    // Handler untuk delay otomatis pindah screen
    private val splashHandler = Handler(Looper.getMainLooper())
    
    // Runnable untuk animasi loading dots
    private var loadingRunnable: Runnable? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        // Inflate layout menggunakan view binding
        _binding = FragmentSplashBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Setup animasi untuk splash screen
        setupAnimations()
        
        // Setup animasi loading dots
        setupLoadingAnimation()
        
        // Auto navigate ke onboarding screen setelah 3 detik
        splashHandler.postDelayed({
            navigateToOnboarding()
        }, SPLASH_DELAY)
    }

    /**
     * Setup animasi untuk elemen-elemen di splash screen
     * Logo akan muncul dengan scale animation
     * Text akan muncul dengan fade in animation
     */
    private fun setupAnimations() {
        // Animasi fade in untuk logo container
        val fadeInAnimation = AnimationUtils.loadAnimation(requireContext(), android.R.anim.fade_in)
        fadeInAnimation.duration = 1000
        binding.logoContainer.startAnimation(fadeInAnimation)
        
        // Animasi slide up untuk app name
        binding.tvAppName.apply {
            alpha = 0f
            translationY = 50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(800)
                .setStartDelay(500)
                .start()
        }
        
        // Animasi slide up untuk tagline
        binding.tvTagline.apply {
            alpha = 0f
            translationY = 30f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(800)
                .start()
        }
    }

    /**
     * Setup animasi loading dots yang bergerak secara berurutan
     * Setiap dot akan berganti-ganti opacity untuk memberikan efek loading
     */
    private fun setupLoadingAnimation() {
        var currentDot = 0
        
        loadingRunnable = object : Runnable {
            override fun run() {
                // Reset semua dots ke opacity rendah
                binding.dot1.alpha = 0.3f
                binding.dot2.alpha = 0.3f
                binding.dot3.alpha = 0.3f
                
                // Highlight dot yang sedang aktif
                when (currentDot) {
                    0 -> binding.dot1.alpha = 1f
                    1 -> binding.dot2.alpha = 1f
                    2 -> binding.dot3.alpha = 1f
                }
                
                // Pindah ke dot berikutnya
                currentDot = (currentDot + 1) % 3
                
                // Ulangi animasi setiap 400ms
                splashHandler.postDelayed(this, DOT_ANIMATION_DELAY)
            }
        }
        
        // Mulai animasi loading dots setelah delay
        splashHandler.postDelayed(loadingRunnable!!, 1200)
    }    /**
     * Navigasi ke OnboardingFragment menggunakan Navigation Component
     * Menggunakan safe navigation untuk menghindari crash
     */
    private fun navigateToOnboarding() {
        try {
            // Pastikan fragment masih attached dan context tersedia
            if (isAdded && context != null) {
                // Navigasi ke onboarding fragment
                findNavController().navigate(
                    R.id.action_splashFragment_to_onboardingFragment
                )
            }
        } catch (e: Exception) {
            // Handle jika navigation gagal
            e.printStackTrace()
        }
    }

    /**
     * Cleanup resources saat fragment di destroy
     * Menghapus semua callback dari handler untuk mencegah memory leak
     */
    override fun onDestroyView() {
        super.onDestroyView()
        
        // Hapus semua callback untuk mencegah memory leak
        splashHandler.removeCallbacksAndMessages(null)
        loadingRunnable?.let {
            splashHandler.removeCallbacks(it)
        }
        
        // Clear view binding reference
        _binding = null
    }

    companion object {
        // Konstanta untuk timing animasi
        private const val SPLASH_DELAY = 3000L // 3 detik
        private const val DOT_ANIMATION_DELAY = 400L // 400ms untuk setiap dot
    }
}
