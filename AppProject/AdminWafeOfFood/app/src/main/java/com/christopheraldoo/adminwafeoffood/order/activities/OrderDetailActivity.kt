package com.christopheraldoo.adminwafeoffood.order.activities

import android.graphics.Color
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.widget.TextView
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.databinding.ActivityOrderDetailBinding
import com.christopheraldoo.adminwafeoffood.order.model.Order
import com.christopheraldoo.adminwafeoffood.order.model.OrderItem
import com.christopheraldoo.adminwafeoffood.order.viewmodel.OrderViewModel
import kotlinx.coroutines.launch

class OrderDetailActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityOrderDetailBinding
    private val viewModel: OrderViewModel by viewModels()
    
    companion object {
        private const val TAG = "OrderDetailActivity"
        const val EXTRA_ORDER_ID = "extra_order_id"
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityOrderDetailBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        setupToolbar()
        
        val orderId = intent.getStringExtra(EXTRA_ORDER_ID)
        if (orderId != null) {
            loadOrderDetail(orderId)
        } else {
            Toast.makeText(this, "Order ID not found", Toast.LENGTH_SHORT).show()
            finish()
        }
    }
    
    private fun setupToolbar() {
        try {
            supportActionBar?.apply {
                title = "Order Detail"
                setDisplayHomeAsUpEnabled(true)
                setDisplayShowHomeEnabled(true)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error setting up toolbar", e)
        }
    }
    
    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }
    
    private fun loadOrderDetail(orderId: String) {
        lifecycleScope.launch {
            try {
                Log.d(TAG, "Loading order detail for ID: $orderId")
                
                // Get order from ViewModel
                val order = viewModel.getOrderById(orderId)
                
                if (order != null) {
                    displayOrderDetail(order)
                } else {
                    Toast.makeText(this@OrderDetailActivity, "Order not found", Toast.LENGTH_SHORT).show()
                    finish()
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error loading order detail", e)
                Toast.makeText(this@OrderDetailActivity, "Error loading order detail", Toast.LENGTH_SHORT).show()
                finish()
            }
        }
    }
    
    private fun displayOrderDetail(order: Order) {
        try {
            binding.apply {
                // Header Information
                tvOrderId.text = "#${order.id.take(8)}"
                tvOrderStatus.text = order.getStatusDisplayName()
                tvOrderDate.text = order.getFormattedCreatedDate()
                
                // Status color
                try {
                    val statusColor = Color.parseColor(order.getStatusColor())
                    tvOrderStatus.setTextColor(statusColor)
                    cardStatus.setCardBackgroundColor(Color.parseColor(order.getStatusColor() + "20"))
                } catch (e: Exception) {
                    Log.w(TAG, "Error setting status color", e)
                }
                
                // Customer Information
                tvCustomerName.text = order.customerName
                tvCustomerEmail.text = order.customerEmail.ifEmpty { "Not provided" }
                tvCustomerPhone.text = order.customerPhone
                
                // Delivery Information
                val deliveryMethod = if (order.customerAddress.isNotEmpty()) "Delivery" else "Dine In"
                tvDeliveryMethod.text = deliveryMethod
                
                if (deliveryMethod == "Delivery") {
                    layoutDeliveryAddress.visibility = View.VISIBLE
                    tvDeliveryAddress.text = order.customerAddress
                } else {
                    layoutDeliveryAddress.visibility = View.GONE
                }
                
                // Payment Information
                tvPaymentMethod.text = order.paymentMethod.ifEmpty { "Not specified" }
                
                // Order Items
                displayOrderItems(order.items)
                
                // Total Amount
                tvTotalAmount.text = order.getFormattedTotal()
            }
            
            Log.d(TAG, "Order detail displayed successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Error displaying order detail", e)
            Toast.makeText(this, "Error displaying order detail", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun displayOrderItems(items: List<OrderItem>) {
        try {
            binding.layoutOrderItems.removeAllViews()
            
            items.forEach { item ->
                val itemView = LayoutInflater.from(this).inflate(
                    R.layout.item_order_menu, 
                    binding.layoutOrderItems, 
                    false
                )
                
                // Bind item data
                itemView.findViewById<TextView>(R.id.tvMenuName).text = item.menuName
                itemView.findViewById<TextView>(R.id.tvMenuPrice).text = "Rp ${String.format("%,.0f", item.menuPrice)}"
                itemView.findViewById<TextView>(R.id.tvQuantity).text = "x${item.quantity}"
                itemView.findViewById<TextView>(R.id.tvSubtotal).text = "Rp ${String.format("%,.0f", item.subtotal)}"
                
                // Handle notes
                val notesView = itemView.findViewById<TextView>(R.id.tvMenuNotes)
                if (item.notes.isNotEmpty()) {
                    notesView.text = "Note: ${item.notes}"
                    notesView.visibility = View.VISIBLE
                } else {
                    notesView.visibility = View.GONE
                }
                
                binding.layoutOrderItems.addView(itemView)
                
                // Add divider (except for last item)
                if (item != items.last()) {
                    val divider = View(this)
                    divider.layoutParams = android.widget.LinearLayout.LayoutParams(
                        android.widget.LinearLayout.LayoutParams.MATCH_PARENT,
                        1
                    )
                    divider.setBackgroundColor(Color.parseColor("#E0E0E0"))
                    binding.layoutOrderItems.addView(divider)
                }
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error displaying order items", e)
        }
    }
}
