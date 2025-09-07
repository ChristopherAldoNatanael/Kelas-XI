package com.christopheraldoo.wavesoffood.ui.orders

import android.animation.ObjectAnimator
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.animation.AccelerateDecelerateInterpolator
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
class OrdersAdapter(
    private val onOrderClick: (Order) -> Unit,
    private val onCancelClick: (Order) -> Unit,
    private val onReorderClick: (Order) -> Unit,
    private val onTrackClick: (Order) -> Unit
) : ListAdapter<Order, OrdersAdapter.OrderViewHolder>(OrderDiffCallback()) {
    
    inner class OrderViewHolder(
        private val binding: ItemOrderBinding
    ) : RecyclerView.ViewHolder(binding.root) {
        
        fun bind(order: Order) {
            with(binding) {
                // Order info
                tvOrderId.text = "Order #${order.id.take(8).uppercase()}"
                tvOrderDate.text = order.getFormattedCreatedTime()
                tvOrderTotal.text = order.getFormattedTotal()
                tvItemCount.text = "${order.items.size} items"
                tvRecipientName.text = order.recipientName
                
                // Status
                tvOrderStatus.text = getStatusDisplayName(order.status)
                tvOrderStatus.setTextColor(ContextCompat.getColor(root.context, getStatusColor(order.status)))
                
                // Status progress indicator
                progressStatus.progress = getStatusProgress(order.status)
                progressStatus.progressTintList = ContextCompat.getColorStateList(root.context, getStatusColor(order.status))
                
                // Order type and payment method
                tvOrderType.text = when (order.orderType) {
                    com.christopheraldoo.wavesoffood.data.model.OrderType.TAKE_AWAY -> "Take Away"
                    com.christopheraldoo.wavesoffood.data.model.OrderType.DINE_IN -> "Dine In"
                }
                tvPaymentMethod.text = when (order.paymentMethod) {
                    com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CASH -> "Cash"
                    com.christopheraldoo.wavesoffood.data.model.PaymentMethod.QRIS -> "QRIS"
                    com.christopheraldoo.wavesoffood.data.model.PaymentMethod.BANK_TRANSFER -> "Bank Transfer"
                    com.christopheraldoo.wavesoffood.data.model.PaymentMethod.CREDIT_CARD -> "Credit Card"
                }
                
                // First item image (preview)
                if (order.items.isNotEmpty()) {
                    val firstItem = order.items[0]
                    tvFirstItemName.text = firstItem.menuName
                    
                    val requestOptions = RequestOptions()
                        .transform(CenterCrop(), RoundedCorners(12))
                        .placeholder(android.R.drawable.ic_menu_gallery)
                        .error(android.R.drawable.ic_menu_gallery)
                    
                    Glide.with(root.context)
                        .load(firstItem.menuImageUrl)
                        .apply(requestOptions)
                        .into(ivFirstItemImage)
                    
                    // Show additional items count if more than 1
                    if (order.items.size > 1) {
                        tvAdditionalItems.visibility = View.VISIBLE
                        tvAdditionalItems.text = "+${order.items.size - 1} more"
                    } else {
                        tvAdditionalItems.visibility = View.GONE
                    }
                }
                
                // Button states based on order status
                setupButtonsForStatus(order)
                
                // Click listeners
                root.setOnClickListener {
                    Log.d("OrdersAdapter", "Order clicked: ${order.id}")
                    animateItemClick {
                        onOrderClick(order)
                    }
                }
                
                btnCancel.setOnClickListener {
                    animateButtonClick(it) {
                        onCancelClick(order)
                    }
                }
                
                btnReorder.setOnClickListener {
                    animateButtonClick(it) {
                        onReorderClick(order)
                    }
                }
                
                btnTrackOrder.setOnClickListener {
                    animateButtonClick(it) {
                        onTrackClick(order)
                    }
                }
                
                // Animate item entry
                animateItemEntry()
            }
        }
        
        private fun setupButtonsForStatus(order: Order) {
            with(binding) {
                when (order.status) {
                    OrderStatus.PENDING, OrderStatus.CONFIRMED -> {
                        btnCancel.visibility = View.VISIBLE
                        btnTrackOrder.visibility = View.VISIBLE
                        btnReorder.visibility = View.GONE
                        
                        btnCancel.isEnabled = true
                        btnTrackOrder.isEnabled = true
                    }
                    OrderStatus.PREPARING, OrderStatus.READY, OrderStatus.DELIVERING -> {
                        btnCancel.visibility = View.GONE
                        btnTrackOrder.visibility = View.VISIBLE
                        btnReorder.visibility = View.GONE
                        
                        btnTrackOrder.isEnabled = true
                    }
                    OrderStatus.COMPLETED -> {
                        btnCancel.visibility = View.GONE
                        btnTrackOrder.visibility = View.GONE
                        btnReorder.visibility = View.VISIBLE
                        
                        btnReorder.isEnabled = true
                    }
                    OrderStatus.CANCELLED -> {
                        btnCancel.visibility = View.GONE
                        btnTrackOrder.visibility = View.GONE
                        btnReorder.visibility = View.VISIBLE
                        
                        btnReorder.isEnabled = true
                    }
                }
            }
        }
        
        private fun getStatusDisplayName(status: OrderStatus): String {
            return when (status) {
                OrderStatus.PENDING -> "Pending"
                OrderStatus.CONFIRMED -> "Confirmed"
                OrderStatus.PREPARING -> "Preparing"
                OrderStatus.READY -> "Ready for Pickup"
                OrderStatus.DELIVERING -> "On the Way"
                OrderStatus.COMPLETED -> "Completed"
                OrderStatus.CANCELLED -> "Cancelled"
            }
        }
        
        private fun getStatusColor(status: OrderStatus): Int {
            return when (status) {
                OrderStatus.PENDING -> R.color.status_pending
                OrderStatus.CONFIRMED -> R.color.status_confirmed
                OrderStatus.PREPARING -> R.color.status_preparing
                OrderStatus.READY -> R.color.status_ready
                OrderStatus.DELIVERING -> R.color.status_delivering
                OrderStatus.COMPLETED -> R.color.status_completed
                OrderStatus.CANCELLED -> R.color.status_cancelled
            }
        }
        
        private fun getStatusProgress(status: OrderStatus): Int {
            return when (status) {
                OrderStatus.PENDING -> 10
                OrderStatus.CONFIRMED -> 25
                OrderStatus.PREPARING -> 50
                OrderStatus.READY -> 75
                OrderStatus.DELIVERING -> 90
                OrderStatus.COMPLETED -> 100
                OrderStatus.CANCELLED -> 0
            }
        }
        
        private fun animateItemEntry() {
            binding.root.apply {
                alpha = 0f
                translationY = 50f
                animate()
                    .alpha(1f)
                    .translationY(0f)
                    .setDuration(300)
                    .setInterpolator(AccelerateDecelerateInterpolator())
                    .start()
            }
        }
        
        private fun animateItemClick(onAnimationEnd: () -> Unit) {
            val animator = ObjectAnimator.ofFloat(binding.root, "scaleX", 1f, 0.98f, 1f)
            animator.duration = 150
            animator.interpolator = AccelerateDecelerateInterpolator()
            
            val animator2 = ObjectAnimator.ofFloat(binding.root, "scaleY", 1f, 0.98f, 1f)
            animator2.duration = 150
            animator2.interpolator = AccelerateDecelerateInterpolator()
            
            animator.start()
            animator2.start()
            
            binding.root.postDelayed({
                onAnimationEnd()
            }, 150)
        }
        
        private fun animateButtonClick(view: View, onAnimationEnd: () -> Unit) {
            view.animate()
                .scaleX(0.9f)
                .scaleY(0.9f)
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

class OrderDiffCallback : DiffUtil.ItemCallback<Order>() {
    override fun areItemsTheSame(oldItem: Order, newItem: Order): Boolean {
        return oldItem.id == newItem.id
    }
    
    override fun areContentsTheSame(oldItem: Order, newItem: Order): Boolean {
        return oldItem == newItem
    }
}
