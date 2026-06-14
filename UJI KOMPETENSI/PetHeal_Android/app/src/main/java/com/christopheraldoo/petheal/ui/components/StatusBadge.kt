package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.StatusCancelled
import com.christopheraldoo.petheal.ui.theme.StatusCompleted
import com.christopheraldoo.petheal.ui.theme.StatusConfirmed
import com.christopheraldoo.petheal.ui.theme.StatusPaid
import com.christopheraldoo.petheal.ui.theme.StatusPending
import com.christopheraldoo.petheal.ui.theme.StatusRejected

@Composable
fun StatusBadge(
    text: String,
    status: String?,
    modifier: Modifier = Modifier
) {
    val color = statusColor(status)
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(PetHealRadius.pill),
        color = color.copy(alpha = 0.12f),
        border = BorderStroke(1.dp, color.copy(alpha = 0.20f))
    ) {
        Text(
            text = text,
            color = color,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
        )
    }
}

fun statusColor(status: String?): Color {
    return when (status?.lowercase()) {
        "paid", "dp_paid" -> StatusPaid
        "confirmed" -> StatusConfirmed
        "completed" -> StatusCompleted
        "pending", "dp_pending", "partial" -> StatusPending
        "cancelled", "failed" -> StatusCancelled
        "rejected" -> StatusRejected
        else -> Color(0xFF64748B)
    }
}
