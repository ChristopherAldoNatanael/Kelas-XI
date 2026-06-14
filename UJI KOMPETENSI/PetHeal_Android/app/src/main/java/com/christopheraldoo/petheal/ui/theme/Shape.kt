package com.christopheraldoo.petheal.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Shapes

val Shapes = Shapes(
    extraSmall = RoundedCornerShape(PetHealRadius.sm),
    small = RoundedCornerShape(PetHealRadius.md),
    medium = RoundedCornerShape(PetHealRadius.lg),
    large = RoundedCornerShape(PetHealRadius.xl),
    extraLarge = RoundedCornerShape(PetHealRadius.xxl)
)
