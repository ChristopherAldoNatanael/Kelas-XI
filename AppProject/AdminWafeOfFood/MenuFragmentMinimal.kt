package com.christopheraldoo.adminwafeoffood.fragments

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.fragment.app.Fragment

class MenuFragmentMinimal : Fragment() {

    companion object {
        private const val TAG = "MenuFragmentMinimal"
        
        fun newInstance(): MenuFragmentMinimal {
            return MenuFragmentMinimal()
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        Log.d(TAG, "onCreateView called")
        
        // Return a simple TextView instead of complex layout
        return TextView(requireContext()).apply {
            text = "Menu Fragment Loaded Successfully!\n\nThis is a minimal version to test if the crash is from layout or code complexity."
            textSize = 16f
            setPadding(32, 32, 32, 32)
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called - Fragment is working!")
    }

    override fun onResume() {
        super.onResume()
        Log.d(TAG, "onResume called")
    }

    override fun onPause() {
        super.onPause()
        Log.d(TAG, "onPause called")
    }

    override fun onDestroyView() {
        super.onDestroyView()
        Log.d(TAG, "onDestroyView called")
    }
}
