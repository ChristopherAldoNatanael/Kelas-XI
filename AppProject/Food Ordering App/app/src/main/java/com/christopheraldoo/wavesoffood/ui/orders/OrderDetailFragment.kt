package com.christopheraldoo.wavesoffood.ui.orders

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.databinding.FragmentOrderDetailBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

/**
 * Fragment untuk menampilkan detail order
 */
class OrderDetailFragment : Fragment() {
    
    private var _binding: FragmentOrderDetailBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: OrdersViewModel by viewModels()
    private lateinit var orderItemsAdapter: OrderItemsAdapter
    
    private var orderId: String = ""
    private var currentOrder: Order? = null
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentOrderDetailBinding.inflate(inflater, container, false)
        return binding.root
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        getOrderId()
        setupRecyclerView()
        setupClickListeners()
        observeViewModel()
        
        if (orderId.isNotEmpty()) {
            viewModel.selectOrder(orderId)
        }
    }
    
    private fun getOrderId() {
        orderId = arguments?.getString("orderId") ?: ""
    }
    
    private fun setupRecyclerView() {
        orderItemsAdapter = OrderItemsAdapter()
        binding.rvOrderItems.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = orderItemsAdapter
            setHasFixedSize(true)
        }
    }
      private fun setupClickListeners() {
        // Note: No back button in layout, using navigation component's built-in back handling
        
        binding.btnCancelOrder.setOnClickListener {
            showCancelOrderDialog()
        }
        
        binding.btnTrackOrder.setOnClickListener {
            navigateToOrderTracking()
        }
        
        binding.btnReorder.setOnClickListener {
            showReorderDialog()
        }
    }
    
    private fun observeViewModel() {        lifecycleScope.launch {
            viewModel.selectedOrder.collectLatest { order ->
                order?.let { 
                    currentOrder = it
                    displayOrderDetails(it) 
                }
            }
        }
        
        lifecycleScope.launch {
            viewModel.isLoading.collectLatest { isLoading ->
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
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
    }
    
    private fun displayOrderDetails(order: Order) {
        with(binding) {
            // Order header
            tvOrderId.text = "Order #${order.id.take(8).uppercase()}"
            tvOrderDate.text = order.getFormattedCreatedTime()
              // Status
            binding.chipOrderStatus.text = getStatusDisplayName(order.status)
            binding.chipOrderStatus.chipBackgroundColor = ContextCompat.getColorStateList(requireContext(), getStatusColor(order.status))
            
            // Progress
            binding.progressOrderStatus.progress = getStatusProgress(order.status)
            binding.progressOrderStatus.progressTintList = ContextCompat.getColorStateList(requireContext(), getStatusColor(order.status))
            
            // Customer info
            tvRecipientName.text = order.recipientName
            tvDeliveryAddress.text = order.deliveryAddress
            tvOrderType.text = when (order.orderType) {
                com.christopheraldoo.wavesoffood.data.model.OrderType.TAKE_AWAY -> "Take Away"
                com.christopheraldoo.wavesoffood.data.model.OrderType.DINE_IN -> "Dine In"
            }
            tvPaymentMethod.text = when (order.paymentMethod) {
                com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CASH -> "Cash"
                com.christopheraldoo.wavesoffood.data.model.PaymentMethod.QRIS -> "QRIS"
                com.christopheraldoo.wavesoffood.data.model.PaymentMethod.BANK_TRANSFER -> "Bank Transfer"
                com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CREDIT_CARD -> "Credit Card"
            }
            
            // Order summary
            tvSubtotal.text = order.getFormattedSubtotal()
            tvDiscount.text = if (order.discount > 0) "-${order.getFormattedDiscount()}" else "Rp 0"
            tvDeliveryFee.text = order.getFormattedDeliveryFee()
            tvTotal.text = order.getFormattedTotal()
              // Estimated delivery time - not available in current layout
            // if (order.estimatedDeliveryTime > 0) {
            //     tvEstimatedTime.text = order.getFormattedDeliveryTime()
            //     deliveryTimeContainer.visibility = View.VISIBLE
            // } else {
            //     deliveryTimeContainer.visibility = View.GONE
            // }
            
            // Notes
            if (order.notes.isNotEmpty()) {
                binding.tvNotes.text = order.notes
                binding.layoutNotes.visibility = View.VISIBLE
            } else {
                binding.layoutNotes.visibility = View.GONE
            }
            
            // Setup buttons based on status
            setupButtonsForStatus(order)
            
            // Display order items
            orderItemsAdapter.submitList(order.items)
        }
    }
    
    private fun setupButtonsForStatus(order: Order) {
        with(binding) {
            when (order.status) {
                OrderStatus.PENDING, OrderStatus.CONFIRMED -> {
                    btnCancelOrder.visibility = View.VISIBLE
                    btnTrackOrder.visibility = View.VISIBLE
                    btnReorder.visibility = View.GONE
                }
                OrderStatus.PREPARING, OrderStatus.READY, OrderStatus.DELIVERING -> {
                    btnCancelOrder.visibility = View.GONE
                    btnTrackOrder.visibility = View.VISIBLE
                    btnReorder.visibility = View.GONE
                }
                OrderStatus.COMPLETED -> {
                    btnCancelOrder.visibility = View.GONE
                    btnTrackOrder.visibility = View.GONE
                    btnReorder.visibility = View.VISIBLE
                }
                OrderStatus.CANCELLED -> {
                    btnCancelOrder.visibility = View.GONE
                    btnTrackOrder.visibility = View.GONE
                    btnReorder.visibility = View.VISIBLE
                }
            }
        }
    }
    
    private fun getStatusDisplayName(status: OrderStatus): String {
        return when (status) {
            OrderStatus.PENDING -> "Order Placed"
            OrderStatus.CONFIRMED -> "Confirmed"
            OrderStatus.PREPARING -> "Preparing Your Food"
            OrderStatus.READY -> "Ready for Pickup"
            OrderStatus.DELIVERING -> "On the Way"
            OrderStatus.COMPLETED -> "Delivered"
            OrderStatus.CANCELLED -> "Cancelled"
        }
    }
    
    private fun getStatusColor(status: OrderStatus): Int {
        return when (status) {
            OrderStatus.PENDING -> R.color.status_pending
            OrderStatus.CONFIRMED -> R.color.status_confirmed
            OrderStatus.PREPARING -> R.color.status_preparing
            OrderStatus.READY -> R.color.status_ready
            OrderStatus.DELIVERING -> R.color.status_delivering
            OrderStatus.COMPLETED -> R.color.status_completed
            OrderStatus.CANCELLED -> R.color.status_cancelled
        }
    }
    
    private fun getStatusProgress(status: OrderStatus): Int {
        return when (status) {
            OrderStatus.PENDING -> 15
            OrderStatus.CONFIRMED -> 30
            OrderStatus.PREPARING -> 60
            OrderStatus.READY -> 80
            OrderStatus.DELIVERING -> 90
            OrderStatus.COMPLETED -> 100
            OrderStatus.CANCELLED -> 0
        }
    }
    
    private fun showCancelOrderDialog() {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Cancel Order")
            .setMessage("Are you sure you want to cancel this order?")
            .setPositiveButton("Yes, Cancel") { _, _ ->
                viewModel.cancelOrder(orderId, "Cancelled by customer")
            }
            .setNegativeButton("Keep Order", null)
            .show()
    }
      private fun showReorderDialog() {
        currentOrder?.let { order ->
            MaterialAlertDialogBuilder(requireContext())
                .setTitle("Reorder")
                .setMessage("Add these items to your cart?")
                .setPositiveButton("Add to Cart") { _, _ ->                viewModel.reorderItems(order)
                }
                .setNegativeButton("Cancel", null)
                .show()
        }
    }
    
    private fun navigateToOrderTracking() {
        val bundle = Bundle().apply {
            putString("orderId", orderId)
        }
        findNavController().navigate(R.id.action_orderDetailFragment_to_orderTrackingFragment, bundle)
    }
    
    private fun showError(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }
    
    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
