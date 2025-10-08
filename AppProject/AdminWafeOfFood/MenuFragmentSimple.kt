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
    private var menuAdapter: MenuAdapter? = null
    
    companion object {
        private const val TAG = "MenuFragment"
        
        fun newInstance(): MenuFragment {
            return MenuFragment()
        }
    }
    
    private val addMenuLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            Log.d(TAG, "Menu operation completed")
            Toast.makeText(context, "✅ Menu operation completed", Toast.LENGTH_SHORT).show()
            viewModel.refreshMenus()
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return try {
            Log.d(TAG, "Creating MenuFragment view")
            _binding = FragmentMenuBinding.inflate(inflater, container, false)
            binding.root
        } catch (e: Exception) {
            Log.e(TAG, "Error creating view", e)
            null
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            Log.d(TAG, "Setting up MenuFragment")
            
            setupRecyclerView()
            setupClickListeners()
            observeViewModel()
            viewModel.loadMenus()
            
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated", e)
            Toast.makeText(context, "Error initializing menu", Toast.LENGTH_SHORT).show()
        }
    }

    private fun setupRecyclerView() {
        try {
            menuAdapter = MenuAdapter(
                onItemClick = { menuItem -> openViewMenu(menuItem) },
                onEditClick = { menuItem -> openEditMenu(menuItem) },
                onDeleteClick = { menuItem -> deleteMenu(menuItem) },
                onAvailabilityToggle = { menuItem, isAvailable ->
                    toggleAvailability(menuItem, isAvailable)
                }
            )

            binding.recyclerViewMenu.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = menuAdapter
            }
            
            Log.d(TAG, "RecyclerView setup complete")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up RecyclerView", e)
        }
    }

    private fun setupClickListeners() {
        try {
            binding.fabAddMenu.setOnClickListener { openAddMenu() }
            
            // Check if btnAddFirstMenu exists
            try {
                binding.btnAddFirstMenu.setOnClickListener { openAddMenu() }
            } catch (e: Exception) {
                Log.d(TAG, "btnAddFirstMenu not found")
            }
            
            Log.d(TAG, "Click listeners setup")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up clicks", e)
        }
    }

    private fun observeViewModel() {
        try {
            // Observe menu list
            viewLifecycleOwner.lifecycleScope.launch {
                viewModel.menuList.collect { menuList ->
                    updateMenuList(menuList)
                }
            }

            // Observe loading
            viewLifecycleOwner.lifecycleScope.launch {
                viewModel.isLoading.collect { isLoading ->
                    updateLoadingState(isLoading)
                }
            }

            // Observe errors
            viewLifecycleOwner.lifecycleScope.launch {
                viewModel.error.collect { error ->
                    error?.let {
                        showError(it)
                        viewModel.clearError()
                    }
                }
            }
            
            Log.d(TAG, "Observers setup complete")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up observers", e)
        }
    }

    private fun updateMenuList(menuList: List<MenuItem>) {
        try {
            Log.d(TAG, "Updating menu list: ${menuList.size} items")
            
            if (menuList.isEmpty()) {
                binding.recyclerViewMenu.visibility = View.GONE
                binding.layoutEmpty.visibility = View.VISIBLE
            } else {
                binding.recyclerViewMenu.visibility = View.VISIBLE
                binding.layoutEmpty.visibility = View.GONE
                menuAdapter?.submitList(menuList)
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating menu list", e)
        }
    }

    private fun updateLoadingState(isLoading: Boolean) {
        try {
            binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        } catch (e: Exception) {
            Log.e(TAG, "Error updating loading state", e)
        }
    }

    private fun showError(message: String) {
        try {
            if (isAdded) {
                Toast.makeText(context, "❌ $message", Toast.LENGTH_LONG).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing error", e)
        }
    }

    private fun openViewMenu(menuItem: MenuItem) {
        try {
            val intent = Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra("MENU_ITEM_ID", menuItem.id)
                putExtra("IS_EDIT_MODE", false)
            }
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening view menu", e)
        }
    }

    private fun openEditMenu(menuItem: MenuItem) {
        try {
            val intent = Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra("MENU_ITEM_ID", menuItem.id)
                putExtra("IS_EDIT_MODE", true)
            }
            addMenuLauncher.launch(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening edit menu", e)
        }
    }

    private fun openAddMenu() {
        try {
            val intent = Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra("IS_EDIT_MODE", false)
            }
            addMenuLauncher.launch(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Error opening add menu", e)
        }
    }

    private fun deleteMenu(menuItem: MenuItem) {
        try {
            viewModel.deleteMenu(menuItem.id)
            Toast.makeText(context, "✅ Menu deleted: ${menuItem.name}", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e(TAG, "Error deleting menu", e)
            Toast.makeText(context, "❌ Failed to delete menu", Toast.LENGTH_SHORT).show()
        }
    }

    private fun toggleAvailability(menuItem: MenuItem, isAvailable: Boolean) {
        try {
            viewModel.updateMenuAvailability(menuItem.id, isAvailable)
            val status = if (isAvailable) "available" else "unavailable"
            Toast.makeText(context, "✅ Menu marked as $status", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e(TAG, "Error toggling availability", e)
            Toast.makeText(context, "❌ Failed to update menu", Toast.LENGTH_SHORT).show()
        }
    }

    override fun onResume() {
        super.onResume()
        Log.d(TAG, "MenuFragment onResume")
        try {
            viewModel.refreshMenus()
        } catch (e: Exception) {
            Log.e(TAG, "Error in onResume", e)
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        Log.d(TAG, "MenuFragment onDestroyView")
        _binding = null
    }
}
