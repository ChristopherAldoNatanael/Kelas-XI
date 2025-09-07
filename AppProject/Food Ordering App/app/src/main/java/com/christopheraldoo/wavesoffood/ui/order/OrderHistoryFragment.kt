package com.christopheraldoo.wavesoffood.ui.order

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
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.databinding.FragmentOrderHistoryBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.tabs.TabLayout
import kotlinx.coroutines.flow.collectLatest

/**
 * Fragment untuk menampilkan riwayat pesanan user
 */
class OrderHistoryFragment : Fragment() {
    
    private var _binding: FragmentOrderHistoryBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: OrderViewModel by viewModels()
    private lateinit var orderAdapter: OrderAdapter
    
    private var currentFilter: OrderStatus? = null
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentOrderHistoryBinding.inflate(inflater, container, false)
        return binding.root
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        setupUI()
        setupRecyclerView()
        setupTabLayout()
        observeViewModel()
        setupClickListeners()
    }
    
    private fun setupUI() {
        // Setup animations
        binding.apply {
            tvOrderHistoryTitle.apply {
                alpha = 0f
                translationY = -30f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(500)
                    .start()
            }
            
            orderStatsCard.apply {
                alpha = 0f
                translationY = 50f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(500)
                    .setStartDelay(200)
                    .start()
            }
        }
    }
    
    private fun setupRecyclerView() {        orderAdapter = OrderAdapter(
            onOrderClick = { order ->
                // Navigate to order detail - temporary implementation
                showToast("Order detail view coming soon!")
            },
            onCancelClick = { order ->
                showCancelOrderDialog(order.id)
            },
            onReorderClick = { order ->
                // TODO: Implement reorder functionality
                showToast("Reorder feature coming soon!")
            }
        )
        
        binding.rvOrderHistory.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = orderAdapter
        }
    }
    
    private fun setupTabLayout() {
        binding.tabLayout.apply {
            addTab(newTab().setText("All"))
            addTab(newTab().setText("Pending"))
            addTab(newTab().setText("Completed"))
            addTab(newTab().setText("Cancelled"))
            
            addOnTabSelectedListener(object : TabLayout.OnTabSelectedListener {
                override fun onTabSelected(tab: TabLayout.Tab?) {
                    when (tab?.position) {
                        0 -> {
                            currentFilter = null
                            observeAllOrders()
                        }
                        1 -> {
                            currentFilter = OrderStatus.PENDING
                            observeFilteredOrders(OrderStatus.PENDING)
                        }
                        2 -> {
                            currentFilter = OrderStatus.COMPLETED
                            observeFilteredOrders(OrderStatus.COMPLETED)
                        }
                        3 -> {
                            currentFilter = OrderStatus.CANCELLED
                            observeFilteredOrders(OrderStatus.CANCELLED)
                        }
                    }
                }
                
                override fun onTabUnselected(tab: TabLayout.Tab?) {}
                override fun onTabReselected(tab: TabLayout.Tab?) {}
            })
        }
    }
    
    private fun setupClickListeners() {
        binding.apply {
            // Refresh button
            btnRefresh.setOnClickListener {
                animateButtonClick(it) {
                    viewModel.refreshOrders()
                }
            }
            
            // Back button
            btnBack.setOnClickListener {
                animateButtonClick(it) {
                    findNavController().navigateUp()
                }
            }
        }
    }
    
    private fun observeViewModel() {
        viewLifecycleOwner.lifecycleScope.launchWhenStarted {
            // Observe loading state
            viewModel.isLoading.collectLatest { isLoading ->
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
                binding.btnRefresh.isEnabled = !isLoading
            }
        }
        
        viewLifecycleOwner.lifecycleScope.launchWhenStarted {
            // Observe error messages
            viewModel.errorMessage.collectLatest { error ->
                error?.let {
                    showToast(it)
                    viewModel.clearError()
                }
            }
        }
        
        viewLifecycleOwner.lifecycleScope.launchWhenStarted {
            // Observe order statistics
            viewModel.orderStats.collectLatest { stats ->
                updateStatsUI(stats)
            }
        }
        
        // Initially observe all orders
        observeAllOrders()
    }
    
    private fun observeAllOrders() {
        viewLifecycleOwner.lifecycleScope.launchWhenStarted {
            viewModel.orders.collectLatest { orders ->
                orderAdapter.submitList(orders)
                updateEmptyState(orders.isEmpty())
            }
        }
    }
    
    private fun observeFilteredOrders(status: OrderStatus) {
        viewLifecycleOwner.lifecycleScope.launchWhenStarted {
            viewModel.getOrdersByStatus(status).collectLatest { orders ->
                orderAdapter.submitList(orders)
                updateEmptyState(orders.isEmpty())
            }
        }
    }
    
    private fun updateStatsUI(stats: Map<String, Int>) {
        binding.apply {
            tvTotalOrders.text = stats["totalOrders"]?.toString() ?: "0"
            tvCompletedOrders.text = stats["completedOrders"]?.toString() ?: "0"
            tvTotalSpent.text = "Rp ${String.format("%,d", stats["totalSpent"] ?: 0)}"
            tvPendingOrders.text = stats["pendingOrders"]?.toString() ?: "0"
        }
    }
    
    private fun updateEmptyState(isEmpty: Boolean) {
        binding.apply {
            if (isEmpty) {
                rvOrderHistory.visibility = View.GONE
                emptyStateContainer.visibility = View.VISIBLE
                
                val message = when (currentFilter) {
                    OrderStatus.PENDING -> "No pending orders"
                    OrderStatus.COMPLETED -> "No completed orders yet"
                    OrderStatus.CANCELLED -> "No cancelled orders"
                    else -> "No orders found"
                }
                tvEmptyMessage.text = message
            } else {
                rvOrderHistory.visibility = View.VISIBLE
                emptyStateContainer.visibility = View.GONE
            }
        }
    }
    
    private fun showCancelOrderDialog(orderId: String) {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Cancel Order")
            .setMessage("Are you sure you want to cancel this order?")
            .setPositiveButton("Yes") { _, _ ->
                viewModel.cancelOrder(orderId, "Cancelled by user")
            }
            .setNegativeButton("No", null)
            .show()
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
