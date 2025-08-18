package com.christopheraldoo.wavesoffood.ui.search

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentSearchBinding

/**
 * SearchFragment - Fragment untuk fitur pencarian makanan
 * 
 * Fragment ini menampilkan:
 * - Search bar dengan real-time search
 * - Popular searches/suggestions
 * - Recent searches history
 * - Search results dengan filter
 * - Quick filter buttons (categories)
 */
class SearchFragment : Fragment() {

    // View binding untuk akses mudah ke view tanpa findViewById
    private var _binding: FragmentSearchBinding? = null
    private val binding get() = _binding!!

    // Simulasi data untuk demo
    private val popularSearches = listOf(
        "Pizza", "Burger", "Sushi", "Pasta", "Salad", "Coffee"
    )
    
    private val recentSearches = mutableListOf<String>()

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        // Inflate layout menggunakan view binding
        _binding = FragmentSearchBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Setup animasi masuk untuk elemen UI
        setupAnimations()
        
        // Setup search functionality
        setupSearchFunctionality()
        
        // Setup click listeners
        setupClickListeners()
        
        // Setup popular searches
        setupPopularSearches()
        
        // Focus pada search bar saat fragment dibuka
        binding.etSearch.requestFocus()
    }

    /**
     * Setup animasi masuk untuk semua elemen UI
     */
    private fun setupAnimations() {
        // Animasi untuk search container (slide down + fade in)
        binding.searchContainer.apply {
            alpha = 0f
            translationY = -50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(500)
                .setStartDelay(100)
                .start()
        }
        
        // Animasi untuk popular searches (slide up + fade in)
        binding.popularSearchesContainer.apply {
            alpha = 0f
            translationY = 50f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(300)
                .start()
        }
    }

    /**
     * Setup search functionality dengan real-time search
     */
    private fun setupSearchFunctionality() {
        binding.etSearch.apply {
            hint = "Search for delicious food..."
            
            addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
                override fun afterTextChanged(s: Editable?) {
                    val query = s.toString().trim()
                    handleSearchQuery(query)
                }
            })
        }
    }

    /**
     * Setup click listeners untuk berbagai elemen
     */
    private fun setupClickListeners() {
        // Click listener untuk search icon
        binding.ivSearchIcon.setOnClickListener {
            performSearch()
        }
        
        // Click listener untuk clear search
        binding.ivClearSearch.setOnClickListener {
            clearSearch()
        }
        
        // Setup popular search buttons
        setupPopularSearchButtons()
    }

    /**
     * Setup popular searches dengan buttons yang bisa diklik
     */
    private fun setupPopularSearches() {
        // Di implementasi nyata, ini akan menggunakan RecyclerView
        // Untuk demo, kita tampilkan sebagai text
        showToast("Popular searches loaded! 🔥")
    }

    /**
     * Setup popular search buttons
     */
    private fun setupPopularSearchButtons() {
        // Popular search buttons (simulasi)
        binding.btnPopularSearch1.setOnClickListener {
            animateButtonClick(it) {
                searchFor("Pizza")
            }
        }
        
        binding.btnPopularSearch2.setOnClickListener {
            animateButtonClick(it) {
                searchFor("Burger")
            }
        }
        
        binding.btnPopularSearch3.setOnClickListener {
            animateButtonClick(it) {
                searchFor("Sushi")
            }
        }
        
        binding.btnPopularSearch4.setOnClickListener {
            animateButtonClick(it) {
                searchFor("Coffee")
            }
        }
    }    /**
     * Handle search query dengan real-time feedback
     */
    private fun handleSearchQuery(query: String) {
        when {
            query.isEmpty() -> {
                // Show popular searches
                binding.ivClearSearch.visibility = View.GONE
                showSearchSuggestions()
            }
            query.length >= 2 -> {
                // Show search results
                binding.ivClearSearch.visibility = View.VISIBLE
                performSearchWithQuery(query)
            }
            else -> {
                binding.ivClearSearch.visibility = View.GONE
            }
        }
    }

    /**
     * Perform search dengan query tertentu dan tampilkan hasil realistis
     */
    private fun performSearchWithQuery(query: String) {
        val searchResults = getSearchResults(query.lowercase())
        displaySearchResults(query, searchResults)
    }

    /**
     * Get search results berdasarkan query
     */
    private fun getSearchResults(query: String): List<FoodItem> {
        val allFoodItems = listOf(
            FoodItem("Classic Burger", "$12.99", "🍔", "American"),
            FoodItem("Cheese Burger", "$14.99", "🍔", "American"),
            FoodItem("Chicken Burger", "$13.99", "🍔", "American"),
            FoodItem("Margherita Pizza", "$18.99", "🍕", "Italian"),
            FoodItem("Pepperoni Pizza", "$21.99", "🍕", "Italian"),
            FoodItem("Hawaiian Pizza", "$19.99", "🍕", "Italian"),
            FoodItem("Creamy Pasta", "$15.99", "🍝", "Italian"),
            FoodItem("Bolognese Pasta", "$17.99", "🍝", "Italian"),
            FoodItem("Seafood Pasta", "$22.99", "🍝", "Italian"),
            FoodItem("Fresh Salad", "$9.99", "🥗", "Healthy"),
            FoodItem("Caesar Salad", "$11.99", "🥗", "Healthy"),
            FoodItem("Greek Salad", "$10.99", "🥗", "Healthy"),
            FoodItem("Chicken Wings", "$16.99", "🍗", "American"),
            FoodItem("Fish & Chips", "$18.99", "🐟", "British"),
            FoodItem("Beef Steak", "$25.99", "🥩", "American"),
            FoodItem("Fried Rice", "$11.99", "🍚", "Asian"),
            FoodItem("Chicken Teriyaki", "$14.99", "🍗", "Japanese"),
            FoodItem("Sushi Roll", "$19.99", "🍣", "Japanese"),
            FoodItem("Ramen Bowl", "$13.99", "🍜", "Japanese"),
            FoodItem("Tacos", "$8.99", "🌮", "Mexican")
        )
        
        return allFoodItems.filter { item ->
            item.name.lowercase().contains(query) || 
            item.category.lowercase().contains(query)
        }
    }    /**
     * Display search results dengan format yang menarik
     */
    private fun displaySearchResults(query: String, results: List<FoodItem>) {
        if (results.isEmpty()) {
            showToast("😕 No results found for '$query'. Try different keywords!")
            return
        }
        
        val resultText = buildString {
            append("🔍 Found ${results.size} results for '$query':\n\n")
            
            // Group by category
            val groupedResults = results.groupBy { it.category }
            groupedResults.forEach { (category, items) ->
                append("📁 $category:\n")
                items.forEach { item ->
                    append("${item.emoji} ${item.name} - ${item.price}\n")
                }
                append("\n")
            }
            
            append("💡 Navigate to Menu to see full details!")
        }
        
        showToast(resultText)
        
        // Show navigation options after search
        showNavigationOptions(results.size)
        
        // Simulate adding items to suggestion for future searches
        if (!recentSearches.contains(query)) {
            recentSearches.add(0, query)
            if (recentSearches.size > 5) {
                recentSearches.removeAt(recentSearches.size - 1)
            }
        }
    }

    /**
     * Show navigation options setelah search
     */
    private fun showNavigationOptions(resultCount: Int) {
        val builder = android.app.AlertDialog.Builder(requireContext())
        builder.setTitle("🔍 Search Complete!")
            .setMessage("Found $resultCount delicious items! What would you like to do?")
            .setPositiveButton("📋 View in Menu") { _, _ ->
                navigateToMenu()
            }
            .setNeutralButton("🛒 Check Cart") { _, _ ->
                navigateToCart()
            }
            .setNegativeButton("🔍 Search Again", null)
            .show()
    }

    /**
     * Navigate to Menu Fragment
     */
    private fun navigateToMenu() {
        try {
            findNavController().navigate(R.id.navigation_menu)
            showToast("📋 Opening menu...")
        } catch (e: Exception) {
            showToast("Menu available via bottom navigation!")
        }
    }

    /**
     * Navigate to Cart Fragment
     */
    private fun navigateToCart() {
        try {
            findNavController().navigate(R.id.navigation_cart)
            showToast("🛒 Opening cart...")
        } catch (e: Exception) {
            showToast("Cart available via bottom navigation!")
        }
    }

    /**
     * Data class untuk food items
     */
    data class FoodItem(
        val name: String,
        val price: String,
        val emoji: String,
        val category: String
    )

    /**
     * Perform search dengan query tertentu
     */
    private fun searchFor(query: String) {
        binding.etSearch.setText(query)
        performSearchWithQuery(query)
        
        // Add to recent searches
        if (!recentSearches.contains(query)) {
            recentSearches.add(0, query)
            if (recentSearches.size > 5) {
                recentSearches.removeAt(5)
            }
        }
    }    /**
     * Perform search berdasarkan input user
     */
    private fun performSearch() {
        val query = binding.etSearch.text.toString().trim()
        if (query.isNotEmpty()) {
            val searchResults = getSearchResults(query.lowercase())
            displaySearchResults(query, searchResults)
        } else {
            showToast("Please enter search term")
        }
    }

    /**
     * Show/hide search loading
     */
    private fun showSearchLoading(isLoading: Boolean) {
        if (isLoading) {
            binding.searchProgressBar.visibility = View.VISIBLE
            binding.ivSearchIcon.visibility = View.GONE
        } else {
            binding.searchProgressBar.visibility = View.GONE
            binding.ivSearchIcon.visibility = View.VISIBLE
        }
    }

    /**
     * Show search results
     */
    private fun showSearchResults(query: String) {
        // Simulasi hasil pencarian
        val resultCount = (1..10).random()
        showToast("Found $resultCount results for '$query' 🍴")
        
        // Di implementasi nyata, ini akan update RecyclerView dengan results
        // Update UI untuk menampilkan hasil pencarian
        binding.tvSearchResults.apply {
            text = "Found $resultCount delicious results for '$query'"
            visibility = View.VISIBLE
        }
    }

    /**
     * Show search suggestions
     */
    private fun showSearchSuggestions() {
        binding.tvSearchResults.visibility = View.GONE
        // Show popular searches or recent searches
    }

    /**
     * Clear search input dan results
     */
    private fun clearSearch() {
        binding.etSearch.setText("")
        binding.ivClearSearch.visibility = View.GONE
        binding.tvSearchResults.visibility = View.GONE
        showSearchSuggestions()
        showToast("Search cleared 🗑️")
    }

    /**
     * Navigasi ke hasil detail atau menu
     */
    private fun navigateToFoodDetail(foodName: String) {
        try {
            // Navigation ke detail page (belum diimplementasi)
            showToast("Opening $foodName details...")
        } catch (e: Exception) {
            showToast("Food detail coming soon!")
        }
    }

    /**
     * Animasi untuk memberikan feedback visual saat tombol diklik
     */
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

    /**
     * Helper function untuk menampilkan toast message
     */
    private fun showToast(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }

    /**
     * Cleanup resources saat fragment di destroy
     */
    override fun onDestroyView() {
        super.onDestroyView()
        // Clear view binding reference untuk mencegah memory leak
        _binding = null
    }
}