package com.christopheraldoo.bukuringkasapp.ui.textinput

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.automirrored.filled.FormatListBulleted
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

// Custom color palette - Clean and professional
private val PrimaryBlue = Color(0xFF2563EB)
private val PrimaryBlueDark = Color(0xFF1D4ED8)
private val SecondaryTeal = Color(0xFF0D9488)
private val BackgroundLight = Color(0xFFF8FAFC)
private val SurfaceWhite = Color(0xFFFFFFFF)
private val TextPrimary = Color(0xFF1E293B)
private val TextSecondary = Color(0xFF64748B)
private val SuccessGreen = Color(0xFF10B981)
private val WarningOrange = Color(0xFFF59E0B)
private val ErrorRed = Color(0xFFEF4444)

@Composable
fun TextInputScreen(
    onNavigateBack: () -> Unit,
    viewModel: TextInputViewModel
) {
    val uiState by viewModel.uiState.collectAsState()
    val scrollState = rememberScrollState()
    
    var showEnhanceDialog by remember { mutableStateOf(false) }
    var showEditDialog by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            TextInputTopBar(onNavigateBack = onNavigateBack)
        },
        containerColor = BackgroundLight
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(scrollState)
                .padding(20.dp)
        ) {
            // Header Section
            HeaderSection()
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Input Section
            InputSection(
                inputText = uiState.inputText,
                onTextChange = { viewModel.updateInputText(it) },
                characterCount = uiState.inputText.length
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Action Buttons
            ActionButtons(
                isLoading = uiState.isLoading,
                inputText = uiState.inputText,
                onSummarize = { viewModel.summarizeText() },
                onClear = { viewModel.clearAll() }
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Loading Indicator
            AnimatedVisibility(
                visible = uiState.isLoading,
                enter = fadeIn() + expandVertically(),
                exit = fadeOut() + shrinkVertically()
            ) {
                LoadingSection()
            }
            
            // Error Message
            AnimatedVisibility(
                visible = uiState.errorMessage != null,
                enter = fadeIn() + slideInVertically(),
                exit = fadeOut() + slideOutVertically()
            ) {
                uiState.errorMessage?.let { error ->
                    ErrorCard(message = error)
                }
            }
            
            // Result Section
            AnimatedVisibility(
                visible = uiState.summaryResult != null && !uiState.isLoading,
                enter = fadeIn(animationSpec = tween(300)) + expandVertically(),
                exit = fadeOut() + shrinkVertically()
            ) {
                uiState.summaryResult?.let { result ->
                    ResultSection(
                        title = result.topik ?: "Ringkasan",
                        content = result.ringkasan?.konsepUtama ?: "",
                        isSaved = uiState.isSaved,
                        onEnhance = { showEnhanceDialog = true },
                        onEdit = { showEditDialog = true },
                        onCopy = { viewModel.copyToClipboard() }
                    )
                }
            }
            
            // Feedback Section
            AnimatedVisibility(
                visible = uiState.summaryResult != null && !uiState.isLoading,
                enter = fadeIn(animationSpec = tween(500)) + expandVertically(),
                exit = fadeOut()
            ) {
                FeedbackSection(
                    feedbackText = uiState.feedbackText,
                    onFeedbackChange = { viewModel.updateFeedbackText(it) },
                    onSendFeedback = { viewModel.sendFeedback() }
                )
            }
        }
    }

    // Enhancement Dialog
    if (showEnhanceDialog) {
        EnhanceDialog(
            onDismiss = { showEnhanceDialog = false },
            onSelectOption = { type ->
                viewModel.enhanceSummary(type)
                showEnhanceDialog = false
            }
        )
    }

    // Edit Dialog
    if (showEditDialog) {
        EditSummaryDialog(
            currentText = uiState.summaryResult?.ringkasan?.konsepUtama ?: "",
            onDismiss = { showEditDialog = false },
            onSave = { editedText ->
                viewModel.updateSummary(editedText)
                showEditDialog = false
            }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TextInputTopBar(onNavigateBack: () -> Unit) {
    TopAppBar(
        title = {
            Text(
                "Input Teks",
                fontWeight = FontWeight.SemiBold,
                fontSize = 18.sp
            )
        },
        navigationIcon = {
            IconButton(onClick = onNavigateBack) {
                Icon(
                    Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Kembali",
                    tint = SurfaceWhite
                )
            }
        },
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = PrimaryBlue,
            titleContentColor = SurfaceWhite
        )
    )
}

@Composable
private fun HeaderSection() {
    Column {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(
                        Brush.linearGradient(
                            colors = listOf(PrimaryBlue, SecondaryTeal)
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Outlined.EditNote,
                    contentDescription = null,
                    tint = SurfaceWhite,
                    modifier = Modifier.size(28.dp)
                )
            }
            
            Column {
                Text(
                    "Input Teks Manual",
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = TextPrimary
                )
                Text(
                    "Paste atau ketik materi pelajaran",
                    fontSize = 14.sp,
                    color = TextSecondary
                )
            }
        }
    }
}

@Composable
private fun InputSection(
    inputText: String,
    onTextChange: (String) -> Unit,
    characterCount: Int
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            OutlinedTextField(
                value = inputText,
                onValueChange = onTextChange,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(200.dp),
                placeholder = {
                    Text(
                        "Masukkan teks materi pelajaran di sini...\n\nContoh: Definisi, konsep, atau penjelasan dari buku pelajaran.",
                        color = TextSecondary.copy(alpha = 0.6f)
                    )
                },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = PrimaryBlue,
                    unfocusedBorderColor = Color(0xFFE2E8F0),
                    cursorColor = PrimaryBlue
                ),
                shape = RoundedCornerShape(12.dp),
                keyboardOptions = KeyboardOptions(
                    capitalization = KeyboardCapitalization.Sentences
                )
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    "$characterCount karakter",
                    fontSize = 12.sp,
                    color = TextSecondary
                )
                
                Text(
                    "Maksimal 10.000 karakter",
                    fontSize = 12.sp,
                    color = if (characterCount > 10000) ErrorRed else TextSecondary
                )
            }
        }
    }
}

