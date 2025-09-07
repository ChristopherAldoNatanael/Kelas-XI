package com.christopheraldoo.wavesoffood.ui.cart

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentCartBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.flow.collectLatest
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.NavController
import androidx.navigation.NavOptions
import androidx.navigation.fragment.FragmentNavigatorExtras

class CartFragment : Fragment() {

    private var _binding: FragmentCartBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: CartViewModel by viewModels()
    private lateinit var cartAdapter: CartAdapter
    private var discount: Int = 0
    private var deliveryFee: Int = 5000 // Rp 5.000

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentCartBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        setupAnimations()
        setupClickListeners()
        setupPromoCode()
        setupRecyclerView()
        observeCartData()
    }

    override fun onResume() {
        super.onResume()
        // Refresh cart data when returning to cart fragment
        // This ensures cart is updated if it was cleared during checkout
        viewModel.refreshCart()
    }

    private fun setupAnimations() {
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
        // Remove cartItemsContainer animation
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

    private fun setupClickListeners() {
        binding.btnCheckout.setOnClickListener {
            animateButtonClick(it) {
                processCheckout()
            }
        }
        
        binding.btnClearCart.setOnClickListener {
            animateButtonClick(it) {
                clearCart()
            }
        }
        
        binding.btnContinueShopping.setOnClickListener {
            animateButtonClick(it) {
                continueShopping()
            }
        }
    }

    private fun setupPromoCode() {
        binding.btnApplyPromo.setOnClickListener {
            animateButtonClick(it) {
                applyPromoCode()
            }
        }
        
        binding.etPromoCode.hint = "Enter promo code"
    }

    private fun setupRecyclerView() {
        cartAdapter = CartAdapter(
            onQuantityChanged = { item, newQty ->
                viewModel.updateCartItemQuantity(item.id, newQty)
            },
            onItemRemoved = { item ->
                viewModel.removeFromCart(item.id)
            }
        )
        binding.rvCartList.layoutManager = LinearLayoutManager(requireContext())
        binding.rvCartList.adapter = cartAdapter
    }

    private fun observeCartData() {
        lifecycleScope.launchWhenStarted {
            viewModel.cartItems.collectLatest { items ->
                cartAdapter.submitList(items)
                if (items.isEmpty()) {
                    showEmptyCart()
                } else {
                    showCartItems()
                }
                calculateTotals(items)
            }
        }
    }

    private fun calculateTotals(items: List<CartItem>) {
        val subtotal = items.sumOf { it.price * it.quantity }
        val total = subtotal + deliveryFee - discount
        binding.tvSubtotal.text = "Rp ${subtotal}"
        binding.tvDeliveryFee.text = "Rp ${deliveryFee}"
        binding.tvDiscount.text = "-Rp ${discount}"
        binding.tvTotal.text = "Rp ${total}"
        binding.btnCheckout.text = "Checkout - Rp ${total}"
    }

    private fun applyPromoCode() {
        val promoCode = binding.etPromoCode.text.toString().trim().uppercase()
        
        when (promoCode) {
            "WELCOME10" -> {
                discount = (calculateSubtotal() * 0.1).toInt()
                showToast("✅ Welcome discount applied! 10% off")
            }
            "FREESHIP" -> {
                deliveryFee = 0
                showToast("✅ Free shipping applied!")
            }
            "SAVE20" -> {
                discount = (calculateSubtotal() * 0.2).toInt()
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
        
        viewModel.cartItems.value?.let { calculateTotals(it) }
        binding.etPromoCode.setText("")
    }

    private fun calculateSubtotal(): Int {
        return viewModel.cartItems.value?.sumOf { it.price * it.quantity } ?: 0
    }

    private fun showEmptyCart() {
        binding.rvCartList.visibility = View.GONE
        binding.emptyCartContainer.visibility = View.VISIBLE
        binding.totalSection.visibility = View.GONE
        binding.btnCheckout.visibility = View.GONE
    }

    private fun showCartItems() {
        binding.rvCartList.visibility = View.VISIBLE
        binding.emptyCartContainer.visibility = View.GONE
        binding.totalSection.visibility = View.VISIBLE
        binding.btnCheckout.visibility = View.VISIBLE
    }

    private fun processCheckout() {
        val items = viewModel.cartItems.value ?: emptyList()
        if (items.isEmpty()) {
            showToast("Your cart is empty!")
            return
        }
        val subtotal = items.sumOf { it.price * it.quantity }
        val total = subtotal + deliveryFee - discount
        // Pass cart items and totals to CheckoutFragment using Bundle
        val bundle = Bundle().apply {
            putParcelableArrayList("cartItems", ArrayList(items))
            putInt("subtotal", subtotal)
            putInt("total", total)
            putInt("discount", discount)
            putInt("deliveryFee", deliveryFee)
        }
        findNavController().navigate(R.id.action_cartFragment_to_checkoutFragment, bundle)
    }

    private fun clearCart() {
        viewModel.clearCart()
        discount = 0
        deliveryFee = 5000
        showEmptyCart()
        showToast("Cart cleared! 🗑️")
    }

    private fun continueShopping() {
        try {
            findNavController().navigate(R.id.navigation_menu)
            showToast("Happy shopping! 🛍️")
        } catch (e: Exception) {
            findNavController().navigate(R.id.navigation_home)
        }
    }

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

    private fun showToast(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
