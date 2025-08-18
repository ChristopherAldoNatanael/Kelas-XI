package com.christopheraldoo.adminwafeoffood.menu.activities

import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.util.Log
import android.view.MenuItem
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.bumptech.glide.Glide
import com.bumptech.glide.load.engine.DiskCacheStrategy
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.databinding.ActivityAddEditMenuBinding
import com.christopheraldoo.adminwafeoffood.menu.model.DefaultMenuCategories
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem as MenuItemModel
import com.christopheraldoo.adminwafeoffood.menu.model.MenuValidation
import com.christopheraldoo.adminwafeoffood.menu.viewmodel.MenuViewModel
import com.github.dhaval2404.imagepicker.ImagePicker
import kotlinx.coroutines.launch

class AddEditMenuActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityAddEditMenuBinding
    private val viewModel: MenuViewModel by viewModels()
    
    private var currentMenuItem: MenuItemModel? = null
    private var selectedImageUri: Uri? = null
    private var mode: String = MODE_ADD
    private var menuId: String? = null
    private var hasUnsavedChanges = false
    
    companion object {
        private const val TAG = "AddEditMenuActivity"
        const val MODE_ADD = "add"
        const val MODE_EDIT = "edit"
        const val MODE_VIEW = "view"
        const val EXTRA_MODE = "mode"
        const val EXTRA_MENU_ID = "menu_id"
        const val REQUEST_CODE_ADD_MENU = 1001
        const val REQUEST_CODE_EDIT_MENU = 1002
        
        fun createAddIntent(context: android.content.Context): Intent {
            return Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra(EXTRA_MODE, MODE_ADD)
            }
        }
        
        fun createEditIntent(context: android.content.Context, menuId: String): Intent {
            return Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra(EXTRA_MODE, MODE_EDIT)
                putExtra(EXTRA_MENU_ID, menuId)
            }
        }
        
        fun createViewIntent(context: android.content.Context, menuId: String): Intent {
            return Intent(context, AddEditMenuActivity::class.java).apply {
                putExtra(EXTRA_MODE, MODE_VIEW)
                putExtra(EXTRA_MENU_ID, menuId)
            }
        }
    }
    
    private val imagePickerLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val data = result.data
            selectedImageUri = data?.data
            selectedImageUri?.let { uri ->
                loadImageFromUri(uri)
                hasUnsavedChanges = true
            }
        }
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityAddEditMenuBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        Log.d(TAG, "onCreate: Starting AddEditMenuActivity")
        
        setupUI()
        handleIntent()
        setupClickListeners()
        observeViewModel()
        setupTextWatchers()
        
        // Handle back press - FIX: Gunakan OnBackPressedCallback
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                handleBackPress()
            }
        })
    }
    
    private fun setupUI() {
        try {
            // Setup toolbar
            setSupportActionBar(binding.toolbar)
            supportActionBar?.apply {
                setDisplayHomeAsUpEnabled(true)
                setDisplayShowHomeEnabled(true)
            }
            
            // Setup category spinner
            setupCategorySpinner()
            
            Log.d(TAG, "setupUI: UI setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error in setupUI", e)
            showError("Error setting up UI: ${e.message}")
        }
    }
    
    private fun setupCategorySpinner() {
        try {
            val categories = DefaultMenuCategories.getCategoryDisplayNames()
            val adapter = ArrayAdapter(
                this, 
                android.R.layout.simple_spinner_item, 
                categories
            )
            adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
            binding.spinnerCategory.adapter = adapter
            Log.d(TAG, "Category spinner setup with ${categories.size} categories")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up category spinner", e)
        }
    }
    
    private fun handleIntent() {
        try {
            mode = intent.getStringExtra(EXTRA_MODE) ?: MODE_ADD
            menuId = intent.getStringExtra(EXTRA_MENU_ID)
            
            Log.d(TAG, "handleIntent: mode=$mode, menuId=$menuId")
            
            when (mode) {
                MODE_ADD -> {
                    title = "Add New Menu"
                    binding.btnSaveMenu.text = "Add Menu"
                }
                MODE_EDIT -> {
                    title = "Edit Menu"
                    binding.btnSaveMenu.text = "Update Menu"
                    loadMenuData()
                }
                MODE_VIEW -> {
                    title = "Menu Details"
                    binding.btnSaveMenu.visibility = View.GONE
                    setFieldsReadOnly()
                    loadMenuData()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling intent", e)
            showError("Error loading activity: ${e.message}")
            finish()
        }
    }
    
    private fun loadMenuData() {
        val id = menuId
        if (id.isNullOrEmpty()) {
            showError("Menu ID is required for this operation")
            finish()
            return
        }
        
        lifecycleScope.launch {
            try {
                Log.d(TAG, "Loading menu data for ID: $id")
                showLoading(true)
                
                val menuItem = viewModel.getMenuById(id)
                if (menuItem != null) {
                    currentMenuItem = menuItem
                    populateFields(menuItem)
                    Log.d(TAG, "Menu data loaded successfully: ${menuItem.name}")
                } else {
                    showError("Menu not found")
                    finish()
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading menu data", e)
                showError("Error loading menu: ${e.message}")
                finish()
            } finally {
                showLoading(false)
            }
        }
    }
    
    private fun populateFields(menuItem: MenuItemModel) {
        try {
            binding.apply {
                etMenuName.setText(menuItem.name)
                etMenuDescription.setText(menuItem.description)
                etMenuPrice.setText(if (menuItem.price > 0) menuItem.price.toString() else "")
                
                // Set category
                setCategorySelection(menuItem.category)
                
                // Load image
                loadMenuImage(menuItem.imageUrl)
                
                // Set availability
                switchAvailable.isChecked = menuItem.isAvailable
                
                Log.d(TAG, "Fields populated for menu: ${menuItem.name}")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error populating fields", e)
            showError("Error displaying menu data")
        }
    }
    
    private fun setCategorySelection(categoryName: String) {
        try {
            val categories = DefaultMenuCategories.getCategories()
            val categoryIndex = categories.indexOfFirst { 
                it.name == categoryName || it.displayName == categoryName 
            }
            if (categoryIndex >= 0) {
                binding.spinnerCategory.setSelection(categoryIndex)
                Log.d(TAG, "Category set to index $categoryIndex for category: $categoryName")
            } else {
                Log.w(TAG, "Category not found: $categoryName")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting category selection", e)
        }
    }
    
    private fun loadMenuImage(imageUrl: String) {
        try {
            if (imageUrl.isNotEmpty()) {
                Glide.with(this)
                    .load(imageUrl)
                    .placeholder(android.R.drawable.ic_menu_gallery)
                    .error(android.R.drawable.ic_menu_gallery)
                    .diskCacheStrategy(DiskCacheStrategy.ALL)
                    .into(binding.ivMenuImage)
                
                binding.tvImageHint?.visibility = View.GONE
                Log.d(TAG, "Image loaded from URL")
            } else {
                binding.ivMenuImage.setImageResource(android.R.drawable.ic_menu_gallery)
                binding.tvImageHint?.visibility = View.VISIBLE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error loading image", e)
            binding.ivMenuImage.setImageResource(android.R.drawable.ic_menu_gallery)
        }
    }
    
    private fun loadImageFromUri(uri: Uri) {
        try {
            Glide.with(this)
                .load(uri)
                .placeholder(android.R.drawable.ic_menu_gallery)
                .error(android.R.drawable.ic_menu_gallery)
                .into(binding.ivMenuImage)
            
            binding.tvImageHint?.visibility = View.GONE
            Log.d(TAG, "Image loaded from URI: $uri")
        } catch (e: Exception) {
            Log.e(TAG, "Error loading image from URI", e)
        }
    }
    
    private fun setFieldsReadOnly() {
        try {
            binding.apply {
                etMenuName.isEnabled = false
                etMenuDescription.isEnabled = false
                etMenuPrice.isEnabled = false
                spinnerCategory.isEnabled = false
                switchAvailable.isEnabled = false
                ivMenuImage.isClickable = false
                btnSelectImage?.isEnabled = false
            }
            Log.d(TAG, "Fields set to read-only")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting fields read-only", e)
        }
    }
    
    private fun setupClickListeners() {
        try {
            binding.apply {
                btnSaveMenu.setOnClickListener {
                    if (mode != MODE_VIEW) {
                        saveMenu()
                    }
                }
                
                ivMenuImage.setOnClickListener {
                    if (mode != MODE_VIEW) {
                        pickImage()
                    }
                }
                
                btnSelectImage?.setOnClickListener {
                    if (mode != MODE_VIEW) {
                        pickImage()
                    }
                }
            }
            Log.d(TAG, "Click listeners setup completed")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up click listeners", e)
        }
    }
    
    private fun setupTextWatchers() {
        try {
            if (mode != MODE_VIEW) {
                binding.apply {
                    etMenuName.doOnTextChanged { _, _, _, _ -> hasUnsavedChanges = true }
                    etMenuDescription.doOnTextChanged { _, _, _, _ -> hasUnsavedChanges = true }
                    etMenuPrice.doOnTextChanged { _, _, _, _ -> hasUnsavedChanges = true }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up text watchers", e)
        }
    }
    
    private fun pickImage() {
        try {
            ImagePicker.with(this)
                .crop()
                .compress(1024)
                .maxResultSize(1080, 1080)
                .createIntent { intent ->
                    imagePickerLauncher.launch(intent)
                }
        } catch (e: Exception) {
            Log.e(TAG, "Error picking image", e)
            showError("Error opening image picker")
        }
    }
    
    private fun saveMenu() {
        try {
            if (!validateInput()) {
                Log.w(TAG, "Input validation failed")
                return
            }
            
            val menuItem = createMenuItemFromInput()
            if (menuItem == null) {
                showError("Error creating menu data")
                return
            }
            
            Log.d(TAG, "Saving menu: ${menuItem.name}")
            
            when (mode) {
                MODE_ADD -> viewModel.addMenu(menuItem)
                MODE_EDIT -> viewModel.updateMenu(menuItem)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error saving menu", e)
            showError("Error saving menu: ${e.message}")
        }
    }
    
    private fun validateInput(): Boolean {
        try {
            binding.apply {
                val name = etMenuName.text.toString().trim()
                val description = etMenuDescription.text.toString().trim()
                val priceText = etMenuPrice.text.toString().trim()
                
                // Clear previous errors
                etMenuName.error = null
                etMenuDescription.error = null
                etMenuPrice.error = null
                
                // Validate name - PERBAIKI INI
                if (name.isBlank()) {
                    etMenuName.error = "Menu name is required"
                    etMenuName.requestFocus()
                    return false
                }
                if (name.length < 3) {
                    etMenuName.error = "Menu name must be at least 3 characters"
                    etMenuName.requestFocus()
                    return false
                }
                if (name.length > 50) {
                    etMenuName.error = "Menu name must not exceed 50 characters"
                    etMenuName.requestFocus()
                    return false
                }
                
                // Validate description - PERBAIKI INI
                if (description.isBlank()) {
                    etMenuDescription.error = "Description is required"
                    etMenuDescription.requestFocus()
                    return false
                }
                if (description.length < 10) {
                    etMenuDescription.error = "Description must be at least 10 characters"
                    etMenuDescription.requestFocus()
                    return false
                }
                if (description.length > 200) {
                    etMenuDescription.error = "Description must not exceed 200 characters"
                    etMenuDescription.requestFocus()
                    return false
                }
                
                // Validate price - PERBAIKI INI
                if (priceText.isBlank()) {
                    etMenuPrice.error = "Price is required"
                    etMenuPrice.requestFocus()
                    return false
                }
                
                try {
                    val price = priceText.toDouble()
                    if (price <= 0) {
                        etMenuPrice.error = "Price must be greater than 0"
                        etMenuPrice.requestFocus()
                        return false
                    }
                    if (price > 10000000) {
                        etMenuPrice.error = "Price must not exceed Rp 10,000,000"
                        etMenuPrice.requestFocus()
                        return false
                    }
                } catch (e: NumberFormatException) {
                    etMenuPrice.error = "Invalid price format"
                    etMenuPrice.requestFocus()
                    return false
                }
                
                // Validate category
                if (spinnerCategory.selectedItemPosition < 0) {
                    showError("Please select a category")
                    return false
                }
                
                return true
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error during validation", e)
            showError("Validation error: ${e.message}")
            return false
        }
    }
    
    private fun createMenuItemFromInput(): MenuItemModel? {
        return try {
            val name = binding.etMenuName.text.toString().trim()
            val description = binding.etMenuDescription.text.toString().trim()
            val price = binding.etMenuPrice.text.toString().toDouble()
            
            // Get selected category
            val selectedCategoryPosition = binding.spinnerCategory.selectedItemPosition
            val categories = DefaultMenuCategories.getCategories()
            val selectedCategory = if (selectedCategoryPosition >= 0 && selectedCategoryPosition < categories.size) {
                categories[selectedCategoryPosition].name
            } else {
                "main_course" // default
            }
            
            val isAvailable = binding.switchAvailable.isChecked
            val imageUrl = selectedImageUri?.toString() ?: currentMenuItem?.imageUrl ?: ""
            
            // Create and return MenuItem
            val menuItem = when (mode) {
                MODE_EDIT -> {
                    currentMenuItem?.copy(
                        name = name,
                        description = description,
                        price = price,
                        category = selectedCategory,
                        isAvailable = isAvailable,
                        imageUrl = imageUrl,
                        updatedAt = System.currentTimeMillis()
                    )
                }
                else -> {
                    MenuItemModel(
                        id = "", // Will be generated by Firebase
                        name = name,
                        description = description,
                        price = price,
                        category = selectedCategory,
                        isAvailable = isAvailable,
                        imageUrl = imageUrl,
                        createdAt = System.currentTimeMillis(),
                        updatedAt = System.currentTimeMillis(),
                        adminId = "admin_001" // TODO: Replace with actual admin ID from auth
                    )
                }
            }
            
            menuItem
            
        } catch (e: Exception) {
            Log.e(TAG, "Error creating menu item from input", e)
            null
        }
    }
    
    private fun observeViewModel() {
        try {
            lifecycleScope.launch {
                viewModel.error.collect { error ->
                    error?.let {
                        Log.d(TAG, "ViewModel error received: $it")
                        showError(it)
                        
                        // PERBAIKI - Close activity hanya untuk success message
                        if (it.startsWith("✅") || it.contains("berhasil")) {
                            hasUnsavedChanges = false
                            setResult(Activity.RESULT_OK)
                            finish()
                        }
                        viewModel.clearError()
                    }
                }
            }
            
            lifecycleScope.launch {
                viewModel.isLoading.collect { isLoading ->
                    Log.d(TAG, "Loading state: $isLoading")
                    showLoading(isLoading)
                    updateSaveButtonState(isLoading)
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up ViewModel observers", e)
        }
    }
    
    private fun updateSaveButtonState(isLoading: Boolean) {
        try {
            binding.btnSaveMenu.apply {
                isEnabled = !isLoading
                text = when {
                    isLoading -> "Saving..." // INI yang bikin tombol stuck "Saving"
                    mode == MODE_ADD -> "Add Menu"
                    mode == MODE_EDIT -> "Update Menu"
                    else -> "Save"
                }
            }
            Log.d(TAG, "Save button updated - enabled: ${!isLoading}, text: ${binding.btnSaveMenu.text}")
        } catch (e: Exception) {
            Log.e(TAG, "Error updating save button state", e)
        }
    }
    
    private fun showLoading(show: Boolean) {
        try {
            binding.progressBar.visibility = if (show) View.VISIBLE else View.GONE
        } catch (e: Exception) {
            Log.e(TAG, "Error showing loading", e)
        }
    }
    
    private fun showError(message: String) {
        try {
            Toast.makeText(this, message, Toast.LENGTH_LONG).show()
            Log.e(TAG, "Error shown to user: $message")
        } catch (e: Exception) {
            Log.e(TAG, "Error showing error message", e)
        }
    }
    
    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            android.R.id.home -> {
                handleBackPress()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }
    
    private fun handleBackPress() {
        if (hasUnsavedChanges && mode != MODE_VIEW) {
            showUnsavedChangesDialog()
        } else {
            finish()
        }
    }
    
    private fun showUnsavedChangesDialog() {
        AlertDialog.Builder(this)
            .setTitle("Unsaved Changes")
            .setMessage("You have unsaved changes. Are you sure you want to leave?")
            .setPositiveButton("Leave") { _, _ ->
                finish()
            }
            .setNegativeButton("Stay", null)
            .show()
    }
    
    override fun onDestroy() {
        super.onDestroy()
        Log.d(TAG, "Activity destroyed")
    }
}

// Extension function untuk text watcher
private inline fun android.widget.EditText.doOnTextChanged(
    crossinline action: (
        text: CharSequence?,
        start: Int,
        count: Int,
        after: Int
    ) -> Unit
) = addTextChangedListener(object : android.text.TextWatcher {
    override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
    override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
        action(s, start, count, before)
    }
    override fun afterTextChanged(s: android.text.Editable?) {}
})