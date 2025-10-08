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
                Toast.makeText(context, "Menu operation completed", Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling activity result", e)
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        return try {
            _binding = FragmentMenuBinding.inflate(inflater, container, false)
            Log.d(TAG, "Fragment view created successfully")
            binding.root
        } catch (e: Exception) {
            Log.e(TAG, "CRITICAL ERROR creating fragment view: ${e.message}", e)
            // Return a simple view if binding fails - DON'T CRASH!
            View(requireContext())
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            Log.d(TAG, "MenuFragment onViewCreated started")
            
            // Setup components step by step with error handling
            if (_binding != null) {
                setupRecyclerView()
                setupClickListeners()
                observeViewModel()
                Log.d(TAG, "MenuFragment setup completed successfully")
            } else {
                Log.e(TAG, "Binding is null, cannot setup fragment")
                showErrorToast("Failed to initialize menu")
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated: ${e.message}", e)
            // Show error state instead of crashing
            showErrorToast("Error initializing menu: ${e.message}")
        }
    }

    private fun setupRecyclerView() {
        try {
            menuAdapter = MenuAdapter(
                onItemClick = { menuItem ->
                    openViewMenu(menuItem)
                },
                onEditClick = { menuItem ->
                    openEditMenu(menuItem)
                },
                onDeleteClick = { menuItem ->
                    deleteMenu(menuItem)
                },
                onAvailabilityToggle = { menuItem, isAvailable ->
                    toggleAvailability(menuItem, isAvailable)
                }
            )

            binding.recyclerViewMenu.apply {
                layoutManager = LinearLayoutManager(requireContext())
                adapter = menuAdapter
                addItemDecoration(DividerItemDecoration(requireContext(), DividerItemDecoration.VERTICAL))
            }
            
            Log.d(TAG, "RecyclerView setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView: ${e.message}", e)
            showErrorToast("Error setting up menu list")
        }
    }

    private fun setupClickListeners() {
        try {
            binding.fabAddMenu.setOnClickListener {
                openAddMenu()
            }
            
            // Check if btnAddFirstMenu exists (it might not be in all layouts)
            try {
                binding.btnAddFirstMenu.setOnClickListener {
                    openAddMenu()
                }
            } catch (e: Exception) {
                Log.d(TAG, "btnAddFirstMenu not found in layout, skipping")
            }
            
            Log.d(TAG, "Click listeners setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up click listeners: ${e.message}", e)
        }
    }

    private fun openViewMenu(menuItem: MenuItem) {
        try {
            val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
                putExtra("MENU_ITEM_ID", menuItem.id)
                putExtra("IS_EDIT_MODE", false)
            }
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening view menu: ${e.message}", e)
            showErrorToast("Error opening menu details")
        }
    }

    private fun openEditMenu(menuItem: MenuItem) {
        try {
            val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
                putExtra("MENU_ITEM_ID", menuItem.id)
                putExtra("IS_EDIT_MODE", true)
            }
            addMenuLauncher.launch(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening edit menu: ${e.message}", e)
            showErrorToast("Error opening menu editor")
        }
    }

    private fun openAddMenu() {
        try {
            val intent = Intent(requireContext(), AddEditMenuActivity::class.java).apply {
                putExtra("IS_EDIT_MODE", false)
            }
            addMenuLauncher.launch(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening add menu: ${e.message}", e)
            showErrorToast("Error opening add menu")
        }
    }

    private fun deleteMenu(menuItem: MenuItem) {
        try {
            viewModel.deleteMenu(menuItem.id)
            Toast.makeText(context, "Menu deleted", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e(TAG, "Error deleting menu: ${e.message}", e)
            showErrorToast("Error deleting menu")
        }
    }

    private fun toggleAvailability(menuItem: MenuItem, isAvailable: Boolean) {
        try {
            viewModel.updateMenuAvailability(menuItem.id, isAvailable)
            val status = if (isAvailable) "available" else "unavailable" 
            Toast.makeText(context, "Menu marked as $status", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e(TAG, "Error updating availability: ${e.message}", e)
            showErrorToast("Error updating menu status")
        }
    }

    private fun observeViewModel() {
        try {
            // Use viewLifecycleOwner to avoid memory leaks
            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.menuList.collect { menuList ->
                        handleMenuListUpdate(menuList)
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error collecting menu list: ${e.message}", e)
                    showErrorToast("Error loading menu list")
                }
            }

            viewLifecycleOwner.lifecycleScope.launch {
                try {
                    viewModel.isLoading.collect { isLoading ->
                        handleLoadingState(isLoading)
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error collecting loading state: ${e.message}", e)
                }
            }

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
            Log.d(TAG, "Menu list updated with ${menuList.size} items")
            
            if (menuList.isEmpty()) {
                showEmptyState()
            } else {
                hideEmptyState()
                menuAdapter.submitList(menuList.toList()) // Create defensive copy
            }
            
            updateStatistics(menuList)
        } catch (e: Exception) {
            Log.e(TAG, "Error handling menu list update: ${e.message}", e)
            showErrorToast("Error updating menu list")
        }
    }

    private fun handleLoadingState(isLoading: Boolean) {
        try {
            if (_binding != null) {
                binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
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

    private fun updateStatistics(menuList: List<MenuItem>) {
        try {
            val totalMenus = menuList.size
            val availableMenus = menuList.count { it.isAvailable }
            val averagePrice = if (menuList.isNotEmpty()) {
                menuList.map { it.price }.average().toInt()
            } else {
                0
            }

            // Update statistics if TextViews exist (defensive)
            try {
                if (_binding != null) {
                    binding.tvTotalMenus.text = totalMenus.toString()
                    binding.tvAvailableMenus.text = availableMenus.toString()
                    binding.tvAveragePrice.text = "Rp ${String.format("%,d", averagePrice)}"
                }
            } catch (e: Exception) {
                Log.d(TAG, "Statistics TextViews not found, skipping update")
            }
            
            Log.d(TAG, "Statistics updated - Total: $totalMenus, Available: $availableMenus")
        } catch (e: Exception) {
            Log.e(TAG, "Error updating statistics: ${e.message}", e)
        }
    }

    private fun showErrorToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing toast: ${e.message}", e)
        }
    }

    override fun onResume() {
        super.onResume()
        Log.d(TAG, "MenuFragment onResume")
    }

    override fun onPause() {
        super.onPause()
        Log.d(TAG, "MenuFragment onPause")
    }

    override fun onDestroyView() {
        super.onDestroyView()
        try {
            _binding = null
            Log.d(TAG, "Fragment view destroyed")
        } catch (e: Exception) {
            Log.e(TAG, "Error destroying view: ${e.message}", e)
            _binding = null // Force null anyway
        }
    }
}
