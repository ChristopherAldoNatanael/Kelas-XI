package com.christopheraldoo.wavesoffood.ui.cart

import android.animation.ObjectAnimator
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.animation.AccelerateDecelerateInterpolator
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.bumptech.glide.load.resource.bitmap.CenterCrop
import com.bumptech.glide.load.resource.bitmap.RoundedCorners
import com.bumptech.glide.request.RequestOptions
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.ItemCartBinding

class CartAdapter(
    private val onQuantityChanged: (CartItem, Int) -> Unit,
    private val onItemRemoved: (CartItem) -> Unit
) : ListAdapter<CartItem, CartAdapter.CartViewHolder>(CartDiffCallback()) {

    inner class CartViewHolder(
        private val binding: ItemCartBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(cartItem: CartItem) {
            with(binding) {
                // Set data
                tvItemName.text = cartItem.menuName
                tvItemPrice.text = cartItem.getFormattedPrice()
                tvQuantity.text = cartItem.quantity.toString()
                tvTotalPrice.text = cartItem.getFormattedTotalPrice()

                // Load image with Glide
                val requestOptions = RequestOptions()
                    .transform(CenterCrop(), RoundedCorners(16))
                    .placeholder(android.R.drawable.ic_menu_gallery)
                    .error(android.R.drawable.ic_menu_gallery)

                Glide.with(root.context)
                    .load(cartItem.menuImageUrl)
                    .apply(requestOptions)
                    .into(ivItemImage)

                // Quantity controls
                btnDecrease.setOnClickListener {
                    if (cartItem.quantity > 1) {
                        animateButtonClick(it)
                        onQuantityChanged(cartItem, cartItem.quantity - 1)
                    }
                }

                btnIncrease.setOnClickListener {
                    animateButtonClick(it)
                    onQuantityChanged(cartItem, cartItem.quantity + 1)
                }

                btnRemove.setOnClickListener {
                    animateRemoveItem(cartItem)
                }

                // Animate item entry
                animateItemEntry()
            }
        }

        private fun animateButtonClick(view: View) {
            ObjectAnimator.ofFloat(view, "scaleX", 1f, 0.8f, 1f).apply {
                duration = 150
                interpolator = AccelerateDecelerateInterpolator()
                start()
            }
            ObjectAnimator.ofFloat(view, "scaleY", 1f, 0.8f, 1f).apply {
                duration = 150
                interpolator = AccelerateDecelerateInterpolator()
                start()
            }
        }

        private fun animateRemoveItem(cartItem: CartItem) {
            ObjectAnimator.ofFloat(itemView, "translationX", 0f, -itemView.width.toFloat()).apply {
                duration = 300
                interpolator = AccelerateDecelerateInterpolator()
                start()
            }
            // Call removal after animation
            itemView.postDelayed({
                onItemRemoved(cartItem)
            }, 300)
        }

        private fun animateItemEntry() {
            itemView.alpha = 0f
            itemView.translationY = 50f
            
            ObjectAnimator.ofFloat(itemView, "alpha", 0f, 1f).apply {
                duration = 300
                start()
            }
            
            ObjectAnimator.ofFloat(itemView, "translationY", 50f, 0f).apply {
                duration = 300
                interpolator = AccelerateDecelerateInterpolator()
                start()
            }
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): CartViewHolder {
        val binding = ItemCartBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return CartViewHolder(binding)
    }

    override fun onBindViewHolder(holder: CartViewHolder, position: Int) {
        holder.bind(getItem(position))
    }
}

class CartDiffCallback : DiffUtil.ItemCallback<CartItem>() {
    override fun areItemsTheSame(oldItem: CartItem, newItem: CartItem): Boolean {
        return oldItem.id == newItem.id
    }

    override fun areContentsTheSame(oldItem: CartItem, newItem: CartItem): Boolean {
        return oldItem == newItem
    }
}
