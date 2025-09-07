package com.christopheraldoo.wavesoffood.ui.checkout

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentCheckoutBinding
import com.christopheraldoo.wavesoffood.ui.cart.CartItem
import com.christopheraldoo.wavesoffood.ui.cart.CartRepository
import com.christopheraldoo.wavesoffood.ui.checkout.CheckoutMenuAdapter
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

class CheckoutFragment : Fragment() {
    private var _binding: FragmentCheckoutBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: CheckoutViewModel by viewModels()
    private val cartRepository = CartRepository.getInstance()

    private var cartItems: List<CartItem> = emptyList()
    private var subtotal: Int = 0
    private var total: Int = 0
    private var discount: Int = 0
    private var deliveryFee: Int = 10000 // Default delivery fee Rp 10,000

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentCheckoutBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Load cart data from Firebase
        loadCartData()
        
        setupOrderType()
        setupPaymentMethod()
        setupConfirmButton()
        observeViewModel()
    }

    private fun loadCartData() {
        lifecycleScope.launch {
            cartRepository.getUserCart().collectLatest { result ->
                result.fold(
                    onSuccess = { items ->
                        cartItems = items
                        if (cartItems.isNotEmpty()) {
                            calculateTotals()
                            setupMenuList()
                            setupSummaryCard()
                        } else {
                            // Show empty cart message
                            showError("Your cart is empty. Please add items to continue checkout.")
                        }
                    },
                    onFailure = { exception ->
                        showError("Failed to load cart: ${exception.message}")
                    }
                )
            }
        }
    }
    
    private fun calculateTotals() {
        // Calculate subtotal from cart items
        subtotal = cartItems.sumOf { it.calculateTotalPrice() }
        
        // Calculate discount (example: 10% if subtotal > 100,000)
        discount = if (subtotal > 100000) (subtotal * 0.1).toInt() else 0
        
        // Delivery fee is already set to 10,000 by default
        // Set to 0 for take away orders
        val orderType = binding.spinnerOrderType.selectedItem?.toString()
        deliveryFee = if (orderType == "Take Away") 0 else 10000
        
        // Calculate total
        total = subtotal - discount + deliveryFee
    }

    private fun setupMenuList() {
        val adapter = CheckoutMenuAdapter(cartItems)
        binding.rvCheckoutMenuList.layoutManager = LinearLayoutManager(requireContext())
        binding.rvCheckoutMenuList.adapter = adapter
    }

    private fun setupOrderType() {
        val orderTypes = listOf("Take Away", "Dine In")
        val adapter = ArrayAdapter(requireContext(), android.R.layout.simple_spinner_dropdown_item, orderTypes)
        binding.spinnerOrderType.adapter = adapter
        
        // Add listener to recalculate delivery fee when order type changes
        binding.spinnerOrderType.setOnItemSelectedListener(object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                if (cartItems.isNotEmpty()) {
                    calculateTotals()
                    setupSummaryCard()
                }
            }
            override fun onNothingSelected(parent: AdapterView<*>?) {}
        })
    }

    private fun setupPaymentMethod() {
        val paymentMethods = listOf("QRIS", "Cash", "Bank Transfer")
        val adapter = ArrayAdapter(requireContext(), android.R.layout.simple_spinner_dropdown_item, paymentMethods)
        binding.spinnerPaymentMethod.adapter = adapter
    }

    private fun setupConfirmButton() {
        binding.btnConfirmOrder.setOnClickListener {
            processOrder()
        }
    }
    
    private fun processOrder() {
        val name = binding.etRecipientName.text.toString().trim()
        val address = binding.etRecipientAddress.text.toString().trim()
        val orderType = binding.spinnerOrderType.selectedItem?.toString() ?: ""
        val paymentMethod = binding.spinnerPaymentMethod.selectedItem?.toString() ?: ""
        val notes = binding.etOrderNotes.text?.toString()?.trim() ?: ""
        
        // Validate form
        val validationError = viewModel.validateCheckoutForm(name, address, orderType, paymentMethod)
        if (validationError != null) {
            showError(validationError)
            return
        }
        
        // Show confirmation dialog
        showOrderConfirmationDialog(name, address, orderType, paymentMethod, notes)
    }
    
    private fun showOrderConfirmationDialog(
        name: String,
        address: String, 
        orderType: String,
        paymentMethod: String,
        notes: String
    ) {
        val message = """
            Order Summary:
            • Items: ${cartItems.size} items
            • Total: ${formatRupiah(total)}
            • Type: $orderType
            • Payment: $paymentMethod
            • Recipient: $name
            
            Confirm your order?
        """.trimIndent()
        
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Confirm Order")
            .setMessage(message)
            .setPositiveButton("Place Order") { _, _ ->
                viewModel.createOrder(
                    cartItems = cartItems,
                    subtotal = subtotal,
                    discount = discount,
                    deliveryFee = deliveryFee,
                    total = total,
                    orderType = orderType,
                    paymentMethod = paymentMethod,
                    recipientName = name,
                    deliveryAddress = address,
                    notes = notes
                )
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    private fun observeViewModel() {
        lifecycleScope.launch {
            viewModel.isLoading.collectLatest { isLoading -> 
                binding.btnConfirmOrder.isEnabled = !isLoading
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
                binding.btnConfirmOrder.text = if (isLoading) "Placing Order..." else "Confirm Order"
            }
        }
        
        lifecycleScope.launch {
            viewModel.errorMessage.collectLatest { errorMessage -> 
                errorMessage?.let {
                    showError(it)
                    viewModel.clearError()
                }
            }
        }
        
        lifecycleScope.launch {
            viewModel.orderCreated.collectLatest { orderId -> 
                orderId?.let {
                    showOrderSuccess(it)
                    viewModel.clearOrderCreated()
                }
            }
        }
    }
    
    private fun setupSummaryCard() {
        // Update summary card with calculated values
        binding.tvSummarySubtotal.text = "Subtotal: ${formatRupiah(subtotal)}"
        binding.tvSummaryDiscount.text = "Discount: -${formatRupiah(discount)}"
        binding.tvSummaryDeliveryFee.text = "Delivery Fee: ${formatRupiah(deliveryFee)}"
        binding.tvSummaryTotal.text = "Total: ${formatRupiah(total)}"
    }
    
    private fun formatRupiah(amount: Int): String {
        return "Rp ${String.format("%,d", amount)}"
    }
    
    private fun showError(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }
      private fun showOrderSuccess(orderId: String) {
        // Clear cart immediately after successful order
        clearCart()
        
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Order Placed Successfully!")
            .setMessage("Your order #${orderId.take(8).uppercase()} has been placed successfully. You can track your order or continue shopping.")
            .setPositiveButton("Track Order") { _, _ ->
                navigateToOrderTracking(orderId)
            }
            .setNegativeButton("Continue Shopping") { _, _ ->
                navigateToMenu()
            }
            .setCancelable(false)
            .show()
    }

    private fun clearCart() {
        // Clear cart items from repository
        lifecycleScope.launch {
            val result = cartRepository.clearCart()
            result.onFailure { error ->
                showError("Failed to clear cart: ${error.message}")
            }
        }
    }
    
    private fun navigateToOrderTracking(orderId: String) {
        val bundle = Bundle().apply {
            putString("orderId", orderId)
        }
        try {
            findNavController().navigate(R.id.action_checkoutFragment_to_orderTrackingFragment, bundle)
        } catch (e: Exception) {
            showError("Failed to navigate to order tracking: ${e.message}")
        }
    }    private fun navigateToMenu() {
        try {
            val navController = findNavController()
            
            // First pop back to the main navigation
            navController.popBackStack()
            
            // Then navigate to menu tab using a small delay to ensure UI is ready
            binding.root.postDelayed({
                try {
                    // Since we're in main_nav_graph, navigate directly to menu
                    navController.navigate(R.id.navigation_menu)
                } catch (e: Exception) {
                    Log.e("CheckoutFragment", "Failed secondary navigation to menu", e)
                    showError("Returned to previous screen")
                }
            }, 100)
            
        } catch (e: Exception) {
            Log.e("CheckoutFragment", "Navigation error", e)
            showError("Returning to previous screen...")
            // Fallback to just going back
            try {
                findNavController().popBackStack()
            } catch (popError: Exception) {
                Log.e("CheckoutFragment", "Even popBackStack failed", popError)
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