@Composable
private fun ActionButtons(
    isLoading: Boolean,
    inputText: String,
    onSummarize: () -> Unit,
    onClear: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Clear Button
        OutlinedButton(
            onClick = onClear,
            modifier = Modifier
                .weight(1f)
                .height(52.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(
                contentColor = TextSecondary
            ),
            border = androidx.compose.foundation.BorderStroke(
                width = 1.dp,
                brush = Brush.linearGradient(listOf(Color(0xFFE2E8F0), Color(0xFFE2E8F0)))
            )
        ) {
            Icon(
                Icons.Outlined.Clear,
                contentDescription = null,
                modifier = Modifier.size(18.dp)
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text("Bersihkan", fontWeight = FontWeight.Medium)
        }
        
        // Summarize Button
        Button(
            onClick = onSummarize,
            modifier = Modifier
                .weight(2f)
                .height(52.dp),
            enabled = inputText.isNotBlank() && !isLoading,
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = PrimaryBlue,
                disabledContainerColor = Color(0xFFCBD5E1)
            )
        ) {
            if (isLoading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    color = SurfaceWhite,
                    strokeWidth = 2.dp
                )
            } else {
                Icon(
                    Icons.Outlined.AutoAwesome,
                    contentDescription = null,
                    modifier = Modifier.size(20.dp)
                )
            }
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                if (isLoading) "Memproses..." else "Ringkas Sekarang",
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Composable
private fun LoadingSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Animated loading indicator
            val infiniteTransition = rememberInfiniteTransition(label = "loading")
            @Suppress("UNUSED_VARIABLE")
            val rotation by infiniteTransition.animateFloat(
                initialValue = 0f,
                targetValue = 360f,
                animationSpec = infiniteRepeatable(
                    animation = tween(1000, easing = LinearEasing)
                ),
                label = "rotation"
            )
            
            CircularProgressIndicator(
                modifier = Modifier.size(48.dp),
                color = PrimaryBlue,
                strokeWidth = 4.dp
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                "Sedang meringkas dengan AI...",
                fontSize = 16.sp,
                fontWeight = FontWeight.Medium,
                color = TextPrimary
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                "Mohon tunggu sebentar",
                fontSize = 14.sp,
                color = TextSecondary
            )
        }
    }
}

@Composable
private fun ErrorCard(message: String) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = ErrorRed.copy(alpha = 0.1f))
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.Top
        ) {
            Icon(
                Icons.Outlined.ErrorOutline,
                contentDescription = null,
                tint = ErrorRed,
                modifier = Modifier.size(24.dp)
            )
            Spacer(modifier = Modifier.width(12.dp))
            Text(
                message,
                color = ErrorRed,
                fontSize = 14.sp
            )
        }
    }
}

@Composable
private fun ResultSection(
    title: String,
    content: String,
    isSaved: Boolean,
    onEnhance: () -> Unit,
    onEdit: () -> Unit,
    onCopy: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = SurfaceWhite)
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(SuccessGreen.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Outlined.CheckCircle,
                            contentDescription = null,
                            tint = SuccessGreen,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(
                            title,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = TextPrimary
                        )
                        if (isSaved) {
                            Text(
                                "✓ Tersimpan",
                                fontSize = 12.sp,
                                color = SuccessGreen
                            )
                        }
                    }
                }
                
                IconButton(onClick = onCopy) {
                    Icon(
                        Icons.Outlined.ContentCopy,
                        contentDescription = "Salin",
                        tint = TextSecondary
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            HorizontalDivider(color = Color(0xFFE2E8F0))
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Content
            Text(
                content,
                fontSize = 15.sp,
                color = TextPrimary,
                lineHeight = 24.sp
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Action Chips
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                ActionChip(
                    icon = Icons.Outlined.Refresh,
                    text = "Tingkatkan",
                    onClick = onEnhance
                )
                ActionChip(
                    icon = Icons.Outlined.Edit,
                    text = "Edit",
                    onClick = onEdit
                )
            }
        }
    }
}

