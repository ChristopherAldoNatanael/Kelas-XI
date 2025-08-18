package com.christopheraldoo.adminwafeoffood.fragments

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
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
import com.christopheraldoo.adminwafeoffood.menu.model.MenuStatistics
import com.christopheraldoo.adminwafeoffood.menu.viewmodel.MenuViewModel
import kotlinx.coroutines.launch

class MenuFragment : Fragment() {

    private var _binding: FragmentMenuBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: MenuViewModel by viewModels()
    private lateinit var menuAdapter: MenuAdapter
    
    companion object {
        private const val TAG = "MenuFragment"
    }
    
    private val addMenuLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            Log.d(TAG, "Menu added successfully, Firebase will auto-update")
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return try {
            _binding = FragmentMenuBinding.inflate(inflater, container, false)
            binding.root
        } catch (e: Exception) {
            Log.e(TAG, "Error creating view: ${e.message}", e)
            null
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            Log.d(TAG, "MenuFragment onViewCreated")
            
            setupRecyclerView()
            setupClickListeners()
            observeViewModel()
            
            Log.d(TAG, "MenuFragment setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated: ${e.message}", e)
        }
    }

    private fun setupRecyclerView() {
        try {
            menuAdapter = MenuAdapter(
                onItemClick = { menuItem ->
                    try {
                        val intent = AddEditMenuActivity.createViewIntent(requireContext(), menuItem.id)
                        startActivity(intent)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error opening view menu: ${e.message}", e)
                    }
                },
                onEditClick = { menuItem ->
                    try {
                        val intent = AddEditMenuActivity.createEditIntent(requireContext(), menuItem.id)
                        addMenuLauncher.launch(intent)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error opening edit menu: ${e.message}", e)
                    }
                },
                onDeleteClick = { menuItem ->
                    try {
                        viewModel.deleteMenu(menuItem.id)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error deleting menu: ${e.message}", e)
                    }
                },
                onAvailabilityToggle = { menuItem, isAvailable ->
                    try {
                        viewModel.updateMenuAvailability(menuItem.id, isAvailable)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error updating availability: ${e.message}", e)
                    }
                }
            )

            binding.recyclerViewMenu.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = menuAdapter
                addItemDecoration(DividerItemDecoration(context, DividerItemDecoration.VERTICAL))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView: ${e.message}", e)
        }
    }

    private fun setupClickListeners() {
        try {
            binding.fabAddMenu.setOnClickListener {
                try {
                    val intent = AddEditMenuActivity.createAddIntent(requireContext())
                    addMenuLauncher.launch(intent)
                } catch (e: Exception) {
                    Log.e(TAG, "Error launching add menu: ${e.message}", e)
                }
            }
            
            binding.btnAddFirstMenu.setOnClickListener {
                try {
                    val intent = AddEditMenuActivity.createAddIntent(requireContext())
                    addMenuLauncher.launch(intent)
                } catch (e: Exception) {
                    Log.e(TAG, "Error launching add first menu: ${e.message}", e)
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up click listeners: ${e.message}", e)
        }
    }

    private fun observeViewModel() {
        try {
            lifecycleScope.launch {
                viewModel.menuList.collect { menuList ->
                    try {
                        Log.d(TAG, "Menu list updated with ${menuList.size} items")
                        if (menuList.isEmpty()) {
                            showEmptyState()
                        } else {
                            hideEmptyState()
                            menuAdapter.submitList(menuList)
                        }
                        updateStatistics(menuList)
                    } catch (e: Exception) {
                        Log.e(TAG, "Error processing menu list: ${e.message}", e)
                    }
                }
            }

            lifecycleScope.launch {
                viewModel.isLoading.collect { isLoading ->
                    try {
                        binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
                    } catch (e: Exception) {
                        Log.e(TAG, "Error updating loading state: ${e.message}", e)
                    }
                }
            }

            lifecycleScope.launch {
                viewModel.error.collect { error ->
                    error?.let {
                        Log.e(TAG, "Error: $it")
                        viewModel.clearError()
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up observers: ${e.message}", e)
        }
    }

    private fun updateStatistics(menuList: List<MenuItem>) {
        try {
            val stats = generateStatistics(menuList)
            
            binding.apply {
                tvTotalMenus.text = stats.totalMenus.toString()
                tvAvailableMenus.text = stats.availableMenus.toString()
                tvAveragePrice.text = stats.getFormattedAveragePrice()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error updating statistics: ${e.message}", e)
        }
    }

    private fun generateStatistics(menuList: List<MenuItem>): MenuStatistics {
        val total = menuList.size
        val available = menuList.count { it.isAvailable }
        val avgPrice = if (total > 0) menuList.map { it.price }.average() else 0.0
        
        return MenuStatistics(
            totalMenus = total,
            availableMenus = available,
            unavailableMenus = total - available,
            totalCategories = menuList.map { it.category }.distinct().size,
            averagePrice = avgPrice,
            mostExpensiveMenu = menuList.maxByOrNull { it.price }?.name ?: "",
            cheapestMenu = menuList.minByOrNull { it.price }?.name ?: ""
        )
    }

    private fun showEmptyState() {
        try {
            binding.recyclerViewMenu.visibility = View.GONE
            binding.layoutEmpty.visibility = View.VISIBLE
        } catch (e: Exception) {
            Log.e(TAG, "Error showing empty state: ${e.message}", e)
        }
    }

    private fun hideEmptyState() {
        try {
            binding.recyclerViewMenu.visibility = View.VISIBLE
            binding.layoutEmpty.visibility = View.GONE
        } catch (e: Exception) {
            Log.e(TAG, "Error hiding empty state: ${e.message}", e)
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