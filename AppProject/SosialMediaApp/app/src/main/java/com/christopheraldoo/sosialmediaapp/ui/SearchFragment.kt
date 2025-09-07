package com.christopheraldoo.sosialmediaapp.ui

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.Observer
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.adapter.PostAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentSearchBinding
import com.christopheraldoo.sosialmediaapp.viewmodel.SearchViewModel

/**
 * Fragment untuk fitur pencarian dan explore
 * Mirip dengan Search & Explore tab di X/Twitter
 */
class SearchFragment : BaseFragment() {

    private var _binding: FragmentSearchBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: SearchViewModel by viewModels()
    private lateinit var postAdapter: PostAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentSearchBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        setupRecyclerView()
        setupSearchView()
        setupTrending()
        observeViewModel()
        
        // Load initial trending data
        viewModel.loadTrendingData()
    }

    private fun setupRecyclerView() {
        postAdapter = PostAdapter(
            onPostClick = { post ->
                val bundle = Bundle().apply {
                    putString("postId", post.id)
                }
                findNavController().navigate(R.id.action_search_to_reply, bundle)
            },
            onUserClick = { userId ->
                val bundle = Bundle().apply {
                    putString("userId", userId)
                }
                findNavController().navigate(R.id.action_search_to_profile, bundle)
            },
            onLikeClick = { postId ->
                viewModel.togglePostLike(postId)
            },
            onRetweetClick = { postId ->
                Toast.makeText(context, "Retweet functionality", Toast.LENGTH_SHORT).show()
            },
            onReplyClick = { postId ->
                val bundle = Bundle().apply {
                    putString("postId", postId)
                }
                findNavController().navigate(R.id.action_search_to_reply, bundle)
            },
            onShareClick = { postId ->
                sharePost(postId)
            }
        )
        
        binding.rvSearchResults.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = postAdapter
            setHasFixedSize(true)
        }
    }

    private fun setupSearchView() {
        binding.etSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            
            override fun afterTextChanged(s: Editable?) {
                val query = s.toString().trim()
                if (query.isNotEmpty()) {
                    viewModel.searchPosts(query)
                    binding.llTrending.visibility = View.GONE
                    binding.rvSearchResults.visibility = View.VISIBLE
                } else {
                    binding.llTrending.visibility = View.VISIBLE
                    binding.rvSearchResults.visibility = View.GONE
                    viewModel.clearSearch()
                }
            }
        })
    }

    private fun setupTrending() {
        // Setup trending topics click listeners
        binding.tvTrending1.setOnClickListener {
            performTrendingSearch("#Android")
        }
        
        binding.tvTrending2.setOnClickListener {
            performTrendingSearch("#Kotlin")
        }
        
        binding.tvTrending3.setOnClickListener {
            performTrendingSearch("#Development")
        }
        
        binding.tvTrending4.setOnClickListener {
            performTrendingSearch("#Technology")
        }
        
        binding.tvTrending5.setOnClickListener {
            performTrendingSearch("#Programming")
        }
    }

    private fun performTrendingSearch(hashtag: String) {
        binding.etSearch.setText(hashtag)
        viewModel.searchPosts(hashtag)
        binding.llTrending.visibility = View.GONE
        binding.rvSearchResults.visibility = View.VISIBLE
    }

    private fun observeViewModel() {
        viewModel.searchResults.observe(viewLifecycleOwner, Observer { posts ->
            postAdapter.submitList(posts)
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        })
        
        viewModel.trendingTopics.observe(viewLifecycleOwner, Observer { trending ->
            // Update trending topics if needed
            trending?.let {
                if (it.isNotEmpty()) {
                    binding.tvTrending1.text = it.getOrNull(0) ?: "#Android"
                    binding.tvTrending2.text = it.getOrNull(1) ?: "#Kotlin"
                    binding.tvTrending3.text = it.getOrNull(2) ?: "#Development"
                    binding.tvTrending4.text = it.getOrNull(3) ?: "#Technology"
                    binding.tvTrending5.text = it.getOrNull(4) ?: "#Programming"
                }
            }
        })
    }

    private fun sharePost(postId: String) {
        // Implement share functionality
        Toast.makeText(context, "Share post: $postId", Toast.LENGTH_SHORT).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
