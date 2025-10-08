package com.christopheraldoo.basicandroid

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            MaterialTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    FormScreen()
                }
            }
        }
    }
}

@Composable
fun FormScreen() {
    var nama by remember { mutableStateOf("Siti") }
    var alamat by remember { mutableStateOf("Sidoarjo") }
    var namaTersimpan by remember { mutableStateOf("Siti") }
    var alamatTersimpan by remember { mutableStateOf("Sidoarjo") }
    var isSaved by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Enhanced Google Logo
        Card(
            modifier = Modifier
                .size(100.dp)
                .padding(bottom = 32.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
            shape = CircleShape
        ) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.White)
            ) {
                GoogleLogo()
            }
        }

        // Form Card
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                // Nama Field
                OutlinedTextField(
                    value = nama,
                    onValueChange = { nama = it },
                    label = { Text("Nama", color = MaterialTheme.colorScheme.primary) },
                    placeholder = { Text("Masukkan Nama") },
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Color(0xFF4285F4),
                        unfocusedBorderColor = Color.LightGray,
                        focusedLabelColor = Color(0xFF4285F4)
                    )
                )

                Spacer(modifier = Modifier.height(16.dp))

                // Alamat Field
                OutlinedTextField(
                    value = alamat,
                    onValueChange = { alamat = it },
                    label = { Text("Alamat", color = MaterialTheme.colorScheme.primary) },
                    placeholder = { Text("Masukkan Alamat") },
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Color(0xFF4285F4),
                        unfocusedBorderColor = Color.LightGray,
                        focusedLabelColor = Color(0xFF4285F4)
                    )
                )

                Spacer(modifier = Modifier.height(24.dp))

                // Enhanced Simpan Button
                Button(
                    onClick = {
                        namaTersimpan = nama
                        alamatTersimpan = alamat
                        isSaved = true
                    },
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFF7B2CBF) // Purple color
                    ),
                    elevation = ButtonDefaults.buttonElevation(
                        defaultElevation = 8.dp,
                        pressedElevation = 12.dp
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp)
                        .clip(CircleShape)
                ) {
                    Text(
                        text = "💾 Simpan",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Enhanced Display Card
        if (isSaved) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 8.dp),
                elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
                colors = CardDefaults.cardColors(
                    containerColor = Color(0xFFF8F9FA)
                )
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = "📋 Data Tersimpan",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(bottom = 16.dp)
                    )

                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = Color.White
                        )
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            horizontalAlignment = Alignment.Start
                        ) {
                            Text(
                                text = "👤 Nama: $namaTersimpan",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Medium,
                                color = Color(0xFF4285F4)
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "📍 Alamat: $alamatTersimpan",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Medium,
                                color = Color(0xFF34A853)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun GoogleLogo() {
    Canvas(modifier = Modifier.size(60.dp)) {
        val canvasWidth = size.width
        val canvasHeight = size.height

        // Google Blue
        drawCircle(
            color = Color(0xFF4285F4),
            radius = canvasWidth * 0.18f,
            center = androidx.compose.ui.geometry.Offset(
                x = canvasWidth * 0.25f,
                y = canvasHeight * 0.25f
            )
        )

        // Google Red
        drawCircle(
            color = Color(0xFFEA4335),
            radius = canvasWidth * 0.18f,
            center = androidx.compose.ui.geometry.Offset(
                x = canvasWidth * 0.75f,
                y = canvasHeight * 0.25f
            )
        )

        // Google Yellow
        drawCircle(
            color = Color(0xFFFBBC05),
            radius = canvasWidth * 0.18f,
            center = androidx.compose.ui.geometry.Offset(
                x = canvasWidth * 0.25f,
                y = canvasHeight * 0.75f
            )
        )

        // Google Green
        drawCircle(
            color = Color(0xFF34A853),
            radius = canvasWidth * 0.18f,
            center = androidx.compose.ui.geometry.Offset(
                x = canvasWidth * 0.75f,
                y = canvasHeight * 0.75f
            )
        )

        // Center white circle
        drawCircle(
            color = Color.White,
            radius = canvasWidth * 0.12f,
            center = androidx.compose.ui.geometry.Offset(
                x = canvasWidth * 0.5f,
                y = canvasHeight * 0.5f
            )
        )

        // Google "G" in center
        // This is a simplified representation
    }

    Box(
        modifier = Modifier.size(60.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = "G",
            fontSize = 24.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF4285F4)
        )
    }
}
