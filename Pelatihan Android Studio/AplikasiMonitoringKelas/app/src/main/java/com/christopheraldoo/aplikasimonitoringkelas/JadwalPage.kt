package com.christopheraldoo.aplikasimonitoringkelas

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

// Data class untuk menyimpan informasi jadwal
data class JadwalItem(
    val jamKe: String,
    val mataPelajaran: String,
    val kodeGuru: String,
    val namaGuru: String
)

// Sample data untuk demonstrasi berdasarkan kelas
val jadwalDataX_RPL = listOf(
    JadwalItem("Jam ke-1", "Matematika", "GR001", "Pak Budi Santoso"),
    JadwalItem("Jam ke-2", "Bahasa Indonesia", "GR002", "Ibu Siti Nurhaliza"),
    JadwalItem("Jam ke-3", "IPA Terpadu", "GR003", "Pak Adi Wijaya"),
    JadwalItem("Jam ke-4", "IPS Terpadu", "GR004", "Ibu Maya Sari"),
    JadwalItem("Jam ke-5", "Bahasa Inggris", "GR005", "Mr. John Smith"),
    JadwalItem("Jam ke-6", "Pemrograman Dasar", "GR006", "Pak Eko Prasetyo"),
    JadwalItem("Jam ke-7", "Pendidikan Jasmani", "GR007", "Pak Doni Ramadhan"),
    JadwalItem("Jam ke-8", "Basis Data", "GR008", "Ibu Rina Agustina"),
    JadwalItem("Jam ke-9", "Agama", "GR009", "Ustad Ahmad Fauzi"),
    JadwalItem("Jam ke-10", "Bimbingan Konseling", "GR010", "Ibu Lisa Permata")
)

val jadwalDataXI_RPL = listOf(
    JadwalItem("Jam ke-1", "Matematika Lanjut", "GR011", "Pak Budi Santoso"),
    JadwalItem("Jam ke-2", "Bahasa Indonesia", "GR012", "Ibu Siti Nurhaliza"),
    JadwalItem("Jam ke-3", "Fisika", "GR013", "Pak Adi Wijaya"),
    JadwalItem("Jam ke-4", "Kimia", "GR014", "Ibu Maya Sari"),
    JadwalItem("Jam ke-5", "Bahasa Inggris Bisnis", "GR015", "Mr. John Smith"),
    JadwalItem("Jam ke-6", "Pemrograman Web", "GR016", "Pak Eko Prasetyo"),
    JadwalItem("Jam ke-7", "Pendidikan Jasmani", "GR017", "Pak Doni Ramadhan"),
    JadwalItem("Jam ke-8", "Sistem Operasi", "GR018", "Ibu Rina Agustina"),
    JadwalItem("Jam ke-9", "Agama", "GR019", "Ustad Ahmad Fauzi"),
    JadwalItem("Jam ke-10", "Bimbingan Konseling", "GR020", "Ibu Lisa Permata")
)

