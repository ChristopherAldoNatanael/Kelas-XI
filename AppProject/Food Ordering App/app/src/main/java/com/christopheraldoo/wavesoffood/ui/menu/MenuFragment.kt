package com.christopheraldoo.wavesoffood.ui.menu

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.core.view.isVisible
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentMenuBinding

class MenuFragment : Fragment() {

    private var _binding: FragmentMenuBinding? = null
    private val binding get() = _binding!!

    private val viewModel: MenuViewModel by viewModels()
    private lateinit var menuAdapter: MenuAdapter
    
    // TAG untuk logging
    private val TAG = "MenuFragment"

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        Log.d(TAG, "onCreateView called")
        _binding = FragmentMenuBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called")

        setupClickListeners()
        // Search functionality removed - use dedicated Search tab
        setupRecyclerView()
        setupObservers()
        
        // Test Firebase connection for debugging
        testFirebaseConnection()
        
        Log.d(TAG, "MenuFragment created and initialized")
    }

    private fun setupRecyclerView() {
        Log.d(TAG, "setupRecyclerView called")
        menuAdapter = MenuAdapter(mutableListOf()) { menu ->
            // Handle menu item click - navigate to detail
            navigateToMenuDetail(menu)
            Log.d(TAG, "Menu selected: ${menu.getDisplayName()} with ID: ${menu.id}")
        }
        binding.rvMenuList.apply {
            layoutManager = LinearLayoutManager(requireContext())
            adapter = menuAdapter
        }
        Log.d(TAG, "RecyclerView setup completed")
    }
    
    /**
     * Navigate ke menu detail screen
     */
    private fun navigateToMenuDetail(menu: MenuItem) {
        try {
            val bundle = Bundle().apply {
                putString("menuId", menu.id)
                putString("menuName", menu.getDisplayName())
                putString("menuDescription", menu.getDisplayDescription())
                putInt("menuPrice", menu.price)
                putString("menuImageUrl", menu.imageUrl)
                putString("menuCategory", menu.category)
            }
            
            findNavController().navigate(R.id.menuDetailFragment, bundle)
            Log.d(TAG, "Navigating to menu detail for: ${menu.getDisplayName()}")
        } catch (e: Exception) {
            Log.e(TAG, "Error navigating to menu detail", e)
            showToast("Error membuka detail menu")
        }
    }    private fun setupClickListeners() {
        Log.d(TAG, "setupClickListeners called")
        
        // Check if we need to show search results from Home navigation
        arguments?.getString("searchQuery")?.let { query ->
            if (query.isNotEmpty()) {
                showSearchResults(query)
            }
        }
        
        // Clear Search button
        binding.btnClearSearch.setOnClickListener {
            clearSearchResults()
        }
        
        // Category Filter Chips
        binding.chipGroupFilters.setOnCheckedStateChangeListener { _, checkedIds ->
            if (checkedIds.isNotEmpty()) {
                when (checkedIds[0]) {
                    R.id.chipAll -> filterByCategory("All")
                    R.id.chipMainCourse -> filterByCategory("Main Course")
                    R.id.chipAppetizer -> filterByCategory("Appetizer")
                    R.id.chipDessert -> filterByCategory("Dessert")
                    R.id.chipBeverage -> filterByCategory("Beverage")
                }
            }
        }
        
        // Sort/Filter FAB
        binding.fabSortFilter.setOnClickListener {
            showSortFilterDialog()
        }
    }
      /**
     * Show search results when navigated from Home search
     */
    private fun showSearchResults(query: String) {
        Log.d(TAG, "Showing search results for: $query")
        
        // Update the title to show search results
        binding.tvMenuTitle.text = "Menu Search"
        
        // Show search info card
        binding.cardSearchInfo.visibility = View.VISIBLE
        binding.tvSearchQuery.text = "Searching for: \"$query\""
        binding.tvSearchResultCount.text = "Loading results..."
        
        // Filter menu items based on search query using the correct method name
        viewModel.searchMenus(query)
        
        // Show toast for user feedback
        showToast("🔍 Searching for '$query' in menu...")
    }
    
    /**
     * Clear search results and show all menu items
     */
    private fun clearSearchResults() {
        Log.d(TAG, "Clearing search results")
        
        // Reset title
        binding.tvMenuTitle.text = "Our Menu"
        
        // Hide search info card
        binding.cardSearchInfo.visibility = View.GONE
        
        // Reset chip selection to "All"
        binding.chipAll.isChecked = true
        
        // Load all menus
        viewModel.loadAllMenus()
        
        showToast("Search cleared")
    }
    
    /**
     * Filter menu by category
     */
    private fun filterByCategory(category: String) {
        Log.d(TAG, "Filtering by category: $category")
        
        if (category == "All") {
            viewModel.loadAllMenus()
        } else {
            // You can implement category filtering in MenuViewModel
            // For now, we'll show a toast
            showToast("Filtering by $category...")
        }
    }
    
    /**
     * Show sort/filter dialog
     */
    private fun showSortFilterDialog() {
        val options = arrayOf("Price: Low to High", "Price: High to Low", "Name: A to Z", "Name: Z to A", "Rating: Highest First")
        
        android.app.AlertDialog.Builder(requireContext())
            .setTitle("Sort Menu")
            .setItems(options) { _, which ->
                when (which) {
                    0 -> sortMenu("price_asc")
                    1 -> sortMenu("price_desc") 
                    2 -> sortMenu("name_asc")
                    3 -> sortMenu("name_desc")
                    4 -> sortMenu("rating_desc")
                }
            }
            .show()
    }
      /**
     * Sort menu by specified criteria
     */
    private fun sortMenu(sortType: String) {
        Log.d(TAG, "Sorting menu by: $sortType")
        
        val currentMenus = menuAdapter.getCurrentMenus()
        val sortedMenus = when (sortType) {
            "price_asc" -> currentMenus.sortedBy { it.price }
            "price_desc" -> currentMenus.sortedByDescending { it.price }
            "name_asc" -> currentMenus.sortedBy { it.getDisplayName() }
            "name_desc" -> currentMenus.sortedByDescending { it.getDisplayName() }
            "rating_desc" -> currentMenus.sortedByDescending { it.price } // Use price as fallback since rating doesn't exist
            else -> currentMenus
        }
        
        menuAdapter.updateMenus(sortedMenus)
        showToast("Menu sorted by ${sortType.replace("_", " ")}")
    }

    // setupSearchFunctionality method removed - functionality moved to dedicated Search tab

    private fun setupObservers() {
        Log.d(TAG, "setupObservers called")
        
        // Observe menu state changes
        viewModel.menuState.observe(viewLifecycleOwner) { state ->
            Log.d(TAG, "Menu state changed to: $state")
            when (state) {
                is MenuState.Loading -> {
                    Log.d(TAG, "Menu state: Loading")
                    showLoadingState()
                }
                is MenuState.Success -> {
                    Log.d(TAG, "Menu state: Success")
                    showData()
                }
                is MenuState.Empty -> {
                    Log.d(TAG, "Menu state: Empty")
                    showEmptyState("Tidak ada menu yang tersedia")
                }
                is MenuState.Error -> {
                    Log.e(TAG, "Menu state: Error - ${state.message}")
                    showEmptyState("Error: ${state.message}")
                }
            }
        }        // Observe menu list changes
        viewModel.menuList.observe(viewLifecycleOwner) { menus -> 
            Log.d(TAG, "Menu list updated with ${menus.size} items")
            if (menus.isNotEmpty()) {
                Log.d(TAG, "Menu names: ${menus.map { it.getDisplayName() }}")
            }
            menuAdapter.updateMenus(menus)
              // Update search result count if search info card is visible
            if (binding.cardSearchInfo.visibility == View.VISIBLE) {
                binding.tvSearchResultCount.text = "${menus.size} items found"
            }
        }
    }

    /**
     * Test Firebase connection untuk debugging
     */
    private fun testFirebaseConnection() {
        Log.d(TAG, "testFirebaseConnection called")
        try {
            val firebaseTester = FirebaseMenuTester.getInstance()
            firebaseTester.testFirebaseConnection()
            firebaseTester.testFieldQueries()
            firebaseTester.testRealtimeUpdates()
            
            Log.d(TAG, "Firebase testing initiated successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error testing Firebase connection", e)
        }
    }

    private fun showLoadingState() {
        Log.d(TAG, "showLoadingState called")
        binding.stateContainer.isVisible = true
        binding.loadingLayout.isVisible = true
        binding.emptyLayout.isVisible = false
        binding.rvMenuList.isVisible = false
    }

    private fun showEmptyState(message: String) {
        Log.d(TAG, "showEmptyState called with message: $message")
        binding.stateContainer.isVisible = true
        binding.loadingLayout.isVisible = false
        binding.emptyLayout.isVisible = true
        binding.tvMenuStatus.text = message
        binding.rvMenuList.isVisible = false
    }

    private fun showData() {
        Log.d(TAG, "showData called")
        binding.stateContainer.isVisible = false
        binding.loadingLayout.isVisible = false
        binding.emptyLayout.isVisible = false
        binding.rvMenuList.isVisible = true
    }

    override fun onDestroyView() {
        Log.d(TAG, "onDestroyView called")
        super.onDestroyView()
        _binding = null
    }

    private fun showToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            // Ignore toast errors safely
        }
    }
}