@Composable
private fun ActionChip(
    icon: ImageVector,
    text: String,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(20.dp),
        color = Color(0xFFF1F5F9)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                icon,
                contentDescription = null,
                modifier = Modifier.size(16.dp),
                tint = PrimaryBlue
            )
            Spacer(modifier = Modifier.width(6.dp))
            Text(
                text,
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
                color = PrimaryBlue
            )
        }
    }
}

@Composable
private fun FeedbackSection(
    feedbackText: String,
    onFeedbackChange: (String) -> Unit,
    onSendFeedback: () -> Unit
) {
    Spacer(modifier = Modifier.height(20.dp))
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Outlined.Feedback,
                    contentDescription = null,
                    tint = WarningOrange,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    "Ada masukan?",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium,
                    color = TextPrimary
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            OutlinedTextField(
                value = feedbackText,
                onValueChange = onFeedbackChange,
                modifier = Modifier.fillMaxWidth(),
                placeholder = {
                    Text(
                        "Tulis masukan untuk ringkasan...",
                        fontSize = 14.sp,
                        color = TextSecondary.copy(alpha = 0.6f)
                    )
                },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = PrimaryBlue,
                    unfocusedBorderColor = Color(0xFFE2E8F0)
                ),
                shape = RoundedCornerShape(8.dp),
                maxLines = 3
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Button(
                onClick = onSendFeedback,
                enabled = feedbackText.isNotBlank(),
                modifier = Modifier.align(Alignment.End),
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = WarningOrange),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp)
            ) {
                Icon(
                    Icons.AutoMirrored.Filled.Send,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text("Kirim", fontSize = 14.sp)
            }
        }
    }
}

@Composable
private fun EnhanceDialog(
    onDismiss: () -> Unit,
    onSelectOption: (String) -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                "Tingkatkan Ringkasan",
                fontWeight = FontWeight.SemiBold
            )
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                EnhanceOption(
                    icon = Icons.Outlined.Compress,
                    title = "Lebih Ringkas",
                    subtitle = "Buat lebih singkat dan padat",
                    onClick = { onSelectOption("ringkas") }
                )
                EnhanceOption(
                    icon = Icons.Outlined.Expand,
                    title = "Lebih Detail",
                    subtitle = "Tambah penjelasan mendalam",
                    onClick = { onSelectOption("detail") }
                )
                EnhanceOption(
                    icon = Icons.Outlined.Lightbulb,
                    title = "Tambah Contoh",
                    subtitle = "Sertakan contoh konkret",
                    onClick = { onSelectOption("contoh") }
                )
                EnhanceOption(
                    icon = Icons.AutoMirrored.Filled.FormatListBulleted,
                    title = "Format Poin",
                    subtitle = "Gunakan bullet points",
                    onClick = { onSelectOption("poin") }
                )
            }
        },
        confirmButton = {},
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Batal")
            }
        },
        shape = RoundedCornerShape(16.dp)
    )
}

@Composable
private fun EnhanceOption(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(12.dp),
        color = Color(0xFFF8FAFC)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(PrimaryBlue.copy(alpha = 0.1f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    icon,
                    contentDescription = null,
                    tint = PrimaryBlue,
                    modifier = Modifier.size(20.dp)
                )
            }
            Spacer(modifier = Modifier.width(12.dp))
            Column {
                Text(
                    title,
                    fontWeight = FontWeight.Medium,
                    fontSize = 14.sp,
                    color = TextPrimary
                )
                Text(
                    subtitle,
                    fontSize = 12.sp,
                    color = TextSecondary
                )
            }
        }
    }
}

@Composable
private fun EditSummaryDialog(
    currentText: String,
    onDismiss: () -> Unit,
    onSave: (String) -> Unit
) {
    var editedText by remember { mutableStateOf(currentText) }
    
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                "Edit Ringkasan",
                fontWeight = FontWeight.SemiBold
            )
        },
        text = {
            OutlinedTextField(
                value = editedText,
                onValueChange = { editedText = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(200.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = PrimaryBlue
                ),
                shape = RoundedCornerShape(8.dp)
            )
        },
        confirmButton = {
            Button(
                onClick = { onSave(editedText) },
                colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue)
            ) {
                Text("Simpan")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Batal")
            }
        },
        shape = RoundedCornerShape(16.dp)
    )
}
