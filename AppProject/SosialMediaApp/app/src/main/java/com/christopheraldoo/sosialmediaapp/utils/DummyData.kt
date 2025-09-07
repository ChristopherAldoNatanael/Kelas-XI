package com.christopheraldoo.sosialmediaapp.utils

import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.model.Reply
import com.christopheraldoo.sosialmediaapp.model.User
import com.christopheraldoo.sosialmediaapp.model.Notification
import com.christopheraldoo.sosialmediaapp.model.Conversation
import com.christopheraldoo.sosialmediaapp.model.Message

/**
 * Object untuk menyediakan dummy data untuk testing dan development
 * Berisi sample data user, post, dan reply
 */
object DummyData {
    
    val users = listOf(
        User(
            id = "1",
            username = "@john_doe",
            displayName = "John Doe",
            bio = "Software Engineer | Tech Enthusiast | Coffee Lover ☕",
            profileImageUrl = "",
            followers = 1250,
            following = 320
        ),
        User(
            id = "2",
            username = "@jane_smith",
            displayName = "Jane Smith",
            bio = "UI/UX Designer | Creative Mind | Nature Lover 🌿",
            profileImageUrl = "",
            followers = 890,
            following = 210
        ),
        User(
            id = "3",
            username = "@tech_guru",
            displayName = "Tech Guru",
            bio = "Tech News & Reviews | Gadget Enthusiast | Always Learning 📱",
            profileImageUrl = "",
            followers = 5420,
            following = 100
        ),
        User(
            id = "4",
            username = "@design_pro",
            displayName = "Design Pro",
            bio = "Professional Designer | Brand Identity | Visual Storytelling ✨",
            profileImageUrl = "",
            followers = 2100,
            following = 450
        )
    )
    
    // Mutable posts untuk bisa diupdate saat testing
    var posts = listOf(
        Post(
            id = "1",
            user = users[0],
            content = "Just finished working on an amazing Android project! The new Kotlin features are incredible. #Android #Kotlin #Development",
            timestamp = System.currentTimeMillis() - 3600000, // 1 hour ago
            likeCount = 42,
            replyCount = 8,
            retweetCount = 12
        ),
        Post(
            id = "2",
            user = users[1],
            content = "Beautiful sunset today! Sometimes you need to step away from the screen and appreciate nature 🌅 #Nature #Photography #Peace",
            timestamp = System.currentTimeMillis() - 7200000, // 2 hours ago
            likeCount = 128,
            replyCount = 15,
            retweetCount = 34
        ),
        Post(
            id = "3",
            user = users[2],
            content = "The latest smartphone release is mind-blowing! The camera quality and performance improvements are game-changing. What do you think? 📱",
            timestamp = System.currentTimeMillis() - 10800000, // 3 hours ago
            likeCount = 256,
            replyCount = 45,
            retweetCount = 89
        ),
        Post(
            id = "4",
            user = users[3],
            content = "Working on a new brand identity project. The creative process is so fulfilling! Here's a sneak peek of the color palette 🎨",
            timestamp = System.currentTimeMillis() - 14400000, // 4 hours ago
            likeCount = 87,
            replyCount = 12,
            retweetCount = 23
        ),
        Post(
            id = "5",
            user = users[0],
            content = "Quick tip for fellow developers: Always write clean, readable code. Your future self will thank you! 💻 #Programming #Tips",
            timestamp = System.currentTimeMillis() - 18000000, // 5 hours ago
            likeCount = 95,
            replyCount = 18,
            retweetCount = 31
        )
    )
    
    val replies = mapOf(
        "1" to listOf(
            Reply(
                id = "r1",
                postId = "1",
                user = users[1],
                content = "Totally agree! Kotlin has made Android development so much more enjoyable.",
                timestamp = System.currentTimeMillis() - 3000000,
                likeCount = 12
            ),
            Reply(
                id = "r2",
                postId = "1",
                user = users[2],
                content = "Can't wait to try the new coroutines features!",
                timestamp = System.currentTimeMillis() - 2700000,
                likeCount = 8
            )
        ),
        "2" to listOf(
            Reply(
                id = "r3",
                postId = "2",
                user = users[0],
                content = "Absolutely stunning! Where was this taken?",
                timestamp = System.currentTimeMillis() - 6900000,
                likeCount = 5
            ),
            Reply(
                id = "r4",
                postId = "2",
                user = users[3],
                content = "Nature is the best inspiration for design! 🌟",
                timestamp = System.currentTimeMillis() - 6600000,
                likeCount = 7
            )
        ),
        "3" to listOf(
            Reply(
                id = "r5",
                postId = "3",
                user = users[1],
                content = "I've been waiting for this release! The specs look amazing.",
                timestamp = System.currentTimeMillis() - 10200000,
                likeCount = 15
            ),
            Reply(
                id = "r6",
                postId = "3",
                user = users[0],
                content = "The battery life improvements are what I'm most excited about!",
                timestamp = System.currentTimeMillis() - 9900000,
                likeCount = 11            )
        )
    )
    
    // Notifications data
    val notifications = listOf(
        Notification(
            id = "n1",
            type = "like",
            fromUser = users[1], // Jane Smith
            relatedId = "p1",
            message = "liked your post",
            timestamp = System.currentTimeMillis() - 300000, // 5 minutes ago
            isRead = false
        ),
        Notification(
            id = "n2",
            type = "retweet",
            fromUser = users[2], // Tech Guru
            relatedId = "p2",
            message = "retweeted your post",
            timestamp = System.currentTimeMillis() - 600000, // 10 minutes ago
            isRead = false
        ),
        Notification(
            id = "n3",
            type = "follow",
            fromUser = users[3], // Design Pro
            relatedId = null,
            message = "started following you",
            timestamp = System.currentTimeMillis() - 1800000, // 30 minutes ago
            isRead = true
        ),
        Notification(
            id = "n4",
            type = "reply",
            fromUser = users[1],
            relatedId = "p3",
            message = "replied to your post",
            timestamp = System.currentTimeMillis() - 3600000, // 1 hour ago
            isRead = true
        ),
        Notification(
            id = "n5",
            type = "mention",
            fromUser = users[2],
            relatedId = "p4",
            message = "mentioned you in a post",
            timestamp = System.currentTimeMillis() - 7200000, // 2 hours ago
            isRead = true
        ),
        Notification(
            id = "n6",
            type = "like",
            fromUser = users[3],
            relatedId = "p1",
            message = "liked your post",
            timestamp = System.currentTimeMillis() - 86400000, // 1 day ago
            isRead = true
        )
    )
    
    // Messages and Conversations data
    val conversations = listOf(
        Conversation(
            id = "c1",
            otherUser = users[1], // Jane Smith
            lastMessage = Message(
                id = "msg1",
                conversationId = "c1",
                fromUser = users[1],
                toUser = users[0],
                content = "Hey! How's the new project going?",
                timestamp = System.currentTimeMillis() - 600000, // 10 minutes ago
                isRead = false
            ),
            unreadCount = 2,
            timestamp = System.currentTimeMillis() - 600000
        ),
        Conversation(
            id = "c2",
            otherUser = users[2], // Tech Guru
            lastMessage = Message(
                id = "msg2",
                conversationId = "c2",
                fromUser = users[0],
                toUser = users[2],
                content = "Thanks for sharing that article!",
                timestamp = System.currentTimeMillis() - 3600000, // 1 hour ago
                isRead = true
            ),
            unreadCount = 0,
            timestamp = System.currentTimeMillis() - 3600000
        ),
        Conversation(
            id = "c3",
            otherUser = users[3], // Design Pro
            lastMessage = Message(
                id = "msg3",
                conversationId = "c3",
                fromUser = users[3],
                toUser = users[0],
                content = "Love your latest design! 🎨",
                timestamp = System.currentTimeMillis() - 86400000, // 1 day ago
                isRead = false
            ),
            unreadCount = 1,
            timestamp = System.currentTimeMillis() - 86400000
        )
    )
}
