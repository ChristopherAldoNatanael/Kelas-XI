package com.christopheraldoo.learnnavigation.ui.screen

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.christopheraldoo.learnnavigation.ui.theme.LearnNavigationTheme

@Composable
fun AboutScreen(onNavigateUp: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(text = "Ini adalah Halaman About")
        Spacer(modifier = Modifier.height(16.dp))
        Button(onClick = onNavigateUp) {
            Text(text = "Kembali")
        }
    }
}

@Preview(showBackground = true)
@Composable
fun AboutScreenPreview() {
    LearnNavigationTheme {
        AboutScreen({})
    }
}