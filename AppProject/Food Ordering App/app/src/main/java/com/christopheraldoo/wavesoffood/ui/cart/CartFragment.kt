package com.christopheraldoo.wavesoffood.ui.cart

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentCartBinding

/**
 * CartFragment - Fragment untuk menampilkan shopping cart
 * 
 * Fragment ini menampilkan:
 * - Daftar items dalam cart
 * - Quantity controls (+ dan -)
 * - Remove item functionality
 * - Total calculation
 * - Checkout button
 * - Promo code input
 * - Delivery options
 */
class CartFragment : Fragment() {

    // View binding untuk akses mudah ke view tanpa findViewById
    private var _binding: FragmentCartBinding? = null
    private val binding get() = _binding!!

    // Simulasi data cart untuk demo
    private val cartItems = mutableListOf(
        CartItem("Herbal Pancake", 7.0, 2, R.drawable.ic_food_placeholder),
        CartItem("Fruit Salad", 5.0, 1, R.drawable.ic_food_placeholder),
        CartItem("Green Noodle", 15.0, 1, R.drawable.ic_food_placeholder)
    )
    
    private var deliveryFee = 2.5
    private var discount = 0.0

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        // Inflate layout menggunakan view binding
        _binding = FragmentCartBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Setup animasi masuk untuk elemen UI
        setupAnimations()
        
        // Setup cart items
        setupCartItems()
        
        // Setup click listeners
        setupClickListeners()
        
        // Calculate and display totals
        calculateTotals()
        
        // Setup promo code functionality
        setupPromoCode()
    }

    /**
     * Setup animasi masuk untuk semua elemen UI
     */
    private fun setupAnimations() {
        // Animasi untuk header (fade in + slide down)
        binding.tvCartTitle.apply {
            alpha = 0f
            translationY = -30f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(500)
                .setStartDelay(100)
                .start()
        }
        
        // Animasi untuk cart items (slide up + fade in)
        binding.cartItemsContainer.apply {
            alpha = 0f
            translationY = 50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(200)
                .start()
        }
        
        // Animasi untuk total section (slide up + fade in)
        binding.totalSection.apply {
            alpha = 0f
            translationY = 80f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(700)
                .setStartDelay(400)
                .start()
        }
        
        // Animasi untuk checkout button (scale + fade in)
        binding.btnCheckout.apply {
            alpha = 0f
            scaleX = 0.9f
            scaleY = 0.9f
            animate()
                .alpha(1f)
                .scaleX(1f)
                .scaleY(1f)
                .setDuration(500)
                .setStartDelay(600)
                .start()
        }
    }

    /**
     * Setup cart items dengan functionality
     */
    private fun setupCartItems() {
        if (cartItems.isEmpty()) {
            showEmptyCart()
        } else {
            showCartItems()
        }
    }

    /**
     * Setup click listeners untuk berbagai elemen
     */
    private fun setupClickListeners() {
        // Checkout button
        binding.btnCheckout.setOnClickListener {
            animateButtonClick(it) {
                processCheckout()
            }
        }
        
        // Clear cart button
        binding.btnClearCart.setOnClickListener {
            animateButtonClick(it) {
                clearCart()
            }
        }
        
        // Continue shopping
        binding.btnContinueShopping.setOnClickListener {
            animateButtonClick(it) {
                continueShopping()
            }
        }
        
        // Setup item quantity controls
        setupQuantityControls()
    }

    /**
     * Setup quantity controls untuk setiap item
     */
    private fun setupQuantityControls() {
        // Item 1 controls
        binding.btnDecrease1.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(0, -1)
            }
        }
        
        binding.btnIncrease1.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(0, 1)
            }
        }
        
        binding.btnRemove1.setOnClickListener {
            animateButtonClick(it) {
                removeItem(0)
            }
        }
        
        // Item 2 controls
        binding.btnDecrease2.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(1, -1)
            }
        }
        
        binding.btnIncrease2.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(1, 1)
            }
        }
        
        binding.btnRemove2.setOnClickListener {
            animateButtonClick(it) {
                removeItem(1)
            }
        }
        
        // Item 3 controls
        binding.btnDecrease3.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(2, -1)
            }
        }
        
        binding.btnIncrease3.setOnClickListener {
            animateButtonClick(it) {
                updateQuantity(2, 1)
            }
        }
        
        binding.btnRemove3.setOnClickListener {
            animateButtonClick(it) {
                removeItem(2)
            }
        }
    }

    /**
     * Setup promo code functionality
     */
    private fun setupPromoCode() {
        binding.btnApplyPromo.setOnClickListener {
            animateButtonClick(it) {
                applyPromoCode()
            }
        }
        
        binding.etPromoCode.hint = "Enter promo code"
    }

    /**
     * Update quantity item tertentu
     */
    private fun updateQuantity(itemIndex: Int, change: Int) {
        if (itemIndex < cartItems.size) {
            val item = cartItems[itemIndex]
            val newQuantity = item.quantity + change
            
            if (newQuantity > 0) {
                item.quantity = newQuantity
                updateCartItemDisplay(itemIndex)
                calculateTotals()
                showToast("${item.name} quantity updated! 📝")
            } else {
                removeItem(itemIndex)
            }
        }
    }

    /**
     * Remove item dari cart
     */
    private fun removeItem(itemIndex: Int) {
        if (itemIndex < cartItems.size) {
            val itemName = cartItems[itemIndex].name
            cartItems.removeAt(itemIndex)
            
            // Update display
            if (cartItems.isEmpty()) {
                showEmptyCart()
            } else {
                showCartItems()
            }
            
            calculateTotals()
            showToast("$itemName removed from cart! 🗑️")
        }
    }

    /**
     * Update display untuk item tertentu
     */
    private fun updateCartItemDisplay(itemIndex: Int) {
        // Di implementasi nyata, ini akan update RecyclerView item
        // Untuk demo, kita update manual
        when (itemIndex) {
            0 -> binding.tvQuantity1.text = cartItems[0].quantity.toString()
            1 -> binding.tvQuantity2.text = cartItems[1].quantity.toString()
            2 -> binding.tvQuantity3.text = cartItems[2].quantity.toString()
        }
    }

    /**
     * Calculate dan display totals
     */
    private fun calculateTotals() {
        val subtotal = cartItems.sumOf { it.price * it.quantity }
        val total = subtotal + deliveryFee - discount
        
        binding.tvSubtotal.text = "$${"%.2f".format(subtotal)}"
        binding.tvDeliveryFee.text = "$${"%.2f".format(deliveryFee)}"
        binding.tvDiscount.text = "-$${"%.2f".format(discount)}"
        binding.tvTotal.text = "$${"%.2f".format(total)}"
        
        // Update checkout button
        binding.btnCheckout.text = "Checkout - $${"%.2f".format(total)}"
    }

    /**
     * Apply promo code
     */
    private fun applyPromoCode() {
        val promoCode = binding.etPromoCode.text.toString().trim().uppercase()
        
        when (promoCode) {
            "WELCOME10" -> {
                discount = calculateSubtotal() * 0.1
                showToast("✅ Welcome discount applied! 10% off")
            }
            "FREESHIP" -> {
                deliveryFee = 0.0
                showToast("✅ Free shipping applied!")
            }
            "SAVE20" -> {
                discount = calculateSubtotal() * 0.2
                showToast("✅ Save20 applied! 20% off")
            }
            "" -> {
                showToast("Please enter a promo code")
                return
            }
            else -> {
                showToast("❌ Invalid promo code")
                return
            }
        }
        
        calculateTotals()
        binding.etPromoCode.setText("")
    }

    /**
     * Calculate subtotal
     */
    private fun calculateSubtotal(): Double {
        return cartItems.sumOf { it.price * it.quantity }
    }

    /**
     * Show empty cart state
     */
    private fun showEmptyCart() {
        binding.cartItemsContainer.visibility = View.GONE
        binding.emptyCartContainer.visibility = View.VISIBLE
        binding.totalSection.visibility = View.GONE
        binding.btnCheckout.visibility = View.GONE
    }

    /**
     * Show cart with items
     */
    private fun showCartItems() {
        binding.cartItemsContainer.visibility = View.VISIBLE
        binding.emptyCartContainer.visibility = View.GONE
        binding.totalSection.visibility = View.VISIBLE
        binding.btnCheckout.visibility = View.VISIBLE
        
        // Update item displays
        if (cartItems.size > 0) {
            binding.tvQuantity1.text = cartItems[0].quantity.toString()
        }
        if (cartItems.size > 1) {
            binding.tvQuantity2.text = cartItems[1].quantity.toString()
        }
        if (cartItems.size > 2) {
            binding.tvQuantity3.text = cartItems[2].quantity.toString()
        }
    }

    /**
     * Process checkout
     */
    private fun processCheckout() {
        if (cartItems.isEmpty()) {
            showToast("Your cart is empty!")
            return
        }
        
        val total = calculateSubtotal() + deliveryFee - discount
        showToast("🎉 Checkout successful! Total: $${"%.2f".format(total)}")
        
        // Simulate checkout process
        binding.btnCheckout.text = "Processing..."
        binding.btnCheckout.isEnabled = false
        
        binding.btnCheckout.postDelayed({
            // Clear cart after successful checkout
            cartItems.clear()
            showEmptyCart()
            binding.btnCheckout.text = "Checkout"
            binding.btnCheckout.isEnabled = true
            showToast("Order placed successfully! 📦")
        }, 2000)
    }

    /**
     * Clear semua items dari cart
     */
    private fun clearCart() {
        cartItems.clear()
        discount = 0.0
        deliveryFee = 2.5
        showEmptyCart()
        showToast("Cart cleared! 🗑️")
    }    /**
     * Continue shopping - kembali ke menu atau home
     */
    private fun continueShopping() {
        try {
            findNavController().navigate(R.id.navigation_menu)
            showToast("Happy shopping! 🛍️")
        } catch (e: Exception) {
            findNavController().navigate(R.id.navigation_home)
        }
    }

    /**
     * Animasi untuk memberikan feedback visual saat tombol diklik
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
    }

    /**
     * Helper function untuk menampilkan toast message
     */
    private fun showToast(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }

    /**
     * Cleanup resources saat fragment di destroy
     */
    override fun onDestroyView() {
        super.onDestroyView()
        // Clear view binding reference untuk mencegah memory leak
        _binding = null
    }

    /**
     * Data class untuk cart item
     */
    data class CartItem(
        val name: String,
        val price: Double,
        var quantity: Int,
        val imageRes: Int
    )
}