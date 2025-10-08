package com.christopheraldoo.bukuringkasapp

import android.os.Bundle
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.cardview.widget.CardView
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.gson.Gson
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

class HistoryActivity : AppCompatActivity() {

    private lateinit var database: AppDatabase
    private lateinit var historyRecyclerView: RecyclerView
    private lateinit var emptyStateLayout: LinearLayout
    private lateinit var clearHistoryButton: TextView
    private lateinit var historyAdapter: HistoryAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_history)

        database = AppDatabase.getDatabase(this)

        setupToolbar()
        setupViews()
        loadHistory()
    }

    private fun setupToolbar() {
        val toolbar = findViewById<androidx.appcompat.widget.Toolbar>(R.id.toolbar)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Riwayat Ringkasan"
    }

    private fun setupViews() {
        historyRecyclerView = findViewById(R.id.historyRecyclerView)
        emptyStateLayout = findViewById(R.id.emptyStateLayout)
        clearHistoryButton = findViewById(R.id.clearHistoryButton)

        historyRecyclerView.layoutManager = LinearLayoutManager(this)
        historyAdapter = HistoryAdapter { item ->
            showSummaryDetail(item)
        }
        historyRecyclerView.adapter = historyAdapter

        clearHistoryButton.setOnClickListener {
            showClearHistoryDialog()
        }
    }

    private fun loadHistory() {
        lifecycleScope.launch {
            val historyDao = database.historyDao()
            val history = historyDao.getAllHistory()

            if (history.isEmpty()) {
                showEmptyState()
            } else {
                showHistoryList(history)
            }
        }
    }

    private fun showEmptyState() {
        emptyStateLayout.visibility = View.VISIBLE
        historyRecyclerView.visibility = View.GONE
        clearHistoryButton.visibility = View.GONE
    }

    private fun showHistoryList(history: List<HistoryItem>) {
        emptyStateLayout.visibility = View.GONE
        historyRecyclerView.visibility = View.VISIBLE
        clearHistoryButton.visibility = View.VISIBLE

        historyAdapter.updateData(history)
    }

    private fun showSummaryDetail(item: HistoryItem) {
        try {
            val gson = Gson()
            val ringkasan = gson.fromJson(item.summaryData, RingkasanMateri::class.java)

            val message = buildSummaryMessage(ringkasan)
            showDetailDialog(item.title, message)
        } catch (e: Exception) {
            showDetailDialog("Error", "Error loading summary details")
        }
    }

    private fun buildSummaryMessage(ringkasan: RingkasanMateri?): String {
        if (ringkasan == null) return "No data available"

        return buildString {
            append("📚 ${ringkasan.topik}\n\n")
            append("🎯 KONSEP UTAMA:\n${ringkasan.konsepUtama}\n\n")

            if (ringkasan.poinPenting.isNotEmpty()) {
                append("💡 POIN-POIN PENTING:\n")
                ringkasan.poinPenting.forEach { point ->
                    append("• ${point.judul}: ${point.penjelasan}\n")
                }
                append("\n")
            }

            ringkasan.rumus?.let { rumusList ->
                if (rumusList.isNotEmpty()) {
                    append("📐 RUMUS KUNCI:\n")
                    rumusList.forEach { rumus ->
                        append("• ${rumus.formula}\n")
                        rumus.keterangan?.let { append("$it\n") }
                    }
                    append("\n")
                }
            }

            ringkasan.contohAplikasi?.let {
                append("📝 CONTOH APLIKASI:\n$it\n\n")
            }

            if (ringkasan.kataKunci.isNotEmpty()) {
                append("🔑 KATA KUNCI: ${ringkasan.kataKunci.joinToString(" • ")}")
            }
        }
    }

    private fun showDetailDialog(title: String, message: String) {
        AlertDialog.Builder(this)
            .setTitle(title)
            .setMessage(message)
            .setPositiveButton("Tutup") { dialog, _ -> dialog.dismiss() }
            .show()
    }

    private fun showClearHistoryDialog() {
        AlertDialog.Builder(this)
            .setTitle("Clear History")
            .setMessage("Apakah Anda yakin ingin menghapus semua riwayat ringkasan?")
            .setPositiveButton("Ya, Hapus") { _, _ ->
                clearHistory()
            }
            .setNegativeButton("Batal") { dialog, _ -> dialog.dismiss() }
            .show()
    }

    private fun clearHistory() {
        lifecycleScope.launch {
            val historyDao = database.historyDao()
            historyDao.clearHistory()
            loadHistory()
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressed()
        return true
    }

    inner class HistoryAdapter(private val onItemClick: (HistoryItem) -> Unit) :
        RecyclerView.Adapter<HistoryAdapter.HistoryViewHolder>() {

        private var historyItems: List<HistoryItem> = emptyList()
        private val dateFormat = SimpleDateFormat("dd MMM yyyy, HH:mm", Locale.getDefault())

        fun updateData(newItems: List<HistoryItem>) {
            historyItems = newItems
            notifyDataSetChanged()
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): HistoryViewHolder {
            val view = layoutInflater.inflate(R.layout.item_history, parent, false)
            return HistoryViewHolder(view)
        }

        override fun onBindViewHolder(holder: HistoryViewHolder, position: Int) {
            holder.bind(historyItems[position])
        }

        override fun getItemCount() = historyItems.size

        inner class HistoryViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
            private val titleTextView: TextView = itemView.findViewById(R.id.titleTextView)
            private val subjectTextView: TextView = itemView.findViewById(R.id.subjectTextView)
            private val dateTextView: TextView = itemView.findViewById(R.id.dateTextView)
            private val cardView: CardView = itemView.findViewById(R.id.historyCardView)

            fun bind(item: HistoryItem) {
                titleTextView.text = item.title
                subjectTextView.text = "${item.subject} • Kelas ${item.grade ?: "X"}"
                dateTextView.text = dateFormat.format(Date(item.createdDate))

                cardView.setOnClickListener {
                    onItemClick(item)
                }
            }
        }
    }
}
