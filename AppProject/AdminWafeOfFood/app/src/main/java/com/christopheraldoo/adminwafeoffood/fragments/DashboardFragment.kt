package com.christopheraldoo.adminwafeoffood.fragments

import android.content.Intent
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
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.databinding.FragmentDashboardBinding
import com.christopheraldoo.adminwafeoffood.dashboard.viewmodel.DashboardViewModel
import com.christopheraldoo.adminwafeoffood.menu.activities.AddEditMenuActivity

import kotlinx.coroutines.launch

class DashboardFragment : Fragment() {
    
    private var _binding: FragmentDashboardBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: DashboardViewModel by viewModels()
    
    companion object {
        private const val TAG = "DashboardFragment"
    }
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentDashboardBinding.inflate(inflater, container, false)
        return binding.root
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        Log.d(TAG, "Dashboard Fragment created")
        
        setupClickListeners()
        observeViewModel()
        
        // Load data
        viewModel.loadDashboardData()
    }
      private fun setupClickListeners() {
        // Quick Actions
        binding.btnAddMenu.setOnClickListener {
            try {
                startActivity(Intent(requireContext(), AddEditMenuActivity::class.java))
            } catch (e: Exception) {
                Log.e(TAG, "Error starting AddEditMenuActivity", e)
                Toast.makeText(requireContext(), "Error opening Add Menu", Toast.LENGTH_SHORT).show()
            }
        }
          binding.btnViewOrders.setOnClickListener {
            try {
                // Navigate to orders fragment via MainActivity
                (activity as? com.christopheraldoo.adminwafeoffood.MainActivity)?.navigateToOrders()
            } catch (e: Exception) {
                Log.e(TAG, "Error navigating to orders", e)
                Toast.makeText(requireContext(), "Error opening Orders", Toast.LENGTH_SHORT).show()
            }
        }
        
        // View All Recent Orders
        binding.tvViewAllOrders.setOnClickListener {
            try {
                // Navigate to orders fragment via MainActivity
                (activity as? com.christopheraldoo.adminwafeoffood.MainActivity)?.navigateToOrders()
            } catch (e: Exception) {
                Log.e(TAG, "Error opening all orders", e)
            }
        }
    }
    
    private fun observeViewModel() {
        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.dashboardState.collect { state ->
                when {
                    state.isLoading -> {
                        showLoading(true)
                    }
                    state.error != null -> {
                        showLoading(false)
                        showError(state.error)
                    }
                    state.statistics != null -> {
                        showLoading(false)
                        updateDashboard(state.statistics)
                    }
                }
            }
        }
    }
    
    private fun showLoading(isLoading: Boolean) {
        binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        binding.contentLayout.visibility = if (isLoading) View.GONE else View.VISIBLE
    }
    
    private fun showError(error: String) {
        Toast.makeText(requireContext(), error, Toast.LENGTH_LONG).show()
        Log.e(TAG, "Dashboard error: $error")
    }    private fun updateDashboard(statistics: com.christopheraldoo.adminwafeoffood.dashboard.model.DashboardStatistics) {
        try {
            Log.d(TAG, "Updating dashboard with statistics - Revenue: ${statistics.totalRevenueToday}")
            
            // Update Revenue Today
            binding.tvRevenueToday.text = formatCurrency(statistics.totalRevenueToday)
            Log.d(TAG, "Revenue updated: ${formatCurrency(statistics.totalRevenueToday)}")
            
            // Update Total Orders
            binding.tvTotalOrders.text = statistics.totalOrders.toString()
            Log.d(TAG, "Total orders updated: ${statistics.totalOrders}")
            
            // Update Recent Orders
            updateRecentOrders(statistics.recentOrders)
            
            Log.d(TAG, "Dashboard updated successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating dashboard", e)
        }
    }
    
    private fun updateRecentOrders(recentOrders: List<com.christopheraldoo.adminwafeoffood.order.model.Order>) {
        try {
            // Clear previous data
            binding.recentOrdersContainer.removeAllViews()
            
            if (recentOrders.isEmpty()) {
                // Show no orders message
                val noOrdersView = layoutInflater.inflate(R.layout.item_no_orders, binding.recentOrdersContainer, false)
                binding.recentOrdersContainer.addView(noOrdersView)
                return
            }
            
            // Add recent orders (max 5)
            recentOrders.take(5).forEach { order ->
                val orderView = layoutInflater.inflate(R.layout.item_recent_order, binding.recentOrdersContainer, false)
                
                // Bind order data
                orderView.findViewById<android.widget.TextView>(R.id.tvOrderId).text = "#${order.id.take(8)}"
                orderView.findViewById<android.widget.TextView>(R.id.tvCustomerName).text = order.customerName
                orderView.findViewById<android.widget.TextView>(R.id.tvOrderAmount).text = order.getFormattedTotal()
                orderView.findViewById<android.widget.TextView>(R.id.tvOrderStatus).text = order.getStatusDisplayName()
                
                // Set status color
                val statusView = orderView.findViewById<android.widget.TextView>(R.id.tvOrderStatus)
                try {
                    statusView.setTextColor(android.graphics.Color.parseColor(order.getStatusColor()))
                } catch (e: Exception) {
                    // Use default color if parsing fails
                }                // Add click listener
                orderView.setOnClickListener {
                    try {
                        // Navigate to orders fragment
                        (activity as? com.christopheraldoo.adminwafeoffood.MainActivity)?.navigateToOrders()
                    } catch (e: Exception) {
                        Log.e(TAG, "Error opening order details", e)
                    }
                }
                
                binding.recentOrdersContainer.addView(orderView)
            }
              } catch (e: Exception) {
            Log.e(TAG, "Error updating recent orders", e)
        }
    }
    
    private fun formatCurrency(amount: Double): String {
        return "Rp ${String.format("%,.0f", amount)}"
    }
    
    override fun onResume() {
        super.onResume()
        // Refresh data when returning to dashboard
        viewModel.loadDashboardData()
    }
    
    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
