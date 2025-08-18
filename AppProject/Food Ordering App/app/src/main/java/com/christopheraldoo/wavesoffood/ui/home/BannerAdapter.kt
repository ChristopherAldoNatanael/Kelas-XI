package com.christopheraldoo.wavesoffood.ui.home

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.wavesoffood.databinding.ItemBannerBinding

data class BannerItem(
    val title: String,
    val discount: String,
    val description: String,
    val buttonText: String,
    val backgroundDrawable: Int,
    val iconDrawable: Int
)

class BannerAdapter(
    private val bannerItems: List<BannerItem>,
    private val onBannerClick: (BannerItem) -> Unit
) : RecyclerView.Adapter<BannerAdapter.BannerViewHolder>() {

    inner class BannerViewHolder(private val binding: ItemBannerBinding) : 
        RecyclerView.ViewHolder(binding.root) {
        
        fun bind(item: BannerItem) {
            binding.apply {
                tvBannerTitle.text = item.title
                tvBannerDiscount.text = item.discount
                tvBannerDescription.text = item.description
                btnBannerAction.text = item.buttonText
                root.setBackgroundResource(item.backgroundDrawable)
                ivBannerIcon.setImageResource(item.iconDrawable)
                
                // Add click listener
                btnBannerAction.setOnClickListener {
                    onBannerClick(item)
                }
                
                root.setOnClickListener {
                    onBannerClick(item)
                }
            }
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): BannerViewHolder {
        val binding = ItemBannerBinding.inflate(
            LayoutInflater.from(parent.context), 
            parent, 
            false
        )
        return BannerViewHolder(binding)
    }

    override fun onBindViewHolder(holder: BannerViewHolder, position: Int) {
        holder.bind(bannerItems[position])
    }

    override fun getItemCount(): Int = bannerItems.size
}
