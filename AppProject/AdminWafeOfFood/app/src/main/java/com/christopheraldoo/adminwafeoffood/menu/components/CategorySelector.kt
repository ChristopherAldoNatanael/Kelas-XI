package com.christopheraldoo.adminwafeoffood.menu.components

import android.content.Context
import android.util.AttributeSet
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.databinding.ComponentCategorySelectorBinding
import com.christopheraldoo.adminwafeoffood.databinding.ItemCategoryDropdownBinding
import kotlinx.coroutines.*
import android.animation.ValueAnimator
import android.animation.ObjectAnimator
import android.text.Editable
import android.text.TextWatcher
import android.view.animation.AccelerateDecelerateInterpolator

/**
 * Modern Category Selector Component dengan search functionality
 */
class CategorySelector @JvmOverloads constructor(
    context: Context,
    attrs: AttributeSet? = null,
    defStyleAttr: Int = 0
) : LinearLayout(context, attrs, defStyleAttr) {

    private val binding: ComponentCategorySelectorBinding
    private val categories = mutableListOf<CategoryItem>()
    private val filteredCategories = mutableListOf<CategoryItem>()
    private var selectedCategory: CategoryItem? = null
    private var onCategorySelectedListener: ((CategoryItem) -> Unit)? = null
    private var isDropdownVisible = false
    private lateinit var categoryAdapter: CategoryDropdownAdapter

    // Coroutine scope untuk search debouncing
    private val searchScope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    private var searchJob: Job? = null

    init {
        binding = ComponentCategorySelectorBinding.inflate(LayoutInflater.from(context), this, true)
        setupUI()
        setupClickListeners()
        setupSearchFunctionality()
        setupRecyclerView()
    }

    private fun setupUI() {
        // Setup initial state
        binding.dropdownContainer.visibility = View.GONE
        binding.dropdownContainer.alpha = 0f
        
        // Setup loading state
        binding.loadingIndicator.visibility = View.GONE
    }

    private fun setupClickListeners() {
        // Main selector click
        binding.categorySelector.setOnClickListener {
            toggleDropdown()
        }

        // Search clear button
        binding.clearSearchButton.setOnClickListener {
            binding.searchEditText.text?.clear()
            binding.clearSearchButton.visibility = View.GONE
        }

        // Click outside to close
        binding.dropdownOverlay.setOnClickListener {
            hideDropdown()
        }
    }

    private fun setupSearchFunctionality() {
        binding.searchEditText.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            
            override fun afterTextChanged(s: Editable?) {
                val query = s?.toString()?.trim() ?: ""
                
                // Show/hide clear button
                binding.clearSearchButton.visibility = if (query.isNotEmpty()) View.VISIBLE else View.GONE
                
                // Debounce search
                searchJob?.cancel()
                searchJob = searchScope.launch {
                    delay(300) // 300ms debounce
                    filterCategories(query)
                }
            }
        })
    }

    private fun setupRecyclerView() {
        categoryAdapter = CategoryDropdownAdapter { category ->
            selectCategory(category)
        }
        
        binding.categoryRecyclerView.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = categoryAdapter
        }
    }

    private fun toggleDropdown() {
        if (isDropdownVisible) {
            hideDropdown()
        } else {
            showDropdown()
        }
    }

    private fun showDropdown() {
        if (isDropdownVisible) return
        
        isDropdownVisible = true
        binding.dropdownContainer.visibility = View.VISIBLE
        binding.searchEditText.requestFocus()
        
        // Animate dropdown appearance
        val slideDown = ObjectAnimator.ofFloat(binding.dropdownCard, "translationY", -20f, 0f)
        val fadeIn = ObjectAnimator.ofFloat(binding.dropdownContainer, "alpha", 0f, 1f)
        val scaleX = ObjectAnimator.ofFloat(binding.dropdownCard, "scaleX", 0.95f, 1f)
        val scaleY = ObjectAnimator.ofFloat(binding.dropdownCard, "scaleY", 0.95f, 1f)
        
        listOf(slideDown, fadeIn, scaleX, scaleY).forEach { animator ->
            animator.duration = 250
            animator.interpolator = AccelerateDecelerateInterpolator()
            animator.start()
        }

        // Rotate arrow icon
        binding.dropdownArrow.animate()
            .rotation(180f)
            .setDuration(200)
            .start()
    }

    private fun hideDropdown() {
        if (!isDropdownVisible) return
        
        isDropdownVisible = false
        
        // Animate dropdown disappearance
        val slideUp = ObjectAnimator.ofFloat(binding.dropdownCard, "translationY", 0f, -20f)
        val fadeOut = ObjectAnimator.ofFloat(binding.dropdownContainer, "alpha", 1f, 0f)
        
        fadeOut.duration = 200
        slideUp.duration = 200
        
        fadeOut.start()
        slideUp.start()
        
        // Hide container after animation
        binding.dropdownContainer.postDelayed({
            binding.dropdownContainer.visibility = View.GONE
            binding.searchEditText.text?.clear()
        }, 200)

        // Rotate arrow back
        binding.dropdownArrow.animate()
            .rotation(0f)
            .setDuration(200)
            .start()
    }

    private fun filterCategories(query: String) {
        filteredCategories.clear()
        
        if (query.isEmpty()) {
            filteredCategories.addAll(categories)
        } else {
            categories.forEach { category ->
                if (category.name.contains(query, ignoreCase = true) ||
                    category.description.contains(query, ignoreCase = true)) {
                    filteredCategories.add(category)
                }
            }
        }
        
        categoryAdapter.updateCategories(filteredCategories)
        
        // Show "no results" state if needed
        binding.noResultsText.visibility = if (filteredCategories.isEmpty() && query.isNotEmpty()) {
            View.VISIBLE
        } else {
            View.GONE
        }
    }

    private fun selectCategory(category: CategoryItem) {
        selectedCategory = category
        binding.selectedCategoryText.text = category.name
        binding.selectedCategoryIcon.setImageResource(category.iconRes)
        
        // Update UI state
        binding.categorySelector.setBackgroundResource(R.drawable.selector_category_selected)
        
        // Notify listener
        onCategorySelectedListener?.invoke(category)
        
        // Hide dropdown with slight delay for better UX
        binding.dropdownContainer.postDelayed({ hideDropdown() }, 100)
        
        // Show selection animation
        animateSelection()
    }

    private fun animateSelection() {
        val scaleUp = ValueAnimator.ofFloat(1f, 1.05f, 1f)
        scaleUp.duration = 200
        scaleUp.addUpdateListener { animator ->
            val scale = animator.animatedValue as Float
            binding.categorySelector.scaleX = scale
            binding.categorySelector.scaleY = scale
        }
        scaleUp.start()
    }

    // Public methods
    fun setCategories(categoryList: List<CategoryItem>) {
        categories.clear()
        categories.addAll(categoryList)
        filteredCategories.clear()
        filteredCategories.addAll(categoryList)
        categoryAdapter.updateCategories(filteredCategories)
        
        // Hide loading, show content
        binding.loadingIndicator.visibility = View.GONE
        binding.categorySelector.visibility = View.VISIBLE
    }

    fun setSelectedCategory(category: CategoryItem) {
        selectCategory(category)
    }

    fun getSelectedCategory(): CategoryItem? = selectedCategory

    fun setOnCategorySelectedListener(listener: (CategoryItem) -> Unit) {
        this.onCategorySelectedListener = listener
    }

    fun showLoading() {
        binding.loadingIndicator.visibility = View.VISIBLE
        binding.categorySelector.visibility = View.GONE
    }

    fun showError(message: String) {
        binding.loadingIndicator.visibility = View.GONE
        binding.categorySelector.visibility = View.VISIBLE
        // You can add error handling UI here
    }

    override fun onDetachedFromWindow() {
        super.onDetachedFromWindow()
        searchScope.cancel()
    }

    // Data classes
    data class CategoryItem(
        val id: String,
        val name: String,
        val description: String,
        val iconRes: Int,
        val color: String = "#FF6B6B"
    )

    // Adapter for dropdown items
    private inner class CategoryDropdownAdapter(
        private val onItemClick: (CategoryItem) -> Unit
    ) : RecyclerView.Adapter<CategoryDropdownAdapter.CategoryViewHolder>() {

        private val items = mutableListOf<CategoryItem>()

        fun updateCategories(newItems: List<CategoryItem>) {
            items.clear()
            items.addAll(newItems)
            notifyDataSetChanged()
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): CategoryViewHolder {
            val binding = ItemCategoryDropdownBinding.inflate(
                LayoutInflater.from(parent.context), parent, false
            )
            return CategoryViewHolder(binding)
        }

        override fun onBindViewHolder(holder: CategoryViewHolder, position: Int) {
            holder.bind(items[position])
        }

        override fun getItemCount(): Int = items.size

        inner class CategoryViewHolder(
            private val binding: ItemCategoryDropdownBinding
        ) : RecyclerView.ViewHolder(binding.root) {

            fun bind(category: CategoryItem) {
                binding.apply {
                    categoryName.text = category.name
                    categoryDescription.text = category.description
                    categoryIcon.setImageResource(category.iconRes)
                    
                    // Set selected state
                    val isSelected = category.id == selectedCategory?.id
                    root.isSelected = isSelected
                    
                    if (isSelected) {
                        root.setBackgroundResource(R.drawable.bg_category_item_selected)
                        selectedIndicator.visibility = View.VISIBLE
                    } else {
                        root.setBackgroundResource(R.drawable.bg_category_item_normal)
                        selectedIndicator.visibility = View.GONE
                    }
                    
                    // Click listener with animation
                    root.setOnClickListener {
                        animateItemClick {
                            onItemClick(category)
                        }
                    }
                }
            }

            private fun animateItemClick(onClick: () -> Unit) {
                binding.root.animate()
                    .scaleX(0.95f)
                    .scaleY(0.95f)
                    .setDuration(100)
                    .withEndAction {
                        binding.root.animate()
                            .scaleX(1f)
                            .scaleY(1f)
                            .setDuration(100)
                            .withEndAction { onClick() }
                            .start()
                    }
                    .start()
            }
        }
    }
}
