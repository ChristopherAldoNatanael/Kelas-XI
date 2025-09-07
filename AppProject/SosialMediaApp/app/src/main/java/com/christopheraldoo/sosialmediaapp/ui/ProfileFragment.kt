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
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.adapter.PostAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentProfileBinding
import com.christopheraldoo.sosialmediaapp.utils.Utils
import com.christopheraldoo.sosialmediaapp.viewmodel.ProfileViewModel

/**
 * Fragment untuk menampilkan profile pengguna
 * Menggunakan ViewBinding dan ViewModel untuk arsitektur MVVM
 */
class ProfileFragment : BaseFragment() {

    private var _binding: FragmentProfileBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: ProfileViewModel by viewModels()
    private lateinit var postAdapter: PostAdapter
    private var userId: String? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentProfileBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Get userId from arguments
        userId = arguments?.getString("userId")
        
        setupRecyclerView()
        setupClickListeners()
        observeViewModel()
        
        // Load user profile
        if (userId != null) {
            viewModel.loadUserProfile(userId!!)
        } else {
            // Load current user profile
            viewModel.loadCurrentUserProfile()
        }
    }

    private fun setupRecyclerView() {
        postAdapter = PostAdapter(
            onPostClick = { post ->
                // Navigate to reply fragment
                val bundle = Bundle().apply {
                    putString("postId", post.id)
                }
                findNavController().navigate(R.id.action_profile_to_reply, bundle)
            },
            onUserClick = { userId ->
                // Navigate to another user's profile
                if (userId != this.userId) {
                    val bundle = Bundle().apply {
                        putString("userId", userId)
                    }
                    findNavController().navigate(R.id.action_profile_to_profile, bundle)
                }
            },
            onLikeClick = { postId ->
                viewModel.togglePostLike(postId)
            },
            onRetweetClick = { postId ->
                // Handle retweet (could implement in ProfileViewModel)
            },
            onReplyClick = { postId ->
                // Navigate to reply fragment
                val bundle = Bundle().apply {
                    putString("postId", postId)
                }
                findNavController().navigate(R.id.action_profile_to_reply, bundle)
            },
            onShareClick = { postId ->
                // Implement share functionality
                sharePost(postId)
            }
        )
        
        binding.rvUserPosts.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = postAdapter
            setHasFixedSize(true)
        }
    }

    private fun setupClickListeners() {
        // Follow/Unfollow button
        binding.btnFollow.setOnClickListener {
            viewModel.toggleFollowStatus()
        }
        
        // Profile stats clicks (could navigate to followers/following lists)
        binding.llFollowing.setOnClickListener {
            // Could implement followers/following list navigation
        }
        
        binding.llFollowers.setOnClickListener {
            // Could implement followers/following list navigation
        }
    }

    private fun observeViewModel() {
        viewModel.user.observe(viewLifecycleOwner, Observer { user ->
            user?.let {
                // Populate user info
                binding.tvDisplayName.text = it.displayName
                binding.tvUsername.text = it.username
                binding.tvBio.text = if (it.bio.isNotEmpty()) it.bio else getString(R.string.placeholder_bio)
                
                // Update stats
                binding.tvFollowingCount.text = Utils.formatCount(it.following)
                binding.tvFollowersCount.text = Utils.formatCount(it.followers)
                
                // Update follow button
                updateFollowButton(it.isFollowing)
                
                // Set profile image (placeholder for now)
                // In real implementation, you would load image using library like Glide or Picasso
            }
        })
        
        viewModel.userPosts.observe(viewLifecycleOwner, Observer { posts ->
            postAdapter.submitList(posts)
            
            // Show/hide empty state
            if (posts.isNullOrEmpty()) {
                showEmptyPostsState()
            } else {
                hideEmptyPostsState()
            }
        })
          viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            if (isLoading) {
                binding.progressBar.visibility = View.VISIBLE
                binding.tvNoPosts.visibility = View.GONE
            } else {
                binding.progressBar.visibility = View.GONE
            }
        })
        
        viewModel.errorMessage.observe(viewLifecycleOwner, Observer { errorMessage ->
            if (!errorMessage.isNullOrEmpty()) {
                // Show error (could implement a Toast or Snackbar here)
                android.widget.Toast.makeText(context, errorMessage, android.widget.Toast.LENGTH_SHORT).show()
            }
        })
    }

    private fun updateFollowButton(isFollowing: Boolean) {
        if (userId == null) {
            // Hide follow button for current user profile
            binding.btnFollow.visibility = View.GONE
        } else {
            binding.btnFollow.visibility = View.VISIBLE
            if (isFollowing) {
                binding.btnFollow.text = getString(R.string.action_following)
                binding.btnFollow.isSelected = true
                binding.btnFollow.setTextColor(requireContext().getColor(R.color.text_primary))
            } else {
                binding.btnFollow.text = getString(R.string.action_follow)
                binding.btnFollow.isSelected = false
                binding.btnFollow.setTextColor(requireContext().getColor(R.color.text_white))
            }
        }
    }

    private fun showEmptyPostsState() {
        binding.tvNoPosts.visibility = View.VISIBLE
        binding.rvUserPosts.visibility = View.GONE
    }

    private fun hideEmptyPostsState() {
        binding.tvNoPosts.visibility = View.GONE
        binding.rvUserPosts.visibility = View.VISIBLE
    }

    private fun sharePost(postId: String) {
        // Simple share implementation
        val post = viewModel.userPosts.value?.find { it.id == postId }
        post?.let {
            val shareText = "${it.user.displayName} (${it.user.username}): ${it.content}"
            val shareIntent = android.content.Intent().apply {
                action = android.content.Intent.ACTION_SEND
                type = "text/plain"
                putExtra(android.content.Intent.EXTRA_TEXT, shareText)
            }
            startActivity(android.content.Intent.createChooser(shareIntent, "Share post"))
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
