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
import com.christopheraldoo.sosialmediaapp.adapter.MessageAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentMessagesBinding
import com.christopheraldoo.sosialmediaapp.viewmodel.MessagesViewModel

/**
 * Fragment untuk Direct Messages
 * Mirip dengan Messages di X/Twitter
 */
class MessagesFragment : BaseFragment() {

    private var _binding: FragmentMessagesBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: MessagesViewModel by viewModels()
    private lateinit var messageAdapter: MessageAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentMessagesBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            setupRecyclerView()
            setupSearchView()
            setupClickListeners()
            observeViewModel()
            
            // Load conversations
            viewModel.loadConversations()
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error initializing messages: ${e.message}")
        }
    }    private fun setupRecyclerView() {
        try {
            messageAdapter = MessageAdapter(                onConversationClick = { conversation ->
                    // Navigate to chat detail
                    val bundle = Bundle().apply {
                        putString("conversationId", conversation.id)
                        putString("otherUserId", conversation.otherUser.id)
                        putString("otherUserName", conversation.otherUser.displayName)
                    }
                    findNavController().navigate(R.id.action_messages_to_chat, bundle)
                },                onUserClick = { userId ->
                    if (!userId.isNullOrEmpty()) {
                        val bundle = Bundle().apply {
                            putString("userId", userId)
                        }
                        findNavController().navigate(R.id.action_messages_to_profile, bundle)
                    } else {
                        showError("User not found")
                    }
                }
            )
            
            binding.rvConversations.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = messageAdapter
                setHasFixedSize(true)
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up conversations: ${e.message}")
        }
    }

    private fun setupSearchView() {
        binding.etSearchMessages.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            
            override fun afterTextChanged(s: Editable?) {
                val query = s.toString().trim()
                viewModel.searchConversations(query)
            }
        })
    }

    private fun setupClickListeners() {
        binding.fabNewMessage.setOnClickListener {
            // Navigate to new message/user selection
            Toast.makeText(context, "New message functionality", Toast.LENGTH_SHORT).show()
        }
    }

    private fun observeViewModel() {
        viewModel.conversations.observe(viewLifecycleOwner, Observer { conversations ->
            messageAdapter.submitList(conversations)
            
            if (conversations.isEmpty()) {
                showEmptyState()
            } else {
                hideEmptyState()
            }
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        })
        
        viewModel.unreadCount.observe(viewLifecycleOwner, Observer { count ->
            // Update unread message count if needed
        })
    }

    private fun showEmptyState() {
        binding.rvConversations.visibility = View.GONE
        binding.llEmptyState.visibility = View.VISIBLE
    }

    private fun hideEmptyState() {
        binding.rvConversations.visibility = View.VISIBLE
        binding.llEmptyState.visibility = View.GONE
    }

    private fun showError(message: String) {
        try {
            Toast.makeText(context, message, Toast.LENGTH_LONG).show()
            // Hide loading states
            binding.progressBar.visibility = View.GONE
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
