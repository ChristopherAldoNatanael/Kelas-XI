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
import com.christopheraldoo.sosialmediaapp.adapter.ChatMessageAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentChatBinding
import com.christopheraldoo.sosialmediaapp.viewmodel.ChatViewModel

/**
 * Fragment untuk chat detail/percakapan individual
 */
class ChatFragment : BaseFragment() {

    private var _binding: FragmentChatBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: ChatViewModel by viewModels()
    private lateinit var chatAdapter: ChatMessageAdapter
    
    private var conversationId: String? = null
    private var otherUserId: String? = null
    private var otherUserName: String? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentChatBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            // Get arguments
            conversationId = arguments?.getString("conversationId")
            otherUserId = arguments?.getString("otherUserId")
            otherUserName = arguments?.getString("otherUserName")
            
            if (conversationId == null || otherUserId == null) {
                showError("Chat information not found")
                return
            }
            
            setupToolbar()
            setupRecyclerView()
            setupMessageInput()
            observeViewModel()
            
            // Load messages
            conversationId?.let { viewModel.loadMessages(it) }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error loading chat: ${e.message}")
        }
    }    private fun setupToolbar() {
        try {
            binding.tvChatTitle.text = otherUserName ?: "Chat"
              binding.ivBack.setOnClickListener {
                findNavController().popBackStack()
            }
            
            binding.ivUserProfile.setOnClickListener {
                otherUserId?.let { userId ->
                    val bundle = Bundle().apply {
                        putString("userId", userId)
                    }
                    findNavController().navigate(R.id.action_chat_to_profile, bundle)
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up toolbar: ${e.message}")
        }
    }

    private fun setupRecyclerView() {
        chatAdapter = ChatMessageAdapter()
        
        binding.rvMessages.apply {
            layoutManager = LinearLayoutManager(context).apply {
                reverseLayout = true
                stackFromEnd = true
            }
            adapter = chatAdapter
            setHasFixedSize(true)
        }
    }

    private fun setupMessageInput() {
        binding.etMessage.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            
            override fun afterTextChanged(s: Editable?) {
                val hasText = s.toString().trim().isNotEmpty()
                binding.ivSend.isEnabled = hasText
                binding.ivSend.alpha = if (hasText) 1.0f else 0.5f
            }
        })
        
        binding.ivSend.setOnClickListener {
            sendMessage()
        }
        
        binding.ivSend.isEnabled = false
        binding.ivSend.alpha = 0.5f
    }    private fun sendMessage() {
        try {
            val messageText = binding.etMessage.text.toString().trim()
            if (messageText.isNotEmpty()) {
                conversationId?.let { convId ->
                    viewModel.sendMessage(convId, messageText)
                    binding.etMessage.text.clear()
                    
                    // Scroll to bottom
                    binding.rvMessages.scrollToPosition(0)
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error sending message: ${e.message}")
        }
    }

    private fun observeViewModel() {
        viewModel.messages.observe(viewLifecycleOwner, Observer { messages ->
            chatAdapter.submitList(messages.reversed()) // Most recent at bottom
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        })
        
        viewModel.error.observe(viewLifecycleOwner, Observer { error ->
            error?.let {
                Toast.makeText(context, it, Toast.LENGTH_SHORT).show()
            }
        })
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
