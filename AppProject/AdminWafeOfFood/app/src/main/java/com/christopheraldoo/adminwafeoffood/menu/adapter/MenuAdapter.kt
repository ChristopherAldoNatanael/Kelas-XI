package com.christopheraldoo.adminwafeoffood.menu.adapter

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.databinding.ItemMenuBinding
import com.christopheraldoo.adminwafeoffood.menu.model.MenuItem

class MenuAdapter(
    private val onItemClick: (MenuItem) -> Unit,
    private val onEditClick: (MenuItem) -> Unit,
    private val onDeleteClick: (MenuItem) -> Unit,
    private val onAvailabilityToggle: (MenuItem, Boolean) -> Unit
) : ListAdapter<MenuItem, MenuAdapter.MenuViewHolder>(MenuDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MenuViewHolder {
        val binding = ItemMenuBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return MenuViewHolder(binding)
    }

    override fun onBindViewHolder(holder: MenuViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class MenuViewHolder(
        private val binding: ItemMenuBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(menuItem: MenuItem) {
            binding.apply {
                // Menu info
                tvMenuName.text = menuItem.name
                tvMenuDescription.text = menuItem.description
                tvMenuPrice.text = "Rp ${String.format("%,.0f", menuItem.price)}"
                tvMenuCategory.text = menuItem.category

                // Load image
                if (menuItem.imageUrl.isNotEmpty()) {
                    Glide.with(itemView.context)
                        .load(menuItem.imageUrl)
                        .placeholder(android.R.drawable.ic_menu_gallery)
                        .error(android.R.drawable.ic_menu_gallery)
                        .into(ivMenuImage)
                } else {
                    ivMenuImage.setImageResource(android.R.drawable.ic_menu_gallery)
                }

                // Availability status
                switchAvailable.isChecked = menuItem.isAvailable
                tvAvailabilityStatus.text = if (menuItem.isAvailable) "Available" else "Not Available"

                // Click listeners
                root.setOnClickListener { onItemClick(menuItem) }
                btnEdit.setOnClickListener { onEditClick(menuItem) }
                btnDelete.setOnClickListener { onDeleteClick(menuItem) }
                
                switchAvailable.setOnCheckedChangeListener { _, isChecked ->
                    onAvailabilityToggle(menuItem, isChecked)
                }
            }
        }
    }

    class MenuDiffCallback : DiffUtil.ItemCallback<MenuItem>() {
        override fun areItemsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem == newItem
        }
    }
}