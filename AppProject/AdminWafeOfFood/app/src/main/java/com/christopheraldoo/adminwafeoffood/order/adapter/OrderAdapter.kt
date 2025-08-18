package com.christopheraldoo.adminwafeoffood.order.adapter

import android.graphics.Color
import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.adminwafeoffood.databinding.ItemOrderBinding
import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderStatus

class OrderAdapter(
    private val onItemClick: (Order) -> Unit,
    private val onStatusUpdate: (Order, OrderStatus) -> Unit,
    private val onDeleteOrder: (Order) -> Unit
) : ListAdapter<Order, OrderAdapter.OrderViewHolder>(OrderDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderViewHolder {
        val binding = ItemOrderBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return OrderViewHolder(binding)
    }

    override fun onBindViewHolder(holder: OrderViewHolder, position: Int) {
        try {
            val order = getItem(position)
            holder.bind(order)
        } catch (e: Exception) {
            // Skip item if error
        }
    }

    inner class OrderViewHolder(private val binding: ItemOrderBinding) : 
        RecyclerView.ViewHolder(binding.root) {
        
        fun bind(order: Order) {
            try {
                binding.apply {
                    // Basic info
                    tvOrderId.text = "#${order.id.take(8)}"
                    tvCustomerName.text = order.customerName
                    tvCustomerPhone.text = order.customerPhone
                    tvOrderDate.text = order.getFormattedCreatedDate()
                    tvTotalAmount.text = order.getFormattedTotal()
                    
                    // Items summary
                    val itemCount = order.items.sumOf { it.quantity }
                    tvItemsCount.text = "$itemCount items"
                    
                    // Status
                    tvOrderStatus.text = order.getStatusDisplayName()
                    
                    // Status color
                    try {
                        val statusColor = Color.parseColor(order.getStatusColor())
                        tvOrderStatus.setTextColor(statusColor)
                        cardStatus.setCardBackgroundColor(Color.parseColor(order.getStatusColor() + "20")) // 20% opacity
                    } catch (e: Exception) {
                        // Use default colors if parsing fails
                    }
                    
                    // Click listeners
                    root.setOnClickListener { 
                        try {
                            onItemClick(order)
                        } catch (e: Exception) {
                            // Handle click error
                        }
                    }
                    
                    // Status action buttons
                    setupStatusButtons(order)
                }
            } catch (e: Exception) {
                // Handle binding error
            }
        }
          private fun setupStatusButtons(order: Order) {
            binding.apply {
                // Reset button visibility
                btnAccept.visibility = android.view.View.GONE
                btnReject.visibility = android.view.View.GONE
                btnInProgress.visibility = android.view.View.GONE
                btnReady.visibility = android.view.View.GONE
                btnComplete.visibility = android.view.View.GONE
                btnDelete.visibility = android.view.View.GONE
                
                when (order.status) {
                    OrderStatus.INCOMING -> {
                        btnAccept.visibility = android.view.View.VISIBLE
                        btnReject.visibility = android.view.View.VISIBLE
                        
                        btnAccept.setOnClickListener {
                            onStatusUpdate(order, OrderStatus.CONFIRMED)
                        }
                        btnReject.setOnClickListener {
                            onStatusUpdate(order, OrderStatus.CANCELLED)
                        }
                    }
                    OrderStatus.CONFIRMED -> {
                        btnInProgress.visibility = android.view.View.VISIBLE
                        
                        btnInProgress.setOnClickListener {
                            onStatusUpdate(order, OrderStatus.IN_PROGRESS)
                        }
                    }
                    OrderStatus.IN_PROGRESS -> {
                        btnReady.visibility = android.view.View.VISIBLE
                        
                        btnReady.setOnClickListener {
                            onStatusUpdate(order, OrderStatus.READY)
                        }
                    }
                    OrderStatus.READY -> {
                        btnComplete.visibility = android.view.View.VISIBLE
                        
                        btnComplete.setOnClickListener {
                            onStatusUpdate(order, OrderStatus.COMPLETED)
                        }
                    }
                    OrderStatus.COMPLETED -> {
                        // No action buttons for completed orders
                    }
                    OrderStatus.CANCELLED -> {
                        // Show delete button for cancelled orders
                        btnDelete.visibility = android.view.View.VISIBLE
                        
                        btnDelete.setOnClickListener {
                            onDeleteOrder(order)
                        }
                    }
                }
            }
        }
    }

    class OrderDiffCallback : DiffUtil.ItemCallback<Order>() {
        override fun areItemsTheSame(oldItem: Order, newItem: Order): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Order, newItem: Order): Boolean {
            return oldItem == newItem
        }
    }
}