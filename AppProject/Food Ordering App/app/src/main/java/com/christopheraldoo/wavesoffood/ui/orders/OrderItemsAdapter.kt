package com.christopheraldoo.wavesoffood.ui.orders

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderItem
import com.christopheraldoo.wavesoffood.databinding.ItemOrderDetailBinding
import java.text.NumberFormat
import java.util.Locale

class OrderItemsAdapter : ListAdapter<OrderItem, OrderItemsAdapter.OrderItemViewHolder>(DiffCallback) {
    
    companion object DiffCallback : DiffUtil.ItemCallback<OrderItem>() {
        override fun areItemsTheSame(oldItem: OrderItem, newItem: OrderItem): Boolean {
            return oldItem.menuId == newItem.menuId
        }

        override fun areContentsTheSame(oldItem: OrderItem, newItem: OrderItem): Boolean {
            return oldItem == newItem
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderItemViewHolder {
        return OrderItemViewHolder(
            ItemOrderDetailBinding.inflate(
                LayoutInflater.from(parent.context),
                parent,
                false
            )
        )
    }

    override fun onBindViewHolder(holder: OrderItemViewHolder, position: Int) {
        holder.bind(getItem(position))
    }    class OrderItemViewHolder(
        private val binding: ItemOrderDetailBinding
    ) : RecyclerView.ViewHolder(binding.root) {
        
        fun bind(item: OrderItem) {
            binding.apply {
                // Load item image
                Glide.with(itemView.context)
                    .load(item.menuImageUrl)
                    .placeholder(android.R.drawable.ic_menu_gallery)
                    .error(android.R.drawable.ic_menu_gallery)
                    .into(ivMenuImage)

                // Set item details
                tvMenuName.text = item.menuName
                tvQuantity.text = "${item.quantity}x"
                
                // Format and display prices
                val formatter = NumberFormat.getInstance(Locale("id", "ID"))
                val unitPrice = "Rp ${formatter.format(item.price)} each"
                tvUnitPrice.text = unitPrice
                
                tvItemTotal.text = "Rp ${formatter.format(item.totalPrice)}"
                
                // Show notes if available
                if (item.notes.isNotEmpty()) {
                    tvSpecialInstructions.text = item.notes
                    tvSpecialInstructions.visibility = android.view.View.VISIBLE
                } else {
                    tvSpecialInstructions.visibility = android.view.View.GONE
                }
            }
        }
    }
}