/**
 * Adapter untuk RecyclerView menu
 */
class MenuAdapter(
    private var items: List<MenuItem>,
    private val onItemClick: (MenuItem) -> Unit
) : RecyclerView.Adapter<MenuAdapter.MenuViewHolder>() {

    private val TAG = "MenuAdapter"

    fun updateMenus(newMenus: List<MenuItem>) {
        Log.d(TAG, "updateMenus called with ${newMenus.size} items")
        items = newMenus
        notifyDataSetChanged()
    }
    
    fun getCurrentMenus(): List<MenuItem> {
        return items
    }

    inner class MenuViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val ivMenuImage: ImageView = itemView.findViewById(R.id.ivMenuImage)
        val tvMenuName: TextView = itemView.findViewById(R.id.tvMenuName)
        val tvMenuDescription: TextView = itemView.findViewById(R.id.tvMenuDescription)
        val tvMenuPrice: TextView = itemView.findViewById(R.id.tvMenuPrice)
        val tvMenuStatus: TextView = itemView.findViewById(R.id.tvMenuStatus)
        val tvCategoryBadge: TextView = itemView.findViewById(R.id.tvCategoryBadge)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MenuViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_menu, parent, false)
        return MenuViewHolder(view)
    }

    override fun onBindViewHolder(holder: MenuViewHolder, position: Int) {
        val menu = items[position]
        Log.d(TAG, "Binding menu item: ${menu.getDisplayName()} at position $position")

        // Display name (gunakan name yang sebenarnya dari Firebase)
        holder.tvMenuName.text = menu.getDisplayName()
        
        // Display description yang clean dan menarik (tanpa admin dan created info)
        val descriptionText = menu.getShortDescription()
        holder.tvMenuDescription.text = descriptionText
        
        // Display category badge dengan styling yang menarik
        if (menu.category.isNotEmpty()) {
            holder.tvCategoryBadge.text = menu.getCategoryBadgeText()
            holder.tvCategoryBadge.setTextColor(android.graphics.Color.WHITE)
            holder.tvCategoryBadge.setBackgroundColor(menu.getCategoryColor())
            holder.tvCategoryBadge.visibility = android.view.View.VISIBLE
        } else {
            holder.tvCategoryBadge.visibility = android.view.View.GONE
        }
        
        // Display price dengan format yang menarik
        holder.tvMenuPrice.text = menu.getFormattedPrice()
        
        // Display status dengan styling yang menarik
        holder.tvMenuStatus.text = menu.getStatusText()

        // Set text color based on availability
        val statusColor = if (menu.isAvailable) {
            holder.itemView.context.getColor(R.color.text_green)
        } else {
            holder.itemView.context.getColor(R.color.text_hint)
        }
        holder.tvMenuStatus.setTextColor(statusColor)

        // Load image with Glide
        if (menu.imageUrl.isNotEmpty()) {
            Glide.with(holder.itemView.context)
                .load(menu.imageUrl)
                .placeholder(android.R.drawable.ic_menu_gallery)
                .error(android.R.drawable.ic_menu_gallery)
                .into(holder.ivMenuImage)
        } else {
            holder.ivMenuImage.setImageResource(android.R.drawable.ic_menu_gallery)
        }

        // Set click listener
        holder.itemView.setOnClickListener {
            onItemClick(menu)
        }
    }

    override fun getItemCount(): Int = items.size
}
