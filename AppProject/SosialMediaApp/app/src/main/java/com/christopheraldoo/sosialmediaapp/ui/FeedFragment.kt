package com.christopheraldoo.sosialmediaapp.ui

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.Observer
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.adapter.PostAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentFeedBinding
import com.christopheraldoo.sosialmediaapp.viewmodel.FeedViewModel

/**
 * Fragment untuk menampilkan feed/timeline utama
 * Menggunakan ViewBinding dan ViewModel untuk arsitektur MVVM
 */
class FeedFragment : BaseFragment() {

    private var _binding: FragmentFeedBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: FeedViewModel by viewModels()
    private lateinit var postAdapter: PostAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentFeedBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        setupRecyclerView()
        setupSwipeRefresh()
        setupClickListeners()
        observeViewModel()
    }    private fun setupRecyclerView() {
        try {
            postAdapter = PostAdapter(
                onPostClick = { post ->
                    try {
                        // Navigate to reply fragment
                        val bundle = Bundle().apply {
                            putString("postId", post.id)
                        }
                        findNavController().navigate(R.id.action_feed_to_reply, bundle)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Navigation error: ${e.message}")
                    }
                },                onUserClick = { userId ->
                    // Navigate to profile fragment
                    val bundle = Bundle().apply {
                        putString("userId", userId)
                    }
                    safeNavigate(R.id.action_feed_to_profile, bundle)
                },
                onLikeClick = { postId ->
                    try {
                        viewModel.toggleLike(postId)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Error toggling like: ${e.message}")
                    }
                },
                onRetweetClick = { postId ->
                    try {
                        viewModel.toggleRetweet(postId)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Error retweeting: ${e.message}")
                    }
                },                onReplyClick = { postId ->
                    // Navigate to reply fragment
                    val bundle = Bundle().apply {
                        putString("postId", postId)
                    }
                    findNavController().navigate(R.id.action_feed_to_reply, bundle)
                },
                onShareClick = { postId ->
                    try {
                        // Implement share functionality
                        sharePost(postId)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Error sharing post: ${e.message}")
                    }
                }
            )
            
            binding.rvPosts.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = postAdapter
                setHasFixedSize(true)
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up feed: ${e.message}")
        }
    }

    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            viewModel.refreshPosts()
        }
        
        // Set color scheme for refresh indicator
        binding.swipeRefresh.setColorSchemeResources(
            R.color.primary_blue,
            R.color.primary_blue_dark
        )
    }

    private fun setupClickListeners() {
        binding.btnRetry.setOnClickListener {
            viewModel.refreshPosts()
        }
    }

    private fun observeViewModel() {
        viewModel.posts.observe(viewLifecycleOwner, Observer { posts ->
            postAdapter.submitList(posts)
            
            // Show/hide empty state
            if (posts.isNullOrEmpty()) {
                showEmptyState()
            } else {
                hideEmptyState()
            }
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            binding.swipeRefresh.isRefreshing = isLoading
            
            if (isLoading) {
                binding.progressBar.visibility = View.VISIBLE
                binding.llError.visibility = View.GONE
                binding.llEmpty.visibility = View.GONE
            } else {
                binding.progressBar.visibility = View.GONE
            }
        })
        
        viewModel.errorMessage.observe(viewLifecycleOwner, Observer { errorMessage ->
            if (!errorMessage.isNullOrEmpty()) {
                showErrorState(errorMessage)
            } else {
                hideErrorState()
            }
        })
    }

    private fun showEmptyState() {
        binding.llEmpty.visibility = View.VISIBLE
        binding.rvPosts.visibility = View.GONE
        binding.llError.visibility = View.GONE
    }

    private fun hideEmptyState() {
        binding.llEmpty.visibility = View.GONE
        binding.rvPosts.visibility = View.VISIBLE
    }

    private fun showErrorState(message: String) {
        binding.llError.visibility = View.VISIBLE
        binding.tvErrorMessage.text = message
        binding.rvPosts.visibility = View.GONE
        binding.llEmpty.visibility = View.GONE
    }

    private fun hideErrorState() {
        binding.llError.visibility = View.GONE
        binding.rvPosts.visibility = View.VISIBLE
    }

    private fun sharePost(postId: String) {
        // Simple share implementation
        val post = viewModel.posts.value?.find { it.id == postId }
        post?.let {
            val shareText = "${it.user.displayName} (${it.user.username}): ${it.content}"
            val shareIntent = android.content.Intent().apply {
                action = android.content.Intent.ACTION_SEND
                type = "text/plain"
                putExtra(android.content.Intent.EXTRA_TEXT, shareText)
            }
            startActivity(android.content.Intent.createChooser(shareIntent, "Share post"))
        }
    }    private fun showError(message: String) {
        try {
            android.widget.Toast.makeText(context, message, android.widget.Toast.LENGTH_LONG).show()
            // Hide loading states
            binding.progressBar.visibility = View.GONE
            binding.swipeRefresh.isRefreshing = false
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
