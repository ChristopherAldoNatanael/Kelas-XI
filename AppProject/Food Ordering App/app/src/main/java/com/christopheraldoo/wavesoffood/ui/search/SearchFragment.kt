package com.christopheraldoo.wavesoffood.ui.search

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import com.christopheraldoo.wavesoffood.databinding.FragmentSearchBinding

/**
 * SearchFragment - Fragment untuk fitur pencarian makanan
 * 
 * ✅ VERSI YANG TELAH DIPERBAIKI:
 * - Search functionality yang bekerja dengan layout yang ada
 * - Popular search buttons
 * - Real-time search
 * - Clean dan tanpa error
 */
class SearchFragment : Fragment() {

    private var _binding: FragmentSearchBinding? = null
    private val binding get() = _binding!!
    
    private val TAG = "SearchFragment"

    // Mock data untuk demo
    private val foodItems = listOf(
        "🍔 Classic Burger - $12.99",
        "🍕 Margherita Pizza - $18.99", 
        "🍝 Pasta Carbonara - $15.99",
        "🥗 Caesar Salad - $11.99",
        "🍗 Fried Chicken - $14.99",
        "☕ Coffee Latte - $5.99",
        "🍣 Sushi Roll - $22.99",
        "🌮 Beef Tacos - $13.99"
    )

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        Log.d(TAG, "onCreateView called")
        _binding = FragmentSearchBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called")
        
        try {
            setupSearchFunctionality()
            setupPopularSearchButtons()
            showToast("🔍 Search is ready! Try searching for food...")
            
            Log.d(TAG, "Search initialization completed successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Error initializing search: ${e.message}", e)
            showToast("Error initializing search")
        }
    }
    
    /**
     * Setup search functionality dengan real-time search
     */
    private fun setupSearchFunctionality() {
        // Setup text watcher untuk real-time search
        binding.etSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                val query = s.toString().trim()
                handleSearchQuery(query)
            }
        })
        
        // Setup click listeners
        binding.ivSearchIcon.setOnClickListener {
            performSearch()
        }
        
        binding.ivClearSearch.setOnClickListener {
            clearSearch()
        }
    }
    
    /**
     * Handle search query dengan real-time feedback
     */
    private fun handleSearchQuery(query: String) {
        when {
            query.isEmpty() -> {
                binding.ivClearSearch.visibility = View.GONE
                hideSearchResults()
            }
            query.length >= 2 -> {
                binding.ivClearSearch.visibility = View.VISIBLE
                performSearchWithQuery(query)
            }
            else -> {
                binding.ivClearSearch.visibility = View.VISIBLE
            }
        }
    }
    
    /**
     * Setup popular search buttons
     */
    private fun setupPopularSearchButtons() {
        binding.btnPopularSearch1.setOnClickListener {
            searchFor("Pizza")
        }
        
        binding.btnPopularSearch2.setOnClickListener {
            searchFor("Burger")
        }
        
        binding.btnPopularSearch3.setOnClickListener {
            searchFor("Sushi")
        }
        
        binding.btnPopularSearch4.setOnClickListener {
            searchFor("Coffee")
        }
    }
    
    /**
     * Perform search dengan query tertentu
     */
    private fun searchFor(query: String) {
        binding.etSearch.setText(query)
        performSearchWithQuery(query)
    }
    
    /**
     * Perform search berdasarkan query
     */
    private fun performSearchWithQuery(query: String) {
        Log.d(TAG, "Performing search for: $query")
        
        // Show loading
        showSearchLoading(true)
        
        // Simulate search delay
        view?.postDelayed({
            val results = getSearchResults(query.lowercase())
            displaySearchResults(query, results)
            showSearchLoading(false)
        }, 300)
    }
    
    /**
     * Get search results berdasarkan query
     */
    private fun getSearchResults(query: String): List<String> {
        return foodItems.filter { 
            it.lowercase().contains(query)
        }
    }
    
    /**
     * Display search results
     */
    private fun displaySearchResults(query: String, results: List<String>) {
        if (results.isNotEmpty()) {
            binding.tvSearchResults.text = "Found ${results.size} results for '$query':\n\n" + results.joinToString("\n")
            binding.tvSearchResults.visibility = View.VISIBLE
            showToast("Found ${results.size} items for '$query'")
        } else {
            binding.tvSearchResults.text = "No results found for '$query'\n\nTry searching for:\n• Pizza\n• Burger\n• Coffee\n• Sushi"
            binding.tvSearchResults.visibility = View.VISIBLE
            showToast("No results found for '$query'")
        }
    }
    
    /**
     * Hide search results
     */
    private fun hideSearchResults() {
        binding.tvSearchResults.visibility = View.GONE
    }
    
    /**
     * Perform search
     */
    private fun performSearch() {
        val query = binding.etSearch.text.toString().trim()
        if (query.isNotEmpty()) {
            performSearchWithQuery(query)
        } else {
            showToast("Please enter search term")
        }
    }
    
    /**
     * Clear search
     */
    private fun clearSearch() {
        binding.etSearch.setText("")
        hideSearchResults()
        binding.ivClearSearch.visibility = View.GONE
        showToast("Search cleared")
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
     * Show toast message
     */
    private fun showToast(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
