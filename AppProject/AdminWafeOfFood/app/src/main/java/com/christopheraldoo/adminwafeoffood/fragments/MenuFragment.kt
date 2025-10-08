package com.christopheraldoo.adminwafeoffood.fragments

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.DividerItemDecoration
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.adminwafeoffood.databinding.FragmentMenuBinding
import com.christopheraldoo.adminwafeoffood.menu.activities.AddEditMenuActivity
import com.christopheraldoo.adminwafeoffood.menu.adapter.MenuAdapter
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.viewmodel.MenuViewModel
import kotlinx.coroutines.launch

class MenuFragment : Fragment() {

    private var _binding: FragmentMenuBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: MenuViewModel by viewModels()
    private lateinit var menuAdapter: MenuAdapter
    
    companion object {
        private const val TAG = "MenuFragment"
        
        fun newInstance(): MenuFragment {
            return MenuFragment()
        }
    }
    
    private val addMenuLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        try {
            if (result.resultCode == Activity.RESULT_OK) {
                Log.d(TAG, "Menu operation completed successfully")
                showSuccessToast("Menu operation completed successfully")
                // Refresh data setelah operasi berhasil
                viewModel.refreshMenus()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling activity result", e)
            showErrorToast("Error handling result: ${e.message}")
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        return try {
            Log.d(TAG, "Creating MenuFragment view...")
            _binding = FragmentMenuBinding.inflate(inflater, container, false)
            Log.d(TAG, "Fragment view created successfully")
            binding.root
        } catch (e: Exception) {
            Log.e(TAG, "CRITICAL ERROR creating fragment view: ${e.message}", e)
            // Return a simple fallback view instead of crashing
            View(requireContext())
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            Log.d(TAG, "MenuFragment onViewCreated started")
            
            // Setup step by step with defensive programming
            if (_binding != null) {
                setupRecyclerViewSafely()
                setupClickListenersSafely() 
                observeViewModelSafely()
                
                // Load initial data
                viewModel.loadMenus()
                
                Log.d(TAG, "MenuFragment setup completed successfully")
            } else {
                Log.e(TAG, "Binding is null after creation, cannot setup fragment")
                showErrorToast("Failed to initialize menu interface")
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated: ${e.message}", e)
            showErrorToast("Error initializing menu: ${e.message}")
        }
    }

    private fun setupRecyclerViewSafely() {
        try {
            Log.d(TAG, "Setting up RecyclerView...")
            
            menuAdapter = MenuAdapter(
                onItemClick = { menuItem ->
                    safeFunctionCall("View Menu") { openViewMenu(menuItem) }
                },
                onEditClick = { menuItem ->
                    safeFunctionCall("Edit Menu") { openEditMenu(menuItem) }
                },
                onDeleteClick = { menuItem ->
                    safeFunctionCall("Delete Menu") { deleteMenu(menuItem) }
                },
                onAvailabilityToggle = { menuItem, isAvailable ->
                    safeFunctionCall("Toggle Availability") { 
                        toggleAvailability(menuItem, isAvailable) 
                    }
                }
            )

            binding.recyclerViewMenu.apply {
                layoutManager = LinearLayoutManager(requireContext())
                adapter = menuAdapter
                
                // Add divider only if context is available
                try {
                    addItemDecoration(
                        DividerItemDecoration(requireContext(), DividerItemDecoration.VERTICAL)
                    )
                } catch (e: Exception) {
                    Log.w(TAG, "Could not add divider decoration: ${e.message}")
                }
            }
            
            Log.d(TAG, "RecyclerView setup completed successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView: ${e.message}", e)
            showErrorToast("Error setting up menu list")
        }
    }

    private fun setupClickListenersSafely() {
        try {
            Log.d(TAG, "Setting up click listeners...")
            
            // FAB untuk add menu
            binding.fabAddMenu.setOnClickListener {
                safeFunctionCall("Open Add Menu") { openAddMenu() }
            }
            
            // Button untuk add first menu (jika ada)
            try {
                binding.btnAddFirstMenu.setOnClickListener {
                    safeFunctionCall("Open Add Menu from Empty State") { openAddMenu() }
                }
            } catch (e: Exception) {
                Log.d(TAG, "btnAddFirstMenu not available in layout, skipping")
            }
            
            Log.d(TAG, "Click listeners setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up click listeners: ${e.message}", e)
        }
    }

    private fun observeViewModelSafely() {
        try {
            Log.d(TAG, "Setting up ViewModel observers...")
            
            // Observe menu list
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.menuList.collect { menuList ->
                        safeFunctionCall("Handle Menu List Update") {
                            handleMenuListUpdate(menuList)
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error collecting menu list: ${e.message}", e)
                    showErrorToast("Error loading menu list")
                }
            }

            // Observe loading state
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.isLoading.collect { isLoading ->
                        safeFunctionCall("Handle Loading State") {
                            handleLoadingState(isLoading)
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error collecting loading state: ${e.message}", e)
                }
            }

            // Observe error state
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.error.collect { error ->
                        error?.let {
                            Log.e(TAG, "ViewModel error: $it")
                            showErrorToast("Error: $it")
                            viewModel.clearError()
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error collecting error state: ${e.message}", e)
                }
            }
            
            Log.d(TAG, "ViewModel observers setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up observers: ${e.message}", e)
            showErrorToast("Error connecting to data source")
        }
    }

    private fun handleMenuListUpdate(menuList: List<MenuItem>) {
        try {
            Log.d(TAG, "Updating menu list with ${menuList.size} items")
            
            // Check binding validity
            if (_binding == null) {
                Log.w(TAG, "Binding is null, cannot update UI")
                return
            }
            
            if (menuList.isEmpty()) {
                showEmptyState()
            } else {
                hideEmptyState()
                menuAdapter.submitList(menuList.toList()) // Defensive copy
            }
            
            updateStatisticsSafely(menuList)
        } catch (e: Exception) {
            Log.e(TAG, "Error handling menu list update: ${e.message}", e)
            showErrorToast("Error updating menu list")
        }
    }

    private fun handleLoadingState(isLoading: Boolean) {
        try {
            if (_binding != null) {
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
                
                // Show/hide other UI elements appropriately
                binding.recyclerViewMenu.visibility = if (isLoading) View.GONE else View.VISIBLE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling loading state: ${e.message}", e)
        }
    }

    private fun showEmptyState() {
        try {
            if (_binding != null) {
                binding.recyclerViewMenu.visibility = View.GONE
                binding.layoutEmpty.visibility = View.VISIBLE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing empty state: ${e.message}", e)
        }
    }

    private fun hideEmptyState() {
        try {
            if (_binding != null) {
                binding.recyclerViewMenu.visibility = View.VISIBLE  
                binding.layoutEmpty.visibility = View.GONE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error hiding empty state: ${e.message}", e)
        }
    }

    private fun updateStatisticsSafely(menuList: List<MenuItem>) {
        try {
            if (_binding == null || menuList.isEmpty()) return
            
            val totalMenus = menuList.size
            val availableMenus = menuList.count { it.isAvailable }
            val averagePrice = menuList.map { it.price }.average().toInt()

            // Update statistics TextViews if they exist
            try {
                binding.tvTotalMenus.text = totalMenus.toString()
                binding.tvAvailableMenus.text = availableMenus.toString()
                binding.tvAveragePrice.text = "Rp ${String.format("%,d", averagePrice)}"
            } catch (e: Exception) {
                Log.d(TAG, "Statistics TextViews not available in layout, skipping update")
            }
            
            Log.d(TAG, "Statistics updated - Total: $totalMenus, Available: $availableMenus")
        } catch (e: Exception) {
            Log.e(TAG, "Error updating statistics: ${e.message}", e)
        }
    }

    private fun openViewMenu(menuItem: MenuItem) {
        val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
            putExtra(AddEditMenuActivity.EXTRA_MENU_ITEM, menuItem)
            putExtra(AddEditMenuActivity.EXTRA_IS_EDIT_MODE, false) // View only mode
        }
        startActivity(intent)
    }

    private fun openEditMenu(menuItem: MenuItem) {
        val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
            putExtra(AddEditMenuActivity.EXTRA_MENU_ITEM, menuItem)
            putExtra(AddEditMenuActivity.EXTRA_IS_EDIT_MODE, true)
        }
        addMenuLauncher.launch(intent)
    }

    private fun openAddMenu() {
        val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
            putExtra("IS_EDIT_MODE", false)
            // Tidak perlu MENU_ITEM_ID untuk add mode
        }
        addMenuLauncher.launch(intent)
    }

    private fun deleteMenu(menuItem: MenuItem) {
        try {
            Log.d(TAG, "Deleting menu: ${menuItem.name}")
            viewModel.deleteMenu(menuItem.id)
            showSuccessToast("Menu '${menuItem.name}' deleted successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error deleting menu: ${e.message}", e)
            showErrorToast("Failed to delete menu: ${e.message}")
        }
    }

    private fun toggleAvailability(menuItem: MenuItem, isAvailable: Boolean) {
        try {
            Log.d(TAG, "Updating availability for ${menuItem.name}: $isAvailable")
            viewModel.updateMenuAvailability(menuItem.id, isAvailable)
            val status = if (isAvailable) "available" else "unavailable"
            showSuccessToast("Menu '${menuItem.name}' marked as $status")
        } catch (e: Exception) {
            Log.e(TAG, "Error updating availability: ${e.message}", e)
            showErrorToast("Failed to update menu status: ${e.message}")
        }
    }

    // Safe function call wrapper untuk mencegah crash
    private inline fun safeFunctionCall(actionName: String, action: () -> Unit) {
        try {
            action()
        } catch (e: Exception) {
            Log.e(TAG, "Error in $actionName: ${e.message}", e)
            showErrorToast("Error in $actionName")
        }
    }

    private fun showSuccessToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(context, "✅ $message", Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing success toast: ${e.message}", e)
        }
    }

    private fun showErrorToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(context, "❌ $message", Toast.LENGTH_LONG).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing error toast: ${e.message}", e)
        }
    }    override fun onResume() {
        super.onResume()
        Log.d(TAG, "MenuFragment onResume - refreshing data")
        try {
            // Refresh data ketika fragment kembali aktif
            viewModel.refreshMenus()
        } catch (e: Exception) {
            Log.e(TAG, "Error in onResume: ${e.message}", e)
        }
    }

    override fun onPause() {
        super.onPause()
        Log.d(TAG, "MenuFragment onPause")
    }    override fun onDestroyView() {
        super.onDestroyView()
        try {
            Log.d(TAG, "MenuFragment onDestroyView - cleaning up")
            _binding = null
        } catch (e: Exception) {
            Log.e(TAG, "Error in onDestroyView: ${e.message}", e)
            _binding = null // Force cleanup anyway
        }
    }
}
