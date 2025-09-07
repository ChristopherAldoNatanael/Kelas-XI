package com.christopheraldoo.wavesoffood.ui.menu

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.bumptech.glide.Glide
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentMenuDetailBinding
import com.christopheraldoo.wavesoffood.ui.cart.CartRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MenuDetailFragment : Fragment() {

    private var _binding: FragmentMenuDetailBinding? = null
    private val binding get() = _binding!!
    
    private val cartRepository = CartRepository.getInstance()
    
    private var currentQuantity = 1
    private var currentPrice = 0
    
    // Menu data from arguments
    private var menuId: String = ""
    private var menuName: String = ""
    private var menuDescription: String = ""
    private var menuPrice: Int = 0
    private var menuImageUrl: String = ""
    private var menuCategory: String = ""
    
    private val TAG = "MenuDetailFragment"

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        Log.d(TAG, "onCreateView called")
        _binding = FragmentMenuDetailBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called")

        // Get arguments
        arguments?.let { args ->
            menuId = args.getString("menuId", "")
            menuName = args.getString("menuName", "")
            menuDescription = args.getString("menuDescription", "")
            menuPrice = args.getInt("menuPrice", 0)
            menuImageUrl = args.getString("menuImageUrl", "")
            menuCategory = args.getString("menuCategory", "")
        }

        setupClickListeners()
        displayMenuDetails()
        updateTotalPrice()
    }

    private fun setupClickListeners() {
        Log.d(TAG, "setupClickListeners called")
        
        // Back button
        binding.btnBack.setOnClickListener {
            findNavController().navigateUp()
        }
        
        // Quantity buttons
        binding.btnMinus.setOnClickListener {
            if (currentQuantity > 1) {
                currentQuantity--
                updateQuantityDisplay()
                updateTotalPrice()
            }
        }
        
        binding.btnPlus.setOnClickListener {
            currentQuantity++
            updateQuantityDisplay()
            updateTotalPrice()
        }
        
        // Add to cart button
        binding.btnAddToCart.setOnClickListener {
            addToCart()
        }
    }

    private fun displayMenuDetails() {
        Log.d(TAG, "displayMenuDetails called")
        
        try {
            Log.d(TAG, "Displaying menu: $menuName (ID: $menuId)")
            
            // Set menu details
            binding.tvMenuDetailName.text = menuName
            binding.tvMenuDetailDescription.text = menuDescription
            binding.tvMenuDetailPrice.text = formatPrice(menuPrice)
            
            // Set category badge
            if (menuCategory.isNotEmpty()) {
                binding.tvCategoryBadgeOverlay.text = getCategoryDisplayText(menuCategory)
                binding.tvCategoryBadgeOverlay.visibility = View.VISIBLE
            } else {
                binding.tvCategoryBadgeOverlay.visibility = View.GONE
            }
            
            // Load menu image
            if (menuImageUrl.isNotEmpty()) {
                Glide.with(requireContext())
                    .load(menuImageUrl)
                    .placeholder(android.R.drawable.ic_menu_gallery)
                    .error(android.R.drawable.ic_menu_gallery)
                    .into(binding.ivMenuDetailImage)
            } else {
                binding.ivMenuDetailImage.setImageResource(android.R.drawable.ic_menu_gallery)
            }
            
            // Store current price for calculations
            currentPrice = menuPrice
            
        } catch (e: Exception) {
            Log.e(TAG, "Error displaying menu details", e)
            showToast("Error loading menu details")
        }
    }

    private fun updateQuantityDisplay() {
        Log.d(TAG, "updateQuantityDisplay called, quantity: $currentQuantity")
        binding.tvQuantity.text = currentQuantity.toString()
        
        // Enable/disable minus button
        binding.btnMinus.isEnabled = currentQuantity > 1
        binding.btnMinus.alpha = if (currentQuantity > 1) 1.0f else 0.5f
    }

    private fun updateTotalPrice() {
        val totalPrice = currentPrice * currentQuantity
        Log.d(TAG, "updateTotalPrice called: $currentPrice x $currentQuantity = $totalPrice")
        binding.tvTotalPrice.text = formatPrice(totalPrice)
    }

    private fun addToCart() {
        Log.d(TAG, "addToCart called for quantity: $currentQuantity")
        
        if (currentQuantity <= 0) {
            showToast("Pilih jumlah yang valid")
            return
        }
        
        // Show loading state
        binding.btnAddToCart.isEnabled = false
        binding.btnAddToCart.text = "⏳ Menambahkan..."
        
        CoroutineScope(Dispatchers.Main).launch {
            try {
                val result = withContext(Dispatchers.IO) {
                    cartRepository.addToCart(
                        menuId = menuId,
                        menuName = menuName,
                        menuImageUrl = menuImageUrl,
                        price = menuPrice,
                        quantity = currentQuantity,
                        category = menuCategory
                    )
                }
                
                result.fold(
                    onSuccess = {
                        Log.d(TAG, "Successfully added to cart")
                        showToast("✅ Berhasil ditambahkan ke keranjang!")
                        
                        // Navigate back to menu
                        findNavController().navigateUp()
                    },
                    onFailure = { error ->
                        Log.e(TAG, "Failed to add to cart", error)
                        showToast("❌ Gagal menambahkan ke keranjang: ${error.message}")
                    }
                )
                
            } catch (e: Exception) {
                Log.e(TAG, "Error in addToCart coroutine", e)
                showToast("❌ Error: ${e.message}")
            } finally {
                // Reset button state
                binding.btnAddToCart.isEnabled = true
                binding.btnAddToCart.text = "🛒 Tambah ke Keranjang"
            }
        }
    }

    private fun formatPrice(price: Int): String {
        return "Rp ${String.format("%,d", price)}"
    }

    private fun getCategoryDisplayText(category: String): String {
        return when (category.lowercase()) {
            "main_course" -> "🍽️ Main Course"
            "appetizer" -> "🥗 Appetizer"
            "dessert" -> "🍰 Dessert"
            "beverage" -> "🥤 Beverage"
            "snack" -> "🍿 Snack"
            "breakfast" -> "🥐 Breakfast"
            "lunch" -> "🍱 Lunch"
            "dinner" -> "🍽️ Dinner"
            else -> "🍴 ${category.ifEmpty { "Other" }}"
        }
    }

    private fun showToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            // Ignore toast errors safely
        }
    }

    override fun onDestroyView() {
        Log.d(TAG, "onDestroyView called")
        super.onDestroyView()
        _binding = null
    }
} 
