package com.christopheraldoo.adminwafeoffood.fragments

import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.DividerItemDecoration
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.adminwafeoffood.databinding.FragmentOrdersBinding
import com.christopheraldoo.adminwafeoffood.order.adapter.OrderAdapter
import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderStatus
import com.christopheraldoo.adminwafeoffood.order.viewmodel.OrderViewModel
import kotlinx.coroutines.launch

class OrdersFragment : Fragment() {
    
    private var _binding: FragmentOrdersBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: OrderViewModel by viewModels()
    private lateinit var orderAdapter: OrderAdapter
    
    companion object {
        private const val TAG = "OrdersFragment"
        
        fun newInstance() = OrdersFragment()
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return try {
            Log.d(TAG, "Creating OrdersFragment view...")
            _binding = FragmentOrdersBinding.inflate(inflater, container, false)
            binding.root
        } catch (e: Exception) {
            Log.e(TAG, "Error creating OrdersFragment view: ${e.message}", e)
            // Fallback
            inflater.inflate(android.R.layout.simple_list_item_1, container, false)
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            Log.d(TAG, "OrdersFragment onViewCreated")
            
            setupRecyclerView()
            setupChips()
            setupTestButton()
            
            // Delay observe ViewModel sedikit
            view.post {
                if (isAdded && !isDetached) {
                    observeViewModel()
                }
            }
            
            Log.d(TAG, "OrdersFragment setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated: ${e.message}", e)
        }
    }