val jadwalDataXII_RPL = listOf(
    JadwalItem("Jam ke-1", "Kalkulus", "GR021", "Pak Budi Santoso"),
    JadwalItem("Jam ke-2", "Sastra Indonesia", "GR022", "Ibu Siti Nurhaliza"),
    JadwalItem("Jam ke-3", "Biologi", "GR023", "Pak Adi Wijaya"),
    JadwalItem("Jam ke-4", "Ekonomi", "GR024", "Ibu Maya Sari"),
    JadwalItem("Jam ke-5", "Bahasa Inggris Teknis", "GR025", "Mr. John Smith"),
    JadwalItem("Jam ke-6", "Pemrograman Mobile", "GR026", "Pak Eko Prasetyo"),
    JadwalItem("Jam ke-7", "Pendidikan Jasmani", "GR027", "Pak Doni Ramadhan"),
    JadwalItem("Jam ke-8", "Jaringan Komputer", "GR028", "Ibu Rina Agustina"),
    JadwalItem("Jam ke-9", "Agama", "GR029", "Ustad Ahmad Fauzi"),
    JadwalItem("Jam ke-10", "Bimbingan Konseling", "GR030", "Ibu Lisa Permata")
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun JadwalPage(userName: String = "John Doe", userRole: String = "Siswa") {
    // State untuk spinner
    var selectedHari by remember { mutableStateOf("Senin") }
    var selectedKelas by remember { mutableStateOf("X RPL") }
    var expandedHari by remember { mutableStateOf(false) }
    var expandedKelas by remember { mutableStateOf(false) }

    // Opsi untuk spinner
    val hariOptions = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu")
    val kelasOptions = listOf("X RPL", "XI RPL", "XII RPL")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Informasi user yang login
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer
            )
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Selamat Datang!",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                    Text(
                        text = "$userName - $userRole",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                }
            }
        }

        // Judul halaman
        Text(
            text = "Jadwal Pelajaran",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        )

        // Spinner untuk hari
        Text(
            text = "Pilih Hari:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedHari,
            onExpandedChange = { expandedHari = !expandedHari },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedHari,
                onValueChange = { selectedHari = it },
                readOnly = true,
                label = { Text("Pilih Hari") },
                placeholder = { Text("Pilih hari dalam seminggu") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedHari)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedHari,
                onDismissRequest = { expandedHari = false }
            ) {
                hariOptions.forEach { hari ->
                    DropdownMenuItem(
                        text = { Text(hari) },
                        onClick = {
                            selectedHari = hari
                            expandedHari = false
                        }
                    )
                }
            }
        }

        // Spinner untuk kelas
        Text(
            text = "Pilih Kelas:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedKelas,
            onExpandedChange = { expandedKelas = !expandedKelas },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        ) {
            OutlinedTextField(
                value = selectedKelas,
                onValueChange = { selectedKelas = it },
                readOnly = true,
                label = { Text("Pilih Kelas") },
                placeholder = { Text("Pilih kelas yang diinginkan") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedKelas)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedKelas,
                onDismissRequest = { expandedKelas = false }
            ) {
                kelasOptions.forEach { kelas ->
                    DropdownMenuItem(
                        text = { Text(kelas) },
                        onClick = {
                            selectedKelas = kelas
                            expandedKelas = false
                        }
                    )
                }
            }
        }

        // LazyColumn untuk cards yang scrollable dengan jadwal berdasarkan kelas
        if (selectedHari.isNotEmpty() && selectedKelas.isNotEmpty()) {
            // Pilih data jadwal berdasarkan kelas yang dipilih
            val currentJadwalData = when (selectedKelas) {
                "X RPL" -> jadwalDataX_RPL
                "XI RPL" -> jadwalDataXI_RPL
                "XII RPL" -> jadwalDataXII_RPL
                else -> jadwalDataX_RPL
            }

            // Informasi pilihan yang dipilih
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.secondaryContainer
                )
            ) {
                Text(
                    text = "Menampilkan jadwal untuk: $selectedHari - $selectedKelas",
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(12.dp),
                    color = MaterialTheme.colorScheme.onSecondaryContainer
                )
            }

            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                contentPadding = PaddingValues(bottom = 16.dp)
            ) {
                items(currentJadwalData) { jadwal ->
                    JadwalCard(jadwal)
                }
            }
        }
    }
}

@Composable
fun JadwalCard(jadwal: JadwalItem) {
    Card(
        modifier = Modifier
            .fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Jam ke berapa
            Text(
                text = jadwal.jamKe,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            // Mata pelajaran
            Text(
                text = jadwal.mataPelajaran,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            // Divider
            HorizontalDivider(
                modifier = Modifier.padding(vertical = 8.dp),
                color = MaterialTheme.colorScheme.outlineVariant
            )

            // Kode guru dan nama guru
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Kode Guru:",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.Gray
                    )
                    Text(
                        text = jadwal.kodeGuru,
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Bold
                    )
                }

                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = "Nama Guru:",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.Gray
                    )
                    Text(
                        text = jadwal.namaGuru,
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.End
                    )
                }
            }
        }
    }
}

// Preview untuk testing
@androidx.compose.ui.tooling.preview.Preview(showBackground = true)
@Composable
fun JadwalPagePreview() {
    MaterialTheme {
        JadwalPage("Ahmad Fauzi", "Siswa")
    }
}
