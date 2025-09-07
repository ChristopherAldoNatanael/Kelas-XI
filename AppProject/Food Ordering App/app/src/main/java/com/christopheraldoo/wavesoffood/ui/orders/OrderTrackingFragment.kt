package com.christopheraldoo.wavesoffood.ui.orders

import android.content.Intent
import android.net.Uri
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
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.databinding.FragmentOrderTrackingBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

/**
 * Fragment untuk tracking status pesanan secara real-time
 */
class OrderTrackingFragment : Fragment() {
    
    private var _binding: FragmentOrderTrackingBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: OrdersViewModel by viewModels()
    private var orderId: String = ""
    private var currentOrder: Order? = null
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        orderId = arguments?.getString("orderId") ?: ""
    }
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentOrderTrackingBinding.inflate(inflater, container, false)
        return binding.root
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        setupUI()
        setupClickListeners()
        observeViewModel()
        
        // Load order details
        if (orderId.isNotEmpty()) {
            viewModel.selectOrder(orderId)
        } else {
            showError("Order ID not found")
        }
    }
    
    private fun setupUI() {
        // Setup animations
        binding.apply {
            cardOrderInfo.apply {
                alpha = 0f
                translationY = -30f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(500)
                    .start()
            }
            
            cardOrderProgress.apply {
                alpha = 0f
                translationY = 30f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(500)
                    .setStartDelay(200)
                    .start()
            }
            
            cardEstimatedTime.apply {
                alpha = 0f
                scaleX = 0.9f
                scaleY = 0.9f
                animate()
                    .alpha(1f)
                    .scaleX(1f)
                    .scaleY(1f)
                    .setDuration(500)
                    .setStartDelay(400)
                    .start()
            }
        }
    }
    
    private fun setupClickListeners() {
        binding.apply {
            btnCancelOrder.setOnClickListener {
                currentOrder?.let { order ->
                    showCancelOrderDialog(order)
                }
            }
            
            btnCallRestaurant.setOnClickListener {
                // Open phone dialer with restaurant number
                val phoneNumber = "tel:+6281234567890" // Replace with actual restaurant number
                val intent = Intent(Intent.ACTION_DIAL, Uri.parse(phoneNumber))
                startActivity(intent)
            }
            
            btnViewDetails.setOnClickListener {
                currentOrder?.let { order ->
                    navigateToOrderDetail(order.id)
                }
            }
            
            btnContactSupport.setOnClickListener {
                // Open support chat or contact
                showContactSupportOptions()
            }
        }
    }
    
    private fun observeViewModel() {
        lifecycleScope.launch {
            viewModel.isLoading.collectLatest { isLoading ->
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
            }
        }
        
        lifecycleScope.launch {
            viewModel.errorMessage.collectLatest { errorMessage ->
                errorMessage?.let {
                    showError(it)
                }
            }
        }
        
        lifecycleScope.launch {
            viewModel.selectedOrder.collectLatest { order ->
                order?.let {
                    currentOrder = it
                    updateOrderInfo(it)
                    updateOrderProgress(it)
                    updateEstimatedTime(it)
                    updateActionButtons(it)
                }
            }
        }
    }
    
    private fun updateOrderInfo(order: Order) {
        binding.apply {
            tvOrderId.text = "Order #${order.id.take(8).uppercase()}"
            tvOrderDate.text = order.getFormattedCreatedTime()
            tvOrderTotal.text = order.getFormattedTotal()
            
            // Update status chip
            chipOrderStatus.apply {
                text = getStatusDisplayName(order.status)
                chipBackgroundColor = ContextCompat.getColorStateList(
                    requireContext(), 
                    getStatusColor(order.status)
                )
            }
        }
    }
    
    private fun updateOrderProgress(order: Order) {
        val status = order.status
        
        binding.apply {
            // Reset all indicators
            resetProgressIndicators()
            
            // Update progress based on current status
            when (status) {
                OrderStatus.PENDING, OrderStatus.CONFIRMED -> {
                    activateStep(indicatorConfirmed, iconConfirmed = true)
                    tvConfirmedTime.text = order.getFormattedCreatedTime()
                    tvConfirmedTime.visibility = View.VISIBLE
                }
                OrderStatus.PREPARING -> {
                    activateStep(indicatorConfirmed, iconConfirmed = true)
                    activateStep(indicatorPreparation, showProgress = true)
                    lineToPreparation.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    tvConfirmedTime.text = order.getFormattedCreatedTime()
                    tvConfirmedTime.visibility = View.VISIBLE
                    progressPreparation.visibility = View.VISIBLE
                    iconPreparationDone.visibility = View.GONE
                }
                OrderStatus.READY -> {
                    activateStep(indicatorConfirmed, iconConfirmed = true)
                    activateStep(indicatorPreparation, iconConfirmed = true)
                    activateStep(indicatorReady, iconConfirmed = true)
                    lineToPreparation.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    lineToReady.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    tvReadyTitle.text = if (order.orderType.name == "DELIVERY") "Ready for Delivery" else "Ready for Pickup"
                    tvReadyTitle.setTextColor(ContextCompat.getColor(requireContext(), R.color.primary_text))
                    progressPreparation.visibility = View.GONE
                    iconPreparationDone.visibility = View.VISIBLE
                }
                OrderStatus.DELIVERING -> {
                    activateStep(indicatorConfirmed, iconConfirmed = true)
                    activateStep(indicatorPreparation, iconConfirmed = true)
                    activateStep(indicatorReady, iconConfirmed = true)
                    lineToPreparation.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    lineToReady.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    tvReadyTitle.text = "On the Way"
                    tvReadyTitle.setTextColor(ContextCompat.getColor(requireContext(), R.color.primary_text))
                }
                OrderStatus.COMPLETED -> {
                    activateStep(indicatorConfirmed, iconConfirmed = true)
                    activateStep(indicatorPreparation, iconConfirmed = true)
                    activateStep(indicatorReady, iconConfirmed = true)
                    activateStep(indicatorCompleted, iconConfirmed = true)
                    lineToPreparation.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    lineToReady.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    lineToCompleted.background = ContextCompat.getDrawable(requireContext(), R.color.success_green)
                    tvCompletedTime.text = formatTimestamp(order.completedAt)
                    tvCompletedTime.visibility = View.VISIBLE
                }
                OrderStatus.CANCELLED -> {
                    // Show cancelled state
                    resetProgressIndicators()
                    activateStep(indicatorConfirmed, cancelled = true)
                }
            }
        }
    }
    
    private fun resetProgressIndicators() {
        binding.apply {
            // Reset all indicators to inactive
            indicatorConfirmed.background = ContextCompat.getDrawable(requireContext(), android.R.color.darker_gray)
            indicatorPreparation.background = ContextCompat.getDrawable(requireContext(), android.R.color.darker_gray)
            indicatorReady.background = ContextCompat.getDrawable(requireContext(), android.R.color.darker_gray)
            indicatorCompleted.background = ContextCompat.getDrawable(requireContext(), android.R.color.darker_gray)
            
            // Reset lines
            lineToPreparation.background = ContextCompat.getDrawable(requireContext(), R.color.divider_gray)
            lineToReady.background = ContextCompat.getDrawable(requireContext(), R.color.divider_gray)
            lineToCompleted.background = ContextCompat.getDrawable(requireContext(), R.color.divider_gray)
            
            // Hide progress and times
            progressPreparation.visibility = View.GONE
            iconPreparationDone.visibility = View.GONE
            tvConfirmedTime.visibility = View.GONE
            tvReadyTime.visibility = View.GONE
            tvCompletedTime.visibility = View.GONE
            
            // Reset text colors
            tvReadyTitle.setTextColor(ContextCompat.getColor(requireContext(), R.color.secondary_text))
        }
    }
    
    private fun activateStep(
        indicator: View, 
        showProgress: Boolean = false, 
        iconConfirmed: Boolean = false,
        cancelled: Boolean = false
    ) {
        if (cancelled) {
            indicator.background = ContextCompat.getDrawable(requireContext(), R.color.error_color)
        } else {
            indicator.background = ContextCompat.getDrawable(requireContext(), R.color.colorPrimary)
        }
    }
    
    private fun updateEstimatedTime(order: Order) {
        binding.apply {
            if (order.status in listOf(OrderStatus.PREPARING, OrderStatus.READY)) {
                cardEstimatedTime.visibility = View.VISIBLE
                
                if (order.estimatedDeliveryTime > 0) {
                    val estimatedTime = formatTimestamp(order.estimatedDeliveryTime)
                    tvEstimatedTime.text = estimatedTime
                    
                    // Calculate time left
                    val timeLeft = (order.estimatedDeliveryTime - System.currentTimeMillis()) / (1000 * 60)
                    if (timeLeft > 0) {
                        chipTimeLeft.text = "${timeLeft}min left"
                        chipTimeLeft.visibility = View.VISIBLE
                    } else {
                        chipTimeLeft.visibility = View.GONE
                    }
                } else {
                    tvEstimatedTime.text = "15-20 minutes"
                    chipTimeLeft.visibility = View.GONE
                }
            } else {
                cardEstimatedTime.visibility = View.GONE
            }
        }
    }
    
    private fun updateActionButtons(order: Order) {
        binding.apply {
            when (order.status) {
                OrderStatus.PENDING, OrderStatus.CONFIRMED -> {
                    btnCancelOrder.visibility = View.VISIBLE
                    btnCallRestaurant.visibility = View.VISIBLE
                    btnViewDetails.visibility = View.VISIBLE
                }
                OrderStatus.PREPARING, OrderStatus.READY, OrderStatus.DELIVERING -> {
                    btnCancelOrder.visibility = View.GONE
                    btnCallRestaurant.visibility = View.VISIBLE
                    btnViewDetails.visibility = View.VISIBLE
                }
                OrderStatus.COMPLETED, OrderStatus.CANCELLED -> {
                    btnCancelOrder.visibility = View.GONE
                    btnCallRestaurant.visibility = View.GONE
                    btnViewDetails.visibility = View.VISIBLE
                }
            }
        }
    }
    
    private fun showCancelOrderDialog(order: Order) {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Cancel Order")
            .setMessage("Are you sure you want to cancel order ${order.id.take(8).uppercase()}?")
            .setPositiveButton("Cancel Order") { _, _ ->
                viewModel.cancelOrder(order.id, "Cancelled by user")
                Toast.makeText(requireContext(), "Order cancelled successfully", Toast.LENGTH_SHORT).show()
            }
            .setNegativeButton("Keep Order", null)
            .show()
    }
    
    private fun showContactSupportOptions() {
        val options = arrayOf("Live Chat", "Call Support", "Email Support")
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Contact Support")
            .setItems(options) { _, which ->
                when (which) {
                    0 -> {
                        // Open live chat (placeholder)
                        Toast.makeText(requireContext(), "Live chat feature coming soon", Toast.LENGTH_SHORT).show()
                    }
                    1 -> {
                        // Call support
                        val phoneNumber = "tel:+6281234567890"
                        val intent = Intent(Intent.ACTION_DIAL, Uri.parse(phoneNumber))
                        startActivity(intent)
                    }                2 -> {
                        // Email support
                        val emailIntent = Intent(Intent.ACTION_SENDTO).apply {
                            data = Uri.parse("mailto:support@wavesoffood.com")
                            putExtra(Intent.EXTRA_SUBJECT, "Order Support - ${currentOrder?.id}")
                        }
                        startActivity(emailIntent)
                    }
                }
            }
            .show()
    }
    
    private fun navigateToOrderDetail(orderId: String) {
        val bundle = Bundle().apply {
            putString("orderId", orderId)
        }
        findNavController().navigate(R.id.action_orderTrackingFragment_to_orderDetailFragment, bundle)
    }
    
    private fun showError(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }
    
    private fun getStatusDisplayName(status: OrderStatus): String {
        return when (status) {
            OrderStatus.PENDING -> "Pending"
            OrderStatus.CONFIRMED -> "Confirmed"
            OrderStatus.PREPARING -> "Preparing"
            OrderStatus.READY -> "Ready"
            OrderStatus.DELIVERING -> "On the Way"
            OrderStatus.COMPLETED -> "Completed"
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
    
    private fun formatTimestamp(timestamp: Long): String {
        if (timestamp == 0L) return ""
        val format = SimpleDateFormat("MMM dd, yyyy • HH:mm", Locale.getDefault())
        return format.format(Date(timestamp))
    }
    
    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
