package com.christopheraldoo.earningquizapp.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.Subject

/**
 * Adapter for the subjects RecyclerView in HomeFragment.
 *
 * This adapter takes a list of Subject objects and binds them to the
 * `item_subject` layout for display in a grid.
 */
class SubjectAdapter(private val subjects: List<Subject>) : RecyclerView.Adapter<SubjectAdapter.SubjectViewHolder>() {

    // Listener for item clicks
    private var onItemClickListener: ((Subject) -> Unit)? = null

    fun setOnItemClickListener(listener: (Subject) -> Unit) {
        onItemClickListener = listener
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): SubjectViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_subject, parent, false)
        return SubjectViewHolder(view)
    }

    override fun onBindViewHolder(holder: SubjectViewHolder, position: Int) {
        val subject = subjects[position]
        holder.bind(subject)
    }

    override fun getItemCount(): Int = subjects.size

    inner class SubjectViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val ivIcon: ImageView = itemView.findViewById(R.id.iv_subject_icon)
        private val tvName: TextView = itemView.findViewById(R.id.tv_subject_name)

        fun bind(subject: Subject) {
            tvName.text = subject.name
            ivIcon.setImageResource(subject.iconResId)

            itemView.setOnClickListener {
                onItemClickListener?.invoke(subject)
            }
        }
    }
}
