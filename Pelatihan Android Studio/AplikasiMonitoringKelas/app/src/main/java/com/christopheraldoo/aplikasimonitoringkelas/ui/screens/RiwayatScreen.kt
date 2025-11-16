package com.christopheraldoo.aplikasimonitoringkelas.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.christopheraldoo.aplikasimonitoringkelas.data.RiwayatItem
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.RiwayatUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RiwayatScreen(viewModel: SiswaViewModel) {
    val context = LocalContext.current
    
    // Observe state from ViewModel
    val uiState by viewModel.riwayatState.collectAsState()

    // Load data when the screen is first launched
    LaunchedEffect(Unit) {
        viewModel.loadRiwayat()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Riwayat Kehadiran") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = Color.White
                )
            )
        }
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when (val state = uiState) {
                is RiwayatUiState.Loading -> {
                    CircularProgressIndicator(modifier = Modifier.align(Alignment.Center))
                }
                is RiwayatUiState.Error -> {
                    Text(
                        text = state.message,
                        modifier = Modifier.align(Alignment.Center).padding(16.dp)
                    )
                }
                is RiwayatUiState.Success -> {
                    if (state.data.isEmpty()) {
                        Text(
                            text = "Belum ada riwayat kehadiran",
                            modifier = Modifier.align(Alignment.Center)
                        )
                    } else {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize().padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            items(state.data) { item ->
                                RiwayatCard(item)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun RiwayatCard(item: RiwayatItem) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = item.tanggal,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = if (item.guruHadir) 
                            Icons.Default.CheckCircle else Icons.Default.Cancel,
                        contentDescription = null,
                        tint = if (item.guruHadir) 
                            Color(0xFF4CAF50) else Color(0xFFFF5252),
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = if (item.guruHadir) "Hadir" else "Tidak Hadir",
                        style = MaterialTheme.typography.bodySmall
                    )
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text(text = "${item.subject} - ${item.teacher}")
            Text(text = "${item.day} | Periode ${item.period} | ${item.time}")
            
            if (item.catatan.isNotEmpty()) {
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = "Catatan: ${item.catatan}",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.Gray
                )
            }
        }
    }
}
