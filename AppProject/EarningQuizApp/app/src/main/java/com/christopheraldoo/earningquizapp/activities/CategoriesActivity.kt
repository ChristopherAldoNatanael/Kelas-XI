package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.earningquizapp.MainActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.adapters.CategoryAdapter
import com.christopheraldoo.earningquizapp.models.Category

/**
 * Activity for displaying quiz categories.
 *
 * This screen shows different subject categories available for quiz.
 * Users can select a category to start a quiz in that subject.
 */
class CategoriesActivity : AppCompatActivity() {

    private lateinit var recyclerView: RecyclerView
    private lateinit var categoryAdapter: CategoryAdapter
    private lateinit var categories: List<Category>

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_categories)

        initializeViews()
        setupCategories()
        setupRecyclerView()
    }

    /**
     * Initialize view components
     */
    private fun initializeViews() {
        recyclerView = findViewById(R.id.rv_categories)
    }

    /**
     * Setup quiz categories with educational subjects
     */
    private fun setupCategories() {
        categories = listOf(
            Category(
                id = "math",
                name = getString(R.string.category_mathematics),
                description = getString(R.string.category_math_desc),
                iconResId = R.drawable.ic_math,
                color = R.color.colorMath
            ),
            Category(
                id = "science",
                name = getString(R.string.category_science),
                description = getString(R.string.category_science_desc),
                iconResId = R.drawable.ic_science,
                color = R.color.colorScience
            ),
            Category(
                id = "history",
                name = getString(R.string.category_history),
                description = getString(R.string.category_history_desc),
                iconResId = R.drawable.ic_history,
                color = R.color.colorHistory
            ),
            Category(
                id = "geography",
                name = getString(R.string.category_geography),
                description = getString(R.string.category_geography_desc),
                iconResId = R.drawable.ic_geography,
                color = R.color.colorGeography
            ),
            Category(
                id = "english",
                name = getString(R.string.category_english),
                description = getString(R.string.category_english_desc),
                iconResId = R.drawable.ic_english,
                color = R.color.colorEnglish
            ),
            Category(
                id = "biology",
                name = getString(R.string.category_biology),
                description = getString(R.string.category_biology_desc),
                iconResId = R.drawable.ic_biology,
                color = R.color.colorBiology
            ),
            Category(
                id = "physics",
                name = getString(R.string.category_physics),
                description = getString(R.string.category_physics_desc),
                iconResId = R.drawable.ic_physics,
                color = R.color.colorPhysics
            ),
            Category(
                id = "chemistry",
                name = getString(R.string.category_chemistry),
                description = getString(R.string.category_chemistry_desc),
                iconResId = R.drawable.ic_chemistry,
                color = R.color.colorChemistry
            )
        )
    }

    /**
     * Setup RecyclerView with GridLayoutManager for modern card layout
     */
    private fun setupRecyclerView() {
        val gridLayoutManager = GridLayoutManager(this, 2) // 2 columns
        recyclerView.layoutManager = gridLayoutManager

        categoryAdapter = CategoryAdapter(categories) { category ->
            onCategorySelected(category)
        }
        recyclerView.adapter = categoryAdapter
    }

    /**
     * Handle category selection
     */
    private fun onCategorySelected(category: Category) {
        val intent = Intent(this, QuizActivity::class.java).apply {
            putExtra("CATEGORY_ID", category.id)
            putExtra("CATEGORY_NAME", category.name)
        }
        startActivity(intent)
    }

    override fun onBackPressed() {
        super.onBackPressed()
        // Navigate back to main activity
        val intent = Intent(this, MainActivity::class.java)
        startActivity(intent)
        finish()
    }
}
