package com.christopheraldoo.wavesoffood.ui.orders

import android.os.Bundle
import android.util.Log
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
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.data.repository.OrderRepository
import com.christopheraldoo.wavesoffood.databinding.FragmentOrdersBinding
import com.google.android.material.chip.Chip
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

/**
 * Fragment untuk menampilkan riwayat pesanan user
 */
class OrdersFragment : Fragment() {
    
    private var _binding: FragmentOrdersBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: OrdersViewModel by viewModels()
    private lateinit var ordersAdapter: OrdersAdapter
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentOrdersBinding.inflate(inflater, container, false)
        return binding.root
    }
      override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        Log.d("OrdersFragment", "OrdersFragment onViewCreated called")
        
        setupRecyclerView()
        setupFilterChips()
        setupSearchView()
        setupSwipeRefresh()
        observeViewModel()
        setupAnimations()
        
        // Load orders when fragment is created
        Log.d("OrdersFragment", "Calling viewModel.loadOrders()")
        viewModel.loadOrders()
        
        // Test Firebase data
        Log.d("OrdersFragment", "Testing Firebase order data")
        OrderRepository.getInstance().testOrderData()
    }
    
    private fun setupRecyclerView() {
        ordersAdapter = OrdersAdapter(
            onOrderClick = { order ->
                navigateToOrderDetail(order.id)
            },
            onCancelClick = { order ->
                showCancelOrderDialog(order)
            },
            onReorderClick = { order ->
                showReorderDialog(order)
            },
            onTrackClick = { order ->
                navigateToOrderTracking(order.id)
            }
        )
        
        binding.rvOrders.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = ordersAdapter
            setHasFixedSize(true)
        }
    }
    
    private fun setupFilterChips() {
        val chipGroup = binding.chipGroupStatus
        
        // Add "All Orders" chip
        val allChip = createFilterChip("All Orders", null)
        chipGroup.addView(allChip)
        allChip.isChecked = true
        
        // Add chips for each order status
        OrderStatus.values().forEach { status ->
            val chip = createFilterChip(getStatusDisplayName(status), status)
            chipGroup.addView(chip)
        }
        
        chipGroup.setOnCheckedChangeListener { group, checkedId ->
            val chip = group.findViewById<Chip>(checkedId)
            val status = chip?.tag as? OrderStatus
            viewModel.setFilterStatus(status)
        }
    }
    
    private fun createFilterChip(text: String, status: OrderStatus?): Chip {
        return Chip(requireContext()).apply {
            this.text = text
            tag = status
            isCheckable = true
            setChipBackgroundColorResource(R.color.chip_background_selector)
        }
    }
    
    private fun getStatusDisplayName(status: OrderStatus): String {
        return when (status) {
            OrderStatus.PENDING -> "Pending"
            OrderStatus.CONFIRMED -> "Confirmed"
            OrderStatus.PREPARING -> "Preparing"
            OrderStatus.READY -> "Ready"
            OrderStatus.DELIVERING -> "Delivering"
            OrderStatus.COMPLETED -> "Completed"
            OrderStatus.CANCELLED -> "Cancelled"
        }
    }
    
    private fun setupSearchView() {
        binding.searchView.setOnQueryTextListener(object : androidx.appcompat.widget.SearchView.OnQueryTextListener {
            override fun onQueryTextSubmit(query: String?): Boolean {
                return false
            }
            
            override fun onQueryTextChange(newText: String?): Boolean {
                viewModel.setSearchQuery(newText ?: "")
                return true
            }
        })
    }
    
    private fun setupSwipeRefresh() {
        binding.swipeRefreshLayout.setOnRefreshListener {
            viewModel.refreshOrders()
        }
    }
    
    private fun observeViewModel() {
        lifecycleScope.launch {
            viewModel.filteredOrders.collectLatest { orders ->
                ordersAdapter.submitList(orders)
                updateEmptyState(orders.isEmpty())
            }
        }
        
        lifecycleScope.launch {
            viewModel.isLoading.collectLatest { isLoading ->
                binding.swipeRefreshLayout.isRefreshing = isLoading
                binding.progressBar.visibility = if (isLoading && ordersAdapter.itemCount == 0) {
                    View.VISIBLE
                } else {
                    View.GONE
                }
            }
        }
        
        lifecycleScope.launch {
            viewModel.errorMessage.collectLatest { errorMessage ->
                errorMessage?.let {
                    showErrorSnackbar(it)
                    viewModel.clearError()
                }
            }
        }
    }
    
    private fun setupAnimations() {
        // Animate title
        binding.tvOrdersTitle.apply {
            alpha = 0f
            translationY = -50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(500)
                .start()
        }
        
        // Animate filter chips
        binding.chipGroupStatus.apply {
            alpha = 0f
            translationX = -100f
            animate()
                .alpha(1f)
                .translationX(0f)
                .setDuration(700)
                .setStartDelay(200)
                .start()
        }
        
        // Animate search view
        binding.searchView.apply {
            alpha = 0f
            scaleX = 0.8f
            scaleY = 0.8f
            animate()
                .alpha(1f)
                .scaleX(1f)
                .scaleY(1f)
                .setDuration(600)
                .setStartDelay(400)
                .start()
        }
    }
    
    private fun updateEmptyState(isEmpty: Boolean) {
        binding.emptyStateContainer.visibility = if (isEmpty) View.VISIBLE else View.GONE
        binding.rvOrders.visibility = if (isEmpty) View.GONE else View.VISIBLE
    }
    
    private fun navigateToOrderDetail(orderId: String) {
        val bundle = Bundle().apply {
            putString("orderId", orderId)
        }
        findNavController().navigate(R.id.action_ordersFragment_to_orderDetailFragment, bundle)
    }
    
    private fun navigateToOrderTracking(orderId: String) {
        val bundle = Bundle().apply {
            putString("orderId", orderId)
        }
        findNavController().navigate(R.id.action_ordersFragment_to_orderTrackingFragment, bundle)
    }
    
    private fun showCancelOrderDialog(order: Order) {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Cancel Order")
            .setMessage("Are you sure you want to cancel order ${order.id}?")
            .setPositiveButton("Cancel Order") { _, _ ->
                viewModel.cancelOrder(order.id, "Cancelled by user")
            }
            .setNegativeButton("Keep Order", null)
            .show()
    }
      private fun showReorderDialog(order: Order) {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Reorder")
            .setMessage("Add these items to your cart?")
            .setPositiveButton("Add to Cart") { _, _ ->
                viewModel.reorderItems(order)
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    private fun showErrorSnackbar(message: String) {
        com.google.android.material.snackbar.Snackbar.make(
            binding.root,
            message,
            com.google.android.material.snackbar.Snackbar.LENGTH_LONG
        ).show()
    }
    
    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
