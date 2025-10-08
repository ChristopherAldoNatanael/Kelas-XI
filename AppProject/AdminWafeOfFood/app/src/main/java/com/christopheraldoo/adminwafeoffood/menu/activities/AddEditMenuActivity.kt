package com.christopheraldoo.adminwafeoffood.menu.activities

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.OpenableColumns
import android.text.Editable
import android.text.TextWatcher
import android.util.Log
import android.util.Patterns
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModelProvider
import com.bumptech.glide.Glide
import com.christopheraldoo.adminwafeoffood.databinding.ActivityAddEditMenuBinding
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem
import com.christopheraldoo.adminwafeoffood.menu.model.DefaultMenuCategories
import com.christopheraldoo.adminwafeoffood.menu.viewmodel.AddMenuViewModel
import java.util.*

class AddEditMenuActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityAddEditMenuBinding
    private lateinit var viewModel: AddMenuViewModel
    private var currentMenuItem: MenuItem? = null
    private var selectedImageUrl: String? = null
    private var selectedImageUri: Uri? = null
    
    companion object {
        private const val TAG = "AddEditMenuActivity"
        const val EXTRA_MENU_ITEM = "extra_menu_item"
        const val EXTRA_IS_EDIT_MODE = "extra_is_edit_mode"
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        try {
            binding = ActivityAddEditMenuBinding.inflate(layoutInflater)
            setContentView(binding.root)
            
            // Initialize ViewModel safely
            viewModel = ViewModelProvider(this)[AddMenuViewModel::class.java]
            
            setupUI()
            Log.d(TAG, "Activity created successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Error creating activity", e)
            Toast.makeText(this, "Error opening menu editor", Toast.LENGTH_SHORT).show()
            finish()
        }
    }
    
    private fun setupUI() {
        try {
            setupToolbar()
            setupCategorySpinner()
            setupImageUrlOnly()
            setupClickListeners()
            setupObservers()
            loadMenuData()
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up UI", e)
            Toast.makeText(this, "Error setting up interface", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun setupToolbar() {
        try {
            setSupportActionBar(binding.toolbar)
            supportActionBar?.apply {
                title = if (isEditMode()) "Edit Menu" else "Add Menu"
                setDisplayHomeAsUpEnabled(true)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up toolbar", e)
        }
    }
    
    private fun setupCategorySpinner() {
        try {
            val categories = DefaultMenuCategories.getCategoryDisplayNames()
            val adapter = ArrayAdapter(this, android.R.layout.simple_dropdown_item_1line, categories)
            binding.spinnerCategory.setAdapter(adapter)
            
            if (categories.isNotEmpty()) {
                binding.spinnerCategory.setText(categories[0], false)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up category spinner", e)
        }
    }
    
    private fun setupImageUrlOnly() {
        try {
            binding.etImageUrl.addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
                
                override fun afterTextChanged(s: Editable?) {
                    try {
                        val url = s.toString().trim()
                        if (url.isNotEmpty() && Patterns.WEB_URL.matcher(url).matches()) {
                            viewModel.setImageUrl(url)
                            loadImageFromUrl(url)
                        } else if (url.isEmpty()) {
                            viewModel.clearImageUrl()
                            clearImagePreview()
                        }
                    } catch (e: Exception) {
                        Log.e(TAG, "Error processing URL input", e)
                    }
                }
            })
            
            binding.cardImagePreview.visibility = View.GONE
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up image URL", e)
        }
    }
    
    private fun setupClickListeners() {
        try {
            binding.btnSave.setOnClickListener { saveMenu() }
            
            // URL-only actions
            binding.btnRemoveImageUrl.setOnClickListener {
                try {
                    binding.etImageUrl.setText("")
                    selectedImageUrl = null
                    viewModel.clearImageUrl()
                    clearImagePreview()
                } catch (_: Exception) {}
            }
            
            binding.btnChangeImageUrl.setOnClickListener {
                showChangeImageUrlDialog()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up click listeners", e)
        }
    }
    
    private fun showChangeImageUrlDialog() {
        try {
            val dialogView = layoutInflater.inflate(com.christopheraldoo.adminwafeoffood.R.layout.dialog_image_url_input, null)
            val til = dialogView.findViewById<com.google.android.material.textfield.TextInputLayout>(com.christopheraldoo.adminwafeoffood.R.id.textInputLayoutUrl)
            val et = dialogView.findViewById<com.google.android.material.textfield.TextInputEditText>(com.christopheraldoo.adminwafeoffood.R.id.editTextImageUrl)
            val ivPreview = dialogView.findViewById<android.widget.ImageView>(com.christopheraldoo.adminwafeoffood.R.id.imageViewPreview)
            val tvStatus = dialogView.findViewById<android.widget.TextView>(com.christopheraldoo.adminwafeoffood.R.id.textViewPreviewStatus)
            
            et.setText(binding.etImageUrl.text?.toString() ?: "")
            
            val alert = com.google.android.material.dialog.MaterialAlertDialogBuilder(this)
                .setView(dialogView)
                .setPositiveButton("SIMPAN", null)
                .setNegativeButton("BATAL", null)
                .create()
            
            // Live preview when user types
            et.addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
                override fun afterTextChanged(s: Editable?) {
                    val url = s?.toString()?.trim().orEmpty()
                    if (url.isNotEmpty() && Patterns.WEB_URL.matcher(url).matches()) {
                        ivPreview.visibility = View.VISIBLE
                        tvStatus.visibility = View.VISIBLE
                        tvStatus.text = "Preview"
                        Glide.with(this@AddEditMenuActivity)
                            .load(url)
                            .placeholder(android.R.drawable.ic_menu_gallery)
                            .error(android.R.drawable.ic_menu_gallery)
                            .into(ivPreview)
                    } else {
                        ivPreview.visibility = View.GONE
                        tvStatus.visibility = View.GONE
                    }
                }
            })
            
            alert.setOnShowListener {
                val btnSave = alert.getButton(android.app.AlertDialog.BUTTON_POSITIVE)
                btnSave.setOnClickListener {
                    val url = et.text?.toString()?.trim().orEmpty()
                    if (url.isEmpty() || !Patterns.WEB_URL.matcher(url).matches()) {
                        til.error = "URL tidak valid"
                        return@setOnClickListener
                    }
                    til.error = null
                    binding.etImageUrl.setText(url)
                    loadImageFromUrl(url)
                    alert.dismiss()
                }
            }
            
            alert.show()
        } catch (e: Exception) {
            Log.e(TAG, "Error showing change image URL dialog", e)
        }
    }
    
    private fun loadImageFromUrl(url: String) {
        try {
            selectedImageUrl = url
            selectedImageUri = null
            
            viewModel.setImageUrl(url)
            
            Glide.with(this)
                .load(url)
                .placeholder(android.R.drawable.ic_menu_gallery)
                .error(android.R.drawable.ic_menu_gallery)
                .into(binding.ivPreview)
                
            binding.cardImagePreview.visibility = View.VISIBLE
        } catch (e: Exception) {
            Log.e(TAG, "Error loading image from URL", e)
            Toast.makeText(this, "Error loading image from URL", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun clearImagePreview() {
        try {
            binding.ivPreview.setImageDrawable(null)
            binding.cardImagePreview.visibility = View.GONE
        } catch (e: Exception) {
            Log.e(TAG, "Error clearing image preview", e)
        }
    }
    
    private fun loadMenuData() {
        try {
            if (isEditMode()) {
                currentMenuItem = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    intent.getParcelableExtra(EXTRA_MENU_ITEM, MenuItem::class.java)
                } else {
                    @Suppress("DEPRECATION")
                    intent.getParcelableExtra<MenuItem>(EXTRA_MENU_ITEM)
                }
                
                currentMenuItem?.let { menu ->
                    binding.etMenuName.setText(menu.name)
                    binding.etMenuDescription.setText(menu.description)
                    binding.etMenuPrice.setText(menu.price.toString())
                    binding.switchAvailable.isChecked = menu.isAvailable
                    
                    val categories = DefaultMenuCategories.getCategories()
                    val categoryDisplayNames = DefaultMenuCategories.getCategoryDisplayNames()
                    val categoryIndex = categories.indexOfFirst { it.name == menu.category }
                    if (categoryIndex >= 0 && categoryIndex < categoryDisplayNames.size) {
                        binding.spinnerCategory.setText(categoryDisplayNames[categoryIndex], false)
                    }
                    
                    if (menu.imageURL.isNotEmpty()) {
                        binding.etImageUrl.setText(menu.imageURL)
                        loadImageFromUrl(menu.imageURL)
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error loading menu data", e)
            Toast.makeText(this, "Error loading menu data", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun saveMenu() {
        try {
            val menuName = binding.etMenuName.text.toString().trim()
            val menuDescription = binding.etMenuDescription.text.toString().trim()
            val menuPriceText = binding.etMenuPrice.text.toString().trim()
            val isAvailable = binding.switchAvailable.isChecked
            
            val selectedCategoryText = binding.spinnerCategory.text.toString()
            val categories = DefaultMenuCategories.getCategories()
            val categoryDisplayNames = DefaultMenuCategories.getCategoryDisplayNames()
            val categoryIndex = categoryDisplayNames.indexOf(selectedCategoryText)
            val category = if (categoryIndex >= 0 && categoryIndex < categories.size) {
                categories[categoryIndex].name
            } else {
                "main_course"
            }
            
            val finalImageUrl = viewModel.imageUrl.value ?: selectedImageUrl ?: ""
            
            val existingMenuId = if (isEditMode()) {
                currentMenuItem?.id ?: ""
            } else {
                ""
            }
            
            viewModel.saveMenu(
                name = menuName,
                description = menuDescription,
                priceText = menuPriceText,
                category = category,
                imageUrl = finalImageUrl,
                isAvailable = isAvailable,
                adminId = "admin_001",
                isEditMode = isEditMode(),
                existingMenuId = existingMenuId
            )
            
            Log.d(TAG, "Save menu initiated: $menuName")
            
        } catch (e: Exception) {
            Log.e(TAG, "Error saving menu", e)
            Toast.makeText(this, "Error saving menu: ${e.message}", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun isEditMode(): Boolean {
        return intent.getBooleanExtra(EXTRA_IS_EDIT_MODE, false)
    }
    
    override fun onSupportNavigateUp(): Boolean {
        finish()
        return true
    }
    
    private fun setupObservers() {
        try {
            viewModel.saveState.observe(this) { state ->
                try {
                    when (state) {
                        is AddMenuViewModel.SaveState.Idle -> {
                            binding.btnSave.isEnabled = true
                            binding.btnSave.text = "💾 SIMPAN MENU"
                            binding.progressBar.visibility = View.GONE
                        }
                        is AddMenuViewModel.SaveState.Loading -> {
                            binding.btnSave.isEnabled = false
                            binding.btnSave.text = "⏳ Menyimpan..."
                            binding.progressBar.visibility = View.VISIBLE
                        }
                        is AddMenuViewModel.SaveState.Success -> {
                            binding.btnSave.isEnabled = true
                            binding.btnSave.text = "💾 SIMPAN MENU"
                            binding.progressBar.visibility = View.GONE
                            Toast.makeText(this, "✅ ${state.message}", Toast.LENGTH_SHORT).show()
                            setResult(Activity.RESULT_OK)
                            finish()
                        }
                        is AddMenuViewModel.SaveState.Error -> {
                            binding.btnSave.isEnabled = true
                            binding.btnSave.text = "💾 SIMPAN MENU"
                            binding.progressBar.visibility = View.GONE
                            Toast.makeText(this, "❌ ${state.message}", Toast.LENGTH_SHORT).show()
                        }
                    }
                } catch (e: Exception) {}
            }
            
            viewModel.validationError.observe(this) { error ->
                error?.let {
                    Toast.makeText(this, "⚠️ $it", Toast.LENGTH_SHORT).show()
                }
            }
            
            viewModel.imageUrl.observe(this) { url ->
                if (url.isNotEmpty()) {
                    selectedImageUrl = url
                }
            }
        } catch (e: Exception) {}
    }
}
