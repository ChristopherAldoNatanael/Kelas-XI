package com.christopheraldoo.bukuringkasapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier

import com.christopheraldoo.bukuringkasapp.ui.theme.BukuRingkasAppTheme

/**
 * Main Activity untuk BukuRingkasApp
 * Menggunakan Jetpack Compose sebagai UI framework
 */
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)



        setContent {
            BukuRingkasAppTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    BukuRingkasApp()
                }
            }
        }
    }
}
