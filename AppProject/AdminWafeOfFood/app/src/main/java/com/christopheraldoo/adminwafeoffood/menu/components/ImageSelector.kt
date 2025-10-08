package com.christopheraldoo.adminwafeoffood.menu.components

import android.content.ClipboardManager
import android.content.Context
import android.util.AttributeSet
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.ImageView
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.christopheraldoo.adminwafeoffood.R

/**
 * Simple Image Selector Component
 */
class ImageSelector @JvmOverloads constructor(
    context: Context,
    attrs: AttributeSet? = null,
    defStyleAttr: Int = 0
) : LinearLayout(context, attrs, defStyleAttr) {

    private var selectedImageUri: String? = null
    private var onImageSelectedListener: ((String) -> Unit)? = null

    init {
        LayoutInflater.from(context).inflate(R.layout.component_image_selector, this, true)
        setupClickListeners()
    }

    private fun setupClickListeners() {
        findViewById<View>(R.id.btnSelectGallery)?.setOnClickListener {
            // Handle gallery selection
        }
        
        findViewById<View>(R.id.btnEnterUrl)?.setOnClickListener {
            // Show URL input section
            findViewById<View>(R.id.urlInputSection)?.visibility = View.VISIBLE
        }
        
        findViewById<View>(R.id.btnLoadUrl)?.setOnClickListener {
            val urlInput = findViewById<View>(R.id.urlEditText) as? android.widget.EditText
            val url = urlInput?.text?.toString()?.trim()
            if (!url.isNullOrEmpty()) {
                loadImageFromUrl(url)
            }
        }
        
        findViewById<View>(R.id.btnPasteUrl)?.setOnClickListener {
            pasteFromClipboard()
        }
    }

    private fun pasteFromClipboard() {
        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as? ClipboardManager
        val clipData = clipboard?.primaryClip
        
        if (clipData != null && clipData.itemCount > 0) {
            val clipText = clipData.getItemAt(0).text?.toString()
            if (!clipText.isNullOrEmpty()) {
                val urlInput = findViewById<View>(R.id.urlEditText) as? android.widget.EditText
                urlInput?.setText(clipText)
            }
        }
    }

    private fun loadImageFromUrl(url: String) {
        // Show loading
        findViewById<View>(R.id.loadingOverlay)?.visibility = View.VISIBLE
        
        // Load image with Glide
        val imageView = findViewById<ImageView>(R.id.selectedImageView)
        imageView?.let { iv ->
            Glide.with(context)
                .load(url)
                .placeholder(R.drawable.ic_image_placeholder)
                .error(R.drawable.ic_image_error)
                .into(iv)
        }
        
        // Hide placeholder, show image
        findViewById<View>(R.id.placeholderContainer)?.visibility = View.GONE
        imageView?.visibility = View.VISIBLE
        findViewById<View>(R.id.imageActionsOverlay)?.visibility = View.VISIBLE
        
        // Hide loading
        findViewById<View>(R.id.loadingOverlay)?.visibility = View.GONE
        
        selectedImageUri = url
        onImageSelectedListener?.invoke(url)
    }

    // Public methods
    fun setSelectedImageUrl(url: String) {
        if (url.isNotEmpty()) {
            loadImageFromUrl(url)
        }
    }

    fun getSelectedImageUrl(): String? = selectedImageUri

    fun setOnImageSelectedListener(listener: (String) -> Unit) {
        this.onImageSelectedListener = listener
    }

    fun setActivityResultLauncher(launcher: Any) {
        // For handling activity results if needed
    }

    fun handleImageResult(data: Any): Boolean {
        // Handle image result
        return true
    }
}
