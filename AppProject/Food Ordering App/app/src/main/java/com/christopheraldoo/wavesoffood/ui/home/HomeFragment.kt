package com.christopheraldoo.wavesoffood.ui.home

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import androidx.viewpager2.widget.ViewPager2
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentHomeBinding

class HomeFragment : Fragment() {

    private var _binding: FragmentHomeBinding? = null
    private val binding get() = _binding!!
    
    // Banner carousel
    private lateinit var bannerAdapter: BannerAdapter
    private lateinit var autoSlideHandler: Handler
    private lateinit var autoSlideRunnable: Runnable
    private var currentPage = 0
    private val slideInterval = 3000L // 3 seconds
    
    private val bannerItems = listOf(
        BannerItem(
            title = "Special Offer",
            discount = "30% OFF",
            description = "On your first order",
            buttonText = "Claim Now",
            backgroundDrawable = R.drawable.gradient_banner_1,
            iconDrawable = R.drawable.ic_food_placeholder
        ),
        BannerItem(
            title = "Weekend Deal",
            discount = "FREE DELIVERY",
            description = "Orders above $25",
            buttonText = "Order Now",
            backgroundDrawable = R.drawable.gradient_banner_2,
            iconDrawable = R.drawable.ic_food_placeholder
        ),
        BannerItem(
            title = "Happy Hour",
            discount = "BUY 1 GET 1",
            description = "Between 3-6 PM",
            buttonText = "Get Deal",
            backgroundDrawable = R.drawable.gradient_banner_3,
            iconDrawable = R.drawable.ic_food_placeholder
        ),
        BannerItem(
            title = "New Customer",
            discount = "50% OFF",
            description = "Your second order",
            buttonText = "Explore",
            backgroundDrawable = R.drawable.gradient_banner_4,
            iconDrawable = R.drawable.ic_food_placeholder
        )
    )

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentHomeBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        try {
            setupBannerCarousel()
            setupClickListeners()
            startAutoSlide()
        } catch (e: Exception) {
            showToast("Failed to setup home screen. Please restart app.")
        }
    }
    
    private fun setupBannerCarousel() {
        bannerAdapter = BannerAdapter(bannerItems) { bannerItem ->
            handleBannerClick(bannerItem)
        }
        
        binding.vpBannerCarousel.adapter = bannerAdapter
        binding.vpBannerCarousel.orientation = ViewPager2.ORIENTATION_HORIZONTAL
        
        // Setup indicators
        setupIndicators()
        setCurrentIndicator(0)
        
        // Setup page change callback
        binding.vpBannerCarousel.registerOnPageChangeCallback(object : ViewPager2.OnPageChangeCallback() {
            override fun onPageSelected(position: Int) {
                super.onPageSelected(position)
                currentPage = position
                setCurrentIndicator(position)
            }
        })
        
        autoSlideHandler = Handler(Looper.getMainLooper())
    }
    
    private fun setupIndicators() {
        val indicators = arrayOfNulls<ImageView>(bannerItems.size)
        val layoutParams = LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        )
        layoutParams.setMargins(8, 0, 8, 0)
        
        for (i in indicators.indices) {
            indicators[i] = ImageView(requireContext())
            indicators[i]?.let {
                it.setImageDrawable(
                    ContextCompat.getDrawable(requireContext(), R.drawable.indicator_inactive)
                )
                it.layoutParams = layoutParams
                it.layoutParams.width = 24
                it.layoutParams.height = 8
                binding.layoutIndicators.addView(it)
            }
        }
    }
    
    private fun setCurrentIndicator(position: Int) {
        val childCount = binding.layoutIndicators.childCount
        for (i in 0 until childCount) {
            val imageView = binding.layoutIndicators.getChildAt(i) as ImageView
            if (i == position) {
                imageView.setImageDrawable(
                    ContextCompat.getDrawable(requireContext(), R.drawable.indicator_active)
                )
                imageView.layoutParams.width = 32
            } else {
                imageView.setImageDrawable(
                    ContextCompat.getDrawable(requireContext(), R.drawable.indicator_inactive)
                )
                imageView.layoutParams.width = 24
            }
        }
    }
    
    private fun startAutoSlide() {
        autoSlideRunnable = Runnable {
            if (isAdded && _binding != null) {
                currentPage = (currentPage + 1) % bannerItems.size
                binding.vpBannerCarousel.setCurrentItem(currentPage, true)
                autoSlideHandler.postDelayed(autoSlideRunnable, slideInterval)
            }
        }
        autoSlideHandler.postDelayed(autoSlideRunnable, slideInterval)
    }
    
    private fun stopAutoSlide() {
        if (::autoSlideHandler.isInitialized) {
            autoSlideHandler.removeCallbacks(autoSlideRunnable)
        }
    }
    
    private fun handleBannerClick(bannerItem: BannerItem) {
        when (bannerItem.title) {
            "Special Offer" -> showToast("🎉 ${bannerItem.discount} offer claimed!")
            "Weekend Deal" -> showToast("🚚 Free delivery activated!")
            "Happy Hour" -> showToast("⏰ Buy 1 Get 1 deal applied!")
            "New Customer" -> showToast("🌟 Welcome bonus activated!")
            else -> showToast("Banner clicked: ${bannerItem.title}")
        }
    }    private fun setupClickListeners() {
        // Notification icon
        binding.ivNotifications.setOnClickListener {
            showToast("Notifications clicked! 🔔")
        }
        
        // Search bar - Now functional for better UX
        binding.etSearch.setOnClickListener {
            try {
                findNavController().navigate(R.id.navigation_search)
            } catch (e: Exception) {
                showToast("🔍 Use Search tab at the bottom for better search experience!")
            }
        }
        
        // View Menu button
        binding.tvViewMenu.setOnClickListener {
            navigateToMenu()
        }
        
        // Add to Cart buttons
        binding.btnAddBurger.setOnClickListener {
            addToCart("Classic Burger", "$12.99")
        }
        
        binding.btnAddPizza.setOnClickListener {
            addToCart("Margherita Pizza", "$18.99")
        }
        
        binding.btnAddPasta.setOnClickListener {
            addToCart("Creamy Pasta", "$15.99")
        }
        
        binding.btnAddSalad.setOnClickListener {
            addToCart("Fresh Salad", "$9.99")
        }
        
        // Quick action buttons
        binding.btnQuickOrder.setOnClickListener {
            showToast("Quick Order feature coming soon! ⚡")
        }
        
        binding.btnViewCart.setOnClickListener {
            navigateToCart()
        }
    }private fun addToCart(itemName: String, price: String) {
        showToast("✅ $itemName ($price) added to cart!")
    }

    private fun navigateToMenu() {
        try {
            findNavController().navigate(R.id.navigation_menu)
        } catch (e: Exception) {
            showToast("📋 Menu feature is available! Navigate using bottom menu.")
        }
    }

    private fun navigateToCart() {
        try {
            findNavController().navigate(R.id.navigation_cart)
        } catch (e: Exception) {
            showToast("🛒 Cart feature is available! Navigate using bottom menu.")
        }
    }    private fun showToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            // Ignore toast errors safely
        }
    }    override fun onResume() {
        super.onResume()
        if (::autoSlideHandler.isInitialized) {
            startAutoSlide()
        }
    }
    
    override fun onPause() {
        super.onPause()
        stopAutoSlide()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        stopAutoSlide()
        _binding = null
    }
}