package com.christopheraldoo.simpleweatherapp.widget

import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.simpleweatherapp.R

/**
 * A simple activity to show users how to use the weather widget
 */
class WidgetTutorialActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.widget_tutorial)

        // Set up the close button
        findViewById<View>(R.id.btn_close).setOnClickListener {
            finish()
        }
    }
}
