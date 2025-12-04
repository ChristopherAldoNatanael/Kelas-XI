package com.christopheraldoo.bukuringkasapp

import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import androidx.cardview.widget.CardView
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.bukuringkasapp.data.model.AppDatabase
import kotlinx.coroutines.launch

class HomeFragment : Fragment() {

    private lateinit var database: AppDatabase

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.fragment_home, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        database = AppDatabase.getDatabase(requireContext())

        setupViews(view)
        loadLatestSummary(view)
    }

    private fun setupViews(view: View) {
        val cameraButton = view.findViewById<CardView>(R.id.cameraButton)
        val textButton = view.findViewById<CardView>(R.id.textButton)

        cameraButton.setOnClickListener {
            startActivity(Intent(requireContext(), MultiImageCameraActivity::class.java))
        }

        textButton.setOnClickListener {
            startActivity(Intent(requireContext(), TextInputActivity::class.java))
        }

        // Setup history button
        val historyButton = view.findViewById<Button>(R.id.historyButton)
        historyButton.setOnClickListener {
            startActivity(Intent(requireContext(), HistoryActivity::class.java))
        }
    }

    private fun loadLatestSummary(view: View) {
        val latestSummaryCard = view.findViewById<CardView>(R.id.latestSummaryCard)
        val summaryTitle = view.findViewById<TextView>(R.id.summaryTitle)
        val summarySubject = view.findViewById<TextView>(R.id.summarySubject)
        val summaryContent = view.findViewById<TextView>(R.id.summaryContent)

        lifecycleScope.launch {
            val historyDao = database.historyDao()
            historyDao.getAll().collect { history ->
                if (history.isNotEmpty()) {
                    val latestItem = history.first()
                    latestSummaryCard.visibility = View.VISIBLE

                    try {
                        val gson = com.google.gson.Gson()
                        val ringkasan = gson.fromJson(latestItem.content, RingkasanMateri::class.java)

                        summaryTitle.text = ringkasan?.topik ?: "Ringkasan"
                        summarySubject.text = "${latestItem.subject} • Kelas ${latestItem.grade ?: "X"}"
                        summaryContent.text = ringkasan?.konsepUtama?.take(120) + if ((ringkasan?.konsepUtama?.length ?: 0) > 120) "..." else ""

                        latestSummaryCard.setOnClickListener {
                            startActivity(Intent(requireContext(), HistoryActivity::class.java))
                        }
                    } catch (e: Exception) {
                        latestSummaryCard.visibility = View.GONE
                    }
                } else {
                    latestSummaryCard.visibility = View.GONE
                }
            }
        }
    }
}
