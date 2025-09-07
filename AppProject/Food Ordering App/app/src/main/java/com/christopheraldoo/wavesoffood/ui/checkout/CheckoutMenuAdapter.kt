package com.christopheraldoo.wavesoffood.ui.checkout

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.wavesoffood.databinding.ItemCheckoutMenuBinding
import com.christopheraldoo.wavesoffood.ui.cart.CartItem
import com.bumptech.glide.Glide

class CheckoutMenuAdapter(private val items: List<CartItem>) : RecyclerView.Adapter<CheckoutMenuAdapter.MenuViewHolder>() {
    inner class MenuViewHolder(val binding: ItemCheckoutMenuBinding) : RecyclerView.ViewHolder(binding.root)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MenuViewHolder {
        val binding = ItemCheckoutMenuBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return MenuViewHolder(binding)
    }

    override fun onBindViewHolder(holder: MenuViewHolder, position: Int) {
        val item = items[position]
        with(holder.binding) {            tvMenuName.text = item.menuName
            tvMenuQty.text = "x${item.quantity}"
            tvMenuPrice.text = item.getFormattedTotalPrice()
            Glide.with(root.context)
                .load(item.menuImageUrl)
                .placeholder(android.R.drawable.ic_menu_gallery)
                .into(ivMenuImage)
        }
    }

    override fun getItemCount(): Int = items.size
}
