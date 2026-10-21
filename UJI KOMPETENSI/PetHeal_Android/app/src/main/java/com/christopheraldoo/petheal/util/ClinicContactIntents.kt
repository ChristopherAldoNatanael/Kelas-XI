package com.christopheraldoo.petheal.util

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast

/**
 * Pembuka kanal kontak klinik. Semua tombol Bantuan memakai jalur ini supaya
 * perilakunya identik di seluruh aplikasi.
 *
 * WhatsApp memakai link `https://wa.me` (bukan scheme `whatsapp://`) agar
 * tetap aman ketika aplikasi WhatsApp tidak terpasang.
 */
fun openWhatsApp(context: Context, number: String?, message: String): Boolean {
    val url = number?.let { buildWhatsAppUrl(it, message) } ?: run {
        toast(context, "Nomor WhatsApp klinik belum valid.")
        return false
    }
    return launch(context, Intent(Intent.ACTION_VIEW, Uri.parse(url))) {
        toast(context, "Tidak ada aplikasi yang bisa membuka WhatsApp atau browser.")
    }
}

fun openClinicPhone(context: Context, number: String?): Boolean {
    val normalized = normalizeWhatsAppNumber(number) ?: run {
        toast(context, "Nomor telepon klinik belum tersedia.")
        return false
    }
    return launch(context, Intent(Intent.ACTION_DIAL, Uri.parse("tel:+$normalized"))) {
        toast(context, "Aplikasi telepon tidak tersedia.")
    }
}

fun openClinicEmail(context: Context, address: String?, subject: String): Boolean {
    val email = address?.trim()?.takeIf { it.isNotBlank() } ?: run {
        toast(context, "Email klinik belum tersedia.")
        return false
    }
    val intent = Intent(Intent.ACTION_SENDTO).apply {
        data = Uri.parse("mailto:$email")
        putExtra(Intent.EXTRA_SUBJECT, subject)
    }
    return launch(context, intent) {
        toast(context, "Aplikasi email belum tersedia.")
    }
}

private fun launch(context: Context, intent: Intent, onFailure: () -> Unit): Boolean {
    return runCatching { context.startActivity(intent) }
        .onFailure { onFailure() }
        .isSuccess
}

private fun toast(context: Context, text: String) {
    Toast.makeText(context, text, Toast.LENGTH_SHORT).show()
}