    private fun setupRecyclerView() {
        try {            orderAdapter = OrderAdapter(
                onItemClick = { order ->
                    try {
                        showOrderDetails(order)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error opening order details: ${e.message}", e)
                    }
                },
                onStatusUpdate = { order, newStatus ->
                    try {
                        viewModel.updateOrderStatus(order.id, newStatus)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error updating status: ${e.message}", e)
                    }
                },
                onDeleteOrder = { order ->
                    try {
                        showDeleteConfirmation(order)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error deleting order: ${e.message}", e)
                    }
                }
            )

            binding.recyclerOrders.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = orderAdapter
                addItemDecoration(DividerItemDecoration(context, DividerItemDecoration.VERTICAL))
            }
            
            Log.d(TAG, "RecyclerView setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView: ${e.message}", e)
        }
    }

    private fun setupChips() {
        try {
            // Set default selection
            binding.chipAll.isChecked = true
            
            binding.chipAll.setOnClickListener { 
                clearChipSelection()
                binding.chipAll.isChecked = true
                viewModel.clearFilter()
            }
            
            binding.chipIncoming.setOnClickListener { 
                clearChipSelection()
                binding.chipIncoming.isChecked = true
                viewModel.filterByStatus(OrderStatus.INCOMING)
            }
            
            binding.chipConfirmed.setOnClickListener { 
                clearChipSelection()
                binding.chipConfirmed.isChecked = true
                viewModel.filterByStatus(OrderStatus.CONFIRMED)
            }
            
            binding.chipInProgress.setOnClickListener { 
                clearChipSelection()
                binding.chipInProgress.isChecked = true
                viewModel.filterByStatus(OrderStatus.IN_PROGRESS)
            }
            
            binding.chipReady.setOnClickListener { 
                clearChipSelection()
                binding.chipReady.isChecked = true
                viewModel.filterByStatus(OrderStatus.READY)
            }
              binding.chipCompleted.setOnClickListener { 
                clearChipSelection()
                binding.chipCompleted.isChecked = true
                viewModel.filterByStatus(OrderStatus.COMPLETED)
            }
            
            binding.chipCancelled.setOnClickListener { 
                clearChipSelection()
                binding.chipCancelled.isChecked = true
                viewModel.filterByStatus(OrderStatus.CANCELLED)
            }
            
            Log.d(TAG, "Chips setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up chips: ${e.message}", e)
        }
    }
      private fun clearChipSelection() {
        binding.chipAll.isChecked = false
        binding.chipIncoming.isChecked = false
        binding.chipConfirmed.isChecked = false
        binding.chipInProgress.isChecked = false
        binding.chipReady.isChecked = false
        binding.chipCompleted.isChecked = false
        binding.chipCancelled.isChecked = false
    }

    private fun observeViewModel() {
        try {
            // Check if fragment is still attached
            if (!isAdded || isDetached) {
                Log.w(TAG, "Fragment not attached, skipping observers")
                return
            }
            
            // Observe filtered order list
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.filteredOrderList.collect { orderList ->
                        // Check if fragment is still attached before updating UI
                        if (isAdded && !isDetached && _binding != null) {
                            Log.d(TAG, "Order list updated with ${orderList.size} items")
                            if (orderList.isEmpty()) {
                                showEmptyState()
                            } else {
                                hideEmptyState()
                                orderAdapter.submitList(orderList)
                            }
                        }
                    }
                } catch (e: Exception) {
                    if (isAdded && !isDetached) {
                        Log.e(TAG, "Error in order list observer: ${e.message}", e)
                    }
                }
            }

            // Observe loading state
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.isLoading.collect { isLoading ->
                        if (isAdded && !isDetached && _binding != null) {
                            Log.d(TAG, "Loading state: $isLoading")
                            // You can add loading indicator here if needed
                        }
                    }
                } catch (e: Exception) {
                    if (isAdded && !isDetached) {
                        Log.e(TAG, "Error in loading observer: ${e.message}", e)
                    }
                }
            }

            // Observe errors
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.error.collect { error ->
                        if (isAdded && !isDetached && error != null) {
                            Log.d(TAG, "Error/Message: $error")
                            // Filter out "job was cancelled" messages
                            if (!error.contains("job was cancelled", ignoreCase = true)) {
                                showMessage(error)
                            }
                            viewModel.clearError()
                        }
                    }
                } catch (e: Exception) {
                    if (isAdded && !isDetached) {
                        Log.e(TAG, "Error in error observer: ${e.message}", e)
                    }
                }
            }
            
            Log.d(TAG, "ViewModel observers setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up observers: ${e.message}", e)
        }
    }
    
    private fun setupTestButton() {
        try {
            // Add test button untuk testing (bisa dihapus nanti)
            binding.layoutEmptyState.setOnLongClickListener {
                if (isAdded && !isDetached) {
                    viewModel.addTestOrder()
                }
                true
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up test button: ${e.message}", e)
        }
    }

    private fun showEmptyState() {
        try {
            if (_binding != null) {
                binding.recyclerOrders.visibility = View.GONE
                binding.layoutEmptyState.visibility = View.VISIBLE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing empty state: ${e.message}", e)
        }
    }

    private fun hideEmptyState() {
        try {
            if (_binding != null) {
                binding.recyclerOrders.visibility = View.VISIBLE
                binding.layoutEmptyState.visibility = View.GONE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error hiding empty state: ${e.message}", e)
        }
    }    private fun showOrderDetails(order: Order) {
        try {
            val intent = Intent(requireContext(), com.christopheraldoo.adminwafeoffood.order.activities.OrderDetailActivity::class.java)
            intent.putExtra(com.christopheraldoo.adminwafeoffood.order.activities.OrderDetailActivity.EXTRA_ORDER_ID, order.id)
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening order detail: ${e.message}", e)
            
            // Fallback: show basic info in toast
            val message = """
                Order #${order.id.take(8)}
                Customer: ${order.customerName}
                Total: ${order.getFormattedTotal()}
                Status: ${order.getStatusDisplayName()}
                Items: ${order.items.size}
            """.trimIndent()
            
            showMessage(message)
        }
    }

    private fun showMessage(message: String) {
        try {
            if (isAdded && !isDetached) {
                context?.let {
                    Toast.makeText(it, message, Toast.LENGTH_SHORT).show()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing message: ${e.message}", e)
        }
    }

    private fun showDeleteConfirmation(order: Order) {
        try {
            AlertDialog.Builder(requireContext())
                .setTitle("Hapus Pesanan")
                .setMessage("Apakah Anda yakin ingin menghapus pesanan #${order.id.take(8)} dari ${order.customerName}?\n\nPesanan yang dihapus tidak dapat dikembalikan.")
                .setPositiveButton("Hapus") { _, _ ->
                    viewModel.deleteOrder(order.id)
                }
                .setNegativeButton("Batal", null)
                .show()
        } catch (e: Exception) {
            Log.e(TAG, "Error showing delete confirmation: ${e.message}", e)
        }
    }

    override fun onDestroyView() {
        try {
            super.onDestroyView()
            _binding = null
        } catch (e: Exception) {
            Log.e(TAG, "Error in onDestroyView: ${e.message}", e)
            _binding = null
        }
    }
}
