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
import com.christopheraldoo.sosialmediaapp.adapter.NotificationAdapter
import com.christopheraldoo.sosialmediaapp.databinding.FragmentNotificationsBinding
import com.christopheraldoo.sosialmediaapp.viewmodel.NotificationViewModel

/**
 * Fragment untuk notifikasi
 * Mirip dengan Notifications tab di X/Twitter
 */
class NotificationsFragment : BaseFragment() {

    private var _binding: FragmentNotificationsBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: NotificationViewModel by viewModels()
    private lateinit var notificationAdapter: NotificationAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentNotificationsBinding.inflate(inflater, container, false)
        return binding.root
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        try {
            setupRecyclerView()
            setupSwipeRefresh()
            setupTabs()
            observeViewModel()
            
            // Load notifications
            viewModel.loadNotifications()
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error initializing notifications: ${e.message}")
        }
    }    private fun setupRecyclerView() {
        try {
            notificationAdapter = NotificationAdapter(                onNotificationClick = { notification ->
                    when (notification.type) {
                        "like", "retweet", "reply" -> {
                            // Navigate to post
                            if (!notification.relatedId.isNullOrEmpty()) {
                                val bundle = Bundle().apply {
                                    putString("postId", notification.relatedId)
                                }
                                findNavController().navigate(R.id.action_notifications_to_reply, bundle)
                            } else {
                                showError("Post not found")
                            }
                        }                            "follow" -> {
                            // Navigate to user profile
                            val bundle = Bundle().apply {
                                putString("userId", notification.fromUser.id)
                            }
                            findNavController().navigate(R.id.action_notifications_to_profile, bundle)
                        }
                        else -> {
                            showError("Unknown notification type")
                        }
                    }
                },                onUserClick = { userId ->
                    if (!userId.isNullOrEmpty()) {
                        val bundle = Bundle().apply {
                            putString("userId", userId)
                        }
                        findNavController().navigate(R.id.action_notifications_to_profile, bundle)
                    } else {
                        showError("User not found")
                    }
                }
            )
            
            binding.rvNotifications.apply {
                layoutManager = LinearLayoutManager(context)
                adapter = notificationAdapter
                setHasFixedSize(true)
            }
        } catch (e: Exception) {
            e.printStackTrace()
            showError("Error setting up notifications: ${e.message}")
        }
    }

    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            viewModel.refreshNotifications()
        }
    }

    private fun setupTabs() {
        binding.tvAllTab.setOnClickListener {
            selectTab("all")
        }
        
        binding.tvMentionsTab.setOnClickListener {
            selectTab("mentions")
        }
    }

    private fun selectTab(tabType: String) {
        // Reset tab styles
        binding.tvAllTab.setTextColor(resources.getColor(R.color.text_secondary, null))
        binding.tvMentionsTab.setTextColor(resources.getColor(R.color.text_secondary, null))
        
        binding.viewAllIndicator.visibility = View.INVISIBLE
        binding.viewMentionsIndicator.visibility = View.INVISIBLE
        
        // Highlight selected tab
        when (tabType) {
            "all" -> {
                binding.tvAllTab.setTextColor(resources.getColor(R.color.twitter_blue, null))
                binding.viewAllIndicator.visibility = View.VISIBLE
                viewModel.filterNotifications("all")
            }
            "mentions" -> {
                binding.tvMentionsTab.setTextColor(resources.getColor(R.color.twitter_blue, null))
                binding.viewMentionsIndicator.visibility = View.VISIBLE
                viewModel.filterNotifications("mentions")
            }
        }
    }

    private fun observeViewModel() {
        viewModel.notifications.observe(viewLifecycleOwner, Observer { notifications ->
            notificationAdapter.submitList(notifications)
            
            if (notifications.isEmpty()) {
                showEmptyState()
            } else {
                hideEmptyState()
            }
        })
        
        viewModel.isLoading.observe(viewLifecycleOwner, Observer { isLoading ->
            binding.swipeRefresh.isRefreshing = isLoading
        })
        
        viewModel.unreadCount.observe(viewLifecycleOwner, Observer { count ->
            // Update badge or indicator if needed
        })
    }

    private fun showEmptyState() {
        binding.rvNotifications.visibility = View.GONE
        binding.llEmptyState.visibility = View.VISIBLE
    }

    private fun hideEmptyState() {
        binding.rvNotifications.visibility = View.VISIBLE
        binding.llEmptyState.visibility = View.GONE
    }

    private fun showError(message: String) {
        try {
            android.widget.Toast.makeText(context, message, android.widget.Toast.LENGTH_LONG).show()
            // Hide loading states
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
