package com.christopheraldoo.sosialmediaapp.ui

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.Observer
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.adapter.ReplyAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentReplyBinding
import com.christopheraldoo.sosialmediaapp.utils.Utils
import com.christopheraldoo.sosialmediaapp.viewmodel.ReplyViewModel

/**
 * Fragment untuk menampilkan detail post dan thread replies
 * Menggunakan ViewBinding dan ViewModel untuk arsitektur MVVM
 */
class ReplyFragment : BaseFragment() {

    private var _binding: FragmentReplyBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: ReplyViewModel by viewModels()
    private lateinit var replyAdapter: ReplyAdapter
    private var postId: String? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentReplyBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            // Get postId from arguments
            postId = arguments?.getString("postId")
            
            if (postId == null) {
                // Handle case when postId is null
                showError("Post not found")
                return
            }
            
            setupRecyclerView()
            setupReplyInput()
            setupClickListeners()
            observeViewModel()
            
            // Load post and replies
            postId?.let { viewModel.loadPostAndReplies(it) }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error loading thread: ${e.message}")
        }
    }    private fun setupRecyclerView() {
        try {
            replyAdapter = ReplyAdapter(
                onUserClick = { userId ->
                    try {
                        // Navigate to profile fragment
                        val bundle = Bundle().apply {
                            putString("userId", userId)
                        }
                        findNavController().navigate(R.id.action_reply_to_profile, bundle)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Navigation error: ${e.message}")
                    }
                },
                onLikeClick = { replyId ->
                    try {
                        viewModel.toggleReplyLike(replyId)
                    } catch (e: Exception) {
                        e.printStackTrace()
                        showError("Error toggling like: ${e.message}")
                    }
                }
            )
            
            binding.rvReplies.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = replyAdapter
                setHasFixedSize(true)
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up replies: ${e.message}")
        }
    }

    private fun setupReplyInput() {
        // Enable/disable reply button based on text input
        binding.etReplyContent.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}

            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}

            override fun afterTextChanged(s: Editable?) {
                binding.btnReply.isEnabled = !s.isNullOrBlank()
            }
        })
    }    private fun setupClickListeners() {
        try {
            // Original post like button
            binding.llLike.setOnClickListener {
                try {
                    viewModel.togglePostLike()
                } catch (e: Exception) {
                    e.printStackTrace()
                    showError("Error toggling like: ${e.message}")
                }
            }
            
            // Reply button
            binding.btnReply.setOnClickListener {
                try {
                    val content = binding.etReplyContent.text.toString().trim()
                    if (content.isNotEmpty()) {
                        viewModel.addReply(content)
                        binding.etReplyContent.text.clear()
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                    showError("Error sending reply: ${e.message}")
                }
            }
              // Profile clicks for original post
            binding.ivProfileImage.setOnClickListener {
                viewModel.post.value?.let { post ->
                    val bundle = Bundle().apply {
                        putString("userId", post.user.id)
                    }
                    findNavController().navigate(R.id.action_reply_to_profile, bundle)
                }
            }
            
            binding.tvDisplayName.setOnClickListener {
                viewModel.post.value?.let { post ->
                    val bundle = Bundle().apply {
                        putString("userId", post.user.id)
                    }
                    findNavController().navigate(R.id.action_reply_to_profile, bundle)
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up click listeners: ${e.message}")
        }
    }

    private fun observeViewModel() {
        viewModel.post.observe(viewLifecycleOwner, Observer { post ->
            post?.let { 
                // Populate original post
                binding.tvDisplayName.text = it.user.displayName
                binding.tvUsername.text = it.user.username
                binding.tvTimestamp.text = Utils.formatTimeAgo(it.timestamp)
                binding.tvContent.text = it.content
                
                // Update reply count
                val replyCountText = if (it.replyCount > 0) {
                    "${Utils.formatCount(it.replyCount)} replies"
                } else {
                    "0 replies"
                }
                binding.tvReplyCount.text = replyCountText
                
                // Update like state
                if (it.isLiked) {
                    binding.ivLike.setImageResource(R.drawable.ic_like_filled)
                    binding.tvLikeCount.setTextColor(requireContext().getColor(R.color.like_red))
                } else {
                    binding.ivLike.setImageResource(R.drawable.ic_like)
                    binding.tvLikeCount.setTextColor(requireContext().getColor(R.color.text_secondary))
                }
                
                binding.tvLikeCount.text = if (it.likeCount > 0) Utils.formatCount(it.likeCount) else ""
            }
        })
        
        viewModel.replies.observe(viewLifecycleOwner, Observer { replies ->
            replyAdapter.submitList(replies)
            
            // Show/hide empty state for replies
            if (replies.isNullOrEmpty()) {
                binding.tvNoReplies.visibility = View.VISIBLE
            } else {
                binding.tvNoReplies.visibility = View.GONE
            }
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            if (isLoading) {
                binding.progressBarReplies.visibility = View.VISIBLE
                binding.tvNoReplies.visibility = View.GONE
            } else {
                binding.progressBarReplies.visibility = View.GONE
            }
        })
        
        viewModel.errorMessage.observe(viewLifecycleOwner, Observer { errorMessage ->
            if (!errorMessage.isNullOrEmpty()) {
                // Show error (could implement a Toast or Snackbar here)
                android.widget.Toast.makeText(context, errorMessage, android.widget.Toast.LENGTH_SHORT).show()
            }
        })
    }

    private fun showError(message: String) {
        try {
            android.widget.Toast.makeText(context, message, android.widget.Toast.LENGTH_LONG).show()
            // Optionally navigate back or hide loading states
            binding.progressBarReplies.visibility = View.GONE
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
