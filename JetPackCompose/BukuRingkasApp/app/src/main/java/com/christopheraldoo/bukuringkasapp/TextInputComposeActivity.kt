package com.christopheraldoo.bukuringkasapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import com.christopheraldoo.bukuringkasapp.ui.textinput.TextInputScreen
import com.christopheraldoo.bukuringkasapp.ui.textinput.TextInputViewModel
import com.christopheraldoo.bukuringkasapp.ui.theme.BukuRingkasTheme

class TextInputComposeActivity : ComponentActivity() {
    
    private val viewModel: TextInputViewModel by viewModels()
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        setContent {
            BukuRingkasTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    TextInputScreen(
                        onNavigateBack = { finish() },
                        viewModel = viewModel
                    )
                }
            }
        }
    }
}
