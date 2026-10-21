package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.roundToInt

data class WeightPoint(
    val label: String,
    val value: Double
)

/**
 * Compact weight trend chart. It intentionally draws only what the user needs:
 * a baseline, a line, and the latest point. Labels stay outside the canvas so
 * they scale with the system font size instead of being baked into pixels.
 */
@Composable
fun WeightTrendChart(
    points: List<WeightPoint>,
    lineColor: Color,
    fillColor: Color,
    gridColor: Color,
    labelColor: Color,
    surfaceColor: Color,
    modifier: Modifier = Modifier
) {
    if (points.size < 2) return

    val lowest = points.minOf { it.value }
    val highest = points.maxOf { it.value }
    val range = (highest - lowest).takeIf { it > 0.0 } ?: 1.0

    Column(
        modifier = modifier
            .fillMaxWidth()
            .semantics(mergeDescendants = true) {
                contentDescription = "Grafik berat badan dari ${points.first().label} sampai " +
                    "${points.last().label}. Terendah ${formatKg(lowest)}, tertinggi ${formatKg(highest)}."
            }
    ) {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(132.dp)
        ) {
            val top = 10.dp.toPx()
            val bottom = size.height - 10.dp.toPx()
            val usableHeight = (bottom - top).coerceAtLeast(1f)

            // Three quiet guide lines keep the chart readable without a library.
            repeat(3) { index ->
                val y = top + (usableHeight * index / 2f)
                drawLine(
                    color = gridColor,
                    start = Offset(0f, y),
                    end = Offset(size.width, y),
                    strokeWidth = 1.dp.toPx()
                )
            }

            val stepX = size.width / (points.size - 1)
            val coordinates = points.mapIndexed { index, point ->
                val normalized = ((point.value - lowest) / range).toFloat()
                Offset(
                    x = index * stepX,
                    y = bottom - (normalized * usableHeight)
                )
            }

            val linePath = Path().apply {
                moveTo(coordinates.first().x, coordinates.first().y)
                coordinates.drop(1).forEach { lineTo(it.x, it.y) }
            }

            val fillPath = Path().apply {
                addPath(linePath)
                lineTo(coordinates.last().x, bottom)
                lineTo(coordinates.first().x, bottom)
                close()
            }

            drawPath(path = fillPath, color = fillColor)
            drawPath(
                path = linePath,
                color = lineColor,
                style = Stroke(
                    width = 2.5.dp.toPx(),
                    cap = StrokeCap.Round,
                    join = StrokeJoin.Round
                )
            )

            coordinates.forEachIndexed { index, offset ->
                val isLatest = index == coordinates.lastIndex
                if (isLatest) {
                    drawCircle(color = surfaceColor, radius = 5.dp.toPx(), center = offset)
                    drawCircle(
                        color = lineColor,
                        radius = 5.dp.toPx(),
                        center = offset,
                        style = Stroke(width = 2.5.dp.toPx())
                    )
                } else {
                    drawCircle(color = surfaceColor, radius = 3.5.dp.toPx(), center = offset)
                    drawCircle(color = lineColor.copy(alpha = 0.55f), radius = 2.dp.toPx(), center = offset)
                }
            }
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 6.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Text(
                text = points.first().label,
                fontSize = 10.sp,
                color = labelColor
            )
            Text(
                text = points.last().label,
                fontSize = 10.sp,
                fontWeight = FontWeight.SemiBold,
                color = labelColor
            )
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 2.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Text(
                text = "Terendah ${formatKg(lowest)}",
                fontSize = 10.sp,
                color = labelColor
            )
            Text(
                text = "Tertinggi ${formatKg(highest)}",
                fontSize = 10.sp,
                color = labelColor
            )
        }
    }
}

private fun formatKg(value: Double): String {
    val rounded = if (value % 1.0 == 0.0) {
        value.toInt().toString()
    } else {
        ((value * 10).roundToInt() / 10.0).toString()
    }
    return "$rounded kg"
}
