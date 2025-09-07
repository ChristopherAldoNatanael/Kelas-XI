package com.christopheraldoo.wavesoffood.ui.order

import android.content.res.ColorStateList
import android.graphics.Color
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.bumptech.glide.load.resource.bitmap.CenterCrop
import com.bumptech.glide.load.resource.bitmap.RoundedCorners
import com.bumptech.glide.request.RequestOptions
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.data.model.Order
import com.christopheraldoo.wavesoffood.data.model.OrderStatus
import com.christopheraldoo.wavesoffood.databinding.ItemOrderBinding

/**
 * Adapter untuk menampilkan list order dalam RecyclerView
 */
class OrderAdapter(
    private val onOrderClick: (Order) -> Unit,
    private val onCancelClick: (Order) -> Unit,
    private val onReorderClick: (Order) -> Unit
) : ListAdapter<Order, OrderAdapter.OrderViewHolder>(OrderDiffCallback()) {
    
    inner class OrderViewHolder(
        private val binding: ItemOrderBinding
    ) : RecyclerView.ViewHolder(binding.root) {
        
        fun bind(order: Order) {
            with(binding) {
                // Basic order info
                tvOrderId.text = "#${order.id.take(8).uppercase()}"
                tvOrderDate.text = order.getFormattedCreatedTime()
                tvOrderTotal.text = order.getFormattedTotal()
                tvOrderStatus.text = order.status.name.replace("_", " ")
                tvItemCount.text = "${order.items.size} items"
                tvOrderType.text = order.orderType.name.replace("_", " ")                // Status color
                val statusColorHex = order.getStatusColorHex()
                val statusColor = Color.parseColor(statusColorHex)
                tvOrderStatus.setTextColor(statusColor)
                progressStatus.progressTintList = ColorStateList.valueOf(statusColor)
                
                // Load first item image
                if (order.items.isNotEmpty()) {
                    val firstItem = order.items.first()
                    val requestOptions = RequestOptions()
                        .transform(CenterCrop(), RoundedCorners(12))
                        .placeholder(android.R.drawable.ic_menu_gallery)
                        .error(android.R.drawable.ic_menu_gallery)
                    
                    Glide.with(root.context)
                        .load(firstItem.menuImageUrl)
                        .apply(requestOptions)
                        .into(ivFirstItemImage)
                } else {
                    ivFirstItemImage.setImageResource(android.R.drawable.ic_menu_gallery)
                }
                
                // Show/hide action buttons based on status
                when (order.status) {
                    OrderStatus.PENDING, OrderStatus.CONFIRMED -> {
                        btnCancel.visibility = View.VISIBLE
                        btnReorder.visibility = View.GONE
                    }
                    OrderStatus.COMPLETED -> {
                        btnCancel.visibility = View.GONE
                        btnReorder.visibility = View.VISIBLE
                    }
                    else -> {
                        btnCancel.visibility = View.GONE
                        btnReorder.visibility = View.GONE
                    }
                }
                
                // Click listeners
                root.setOnClickListener {
                    animateClick(it) { onOrderClick(order) }
                }
                
                btnCancel.setOnClickListener {
                    animateClick(it) { onCancelClick(order) }
                }
                
                btnReorder.setOnClickListener {
                    animateClick(it) { onReorderClick(order) }
                }
                
                // Animate item entry
                animateItemEntry()
            }
        }
        
        private fun animateItemEntry() {
            binding.root.apply {
                alpha = 0f
                translationY = 30f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(300)
                    .setStartDelay((bindingAdapterPosition * 50L).coerceAtMost(500))
                    .start()
            }
        }
        
        private fun animateClick(view: View, onClick: () -> Unit) {
            view.animate()
                .scaleX(0.95f)
                .scaleY(0.95f)
                .setDuration(100)
                .withEndAction {
                    view.animate()
                        .scaleX(1f)
                        .scaleY(1f)
                        .setDuration(100)
                        .withEndAction { onClick() }
                        .start()
                }
                .start()
        }
    }
    
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderViewHolder {
        val binding = ItemOrderBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return OrderViewHolder(binding)
    }
    
    override fun onBindViewHolder(holder: OrderViewHolder, position: Int) {
        holder.bind(getItem(position))
    }
}

/**
 * DiffCallback untuk optimasi RecyclerView
 */
class OrderDiffCallback : DiffUtil.ItemCallback<Order>() {
    override fun areItemsTheSame(oldItem: Order, newItem: Order): Boolean {
        return oldItem.id == newItem.id
    }
    
    override fun areContentsTheSame(oldItem: Order, newItem: Order): Boolean {
        return oldItem == newItem
    }
}
