package com.christopheraldoo.earningquizapp.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.cardview.widget.CardView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.Category

/**
 * RecyclerView adapter for displaying quiz categories in a grid layout.
 *
 * This adapter provides a modern card-based layout for category selection
 * with attractive visual design and smooth interactions.
 */
class CategoryAdapter(
    private val categories: List<Category>,
    private val onCategoryClick: (Category) -> Unit
) : RecyclerView.Adapter<CategoryAdapter.CategoryViewHolder>() {

    /**
     * ViewHolder class for category items
     */
    inner class CategoryViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        val cardView: CardView = itemView.findViewById(R.id.card_category)
        val ivIcon: ImageView = itemView.findViewById(R.id.iv_category_icon)
        val tvName: TextView = itemView.findViewById(R.id.tv_category_name)
        val tvDescription: TextView = itemView.findViewById(R.id.tv_category_description)
        val tvQuestionCount: TextView = itemView.findViewById(R.id.tv_question_count)

        init {
            // Set click listener for the entire card
            cardView.setOnClickListener {
                val position = adapterPosition
                if (position != RecyclerView.NO_POSITION) {
                    onCategoryClick(categories[position])
                }
            }
        }

        /**
         * Bind category data to views
         */
        fun bind(category: Category) {
            // Set category icon
            ivIcon.setImageResource(category.iconResId)
            
            // Set category name and description
            tvName.text = category.name
            tvDescription.text = category.description
            tvQuestionCount.text = itemView.context.getString(
                R.string.question_count_format, 
                category.questionCount
            )
            
            // Set category color theme
            try {
                val backgroundColor = ContextCompat.getColor(itemView.context, category.color)
                cardView.setCardBackgroundColor(backgroundColor)
            } catch (e: Exception) {
                // Fallback to default color if resource not found
                val defaultColor = ContextCompat.getColor(itemView.context, R.color.colorPrimary)
                cardView.setCardBackgroundColor(defaultColor)
            }

            // Add subtle animation on bind
            cardView.animate()
                .scaleX(1f)
                .scaleY(1f)
                .alpha(1f)
                .setDuration(200)
                .start()
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): CategoryViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_category, parent, false)
        return CategoryViewHolder(view)
    }

    override fun onBindViewHolder(holder: CategoryViewHolder, position: Int) {
        holder.bind(categories[position])
    }

    override fun getItemCount(): Int = categories.size
}
