package com.christopheraldoo.petheal.service

import android.app.Activity
import android.app.Application
import android.os.Bundle
import java.util.concurrent.atomic.AtomicInteger

/**
 * Tahu apakah ada Activity PetHeal yang sedang terlihat.
 *
 * Dipakai untuk memilih cara mengumumkan panggilan antrian:
 *  - aplikasi terlihat  -> Text-to-Speech (suara Indonesia) dari service,
 *  - aplikasi di background/terminated -> notifikasi FCM dengan suara channel.
 *
 * Memakai `ActivityLifecycleCallbacks` supaya tidak menambah dependency
 * lifecycle-process hanya untuk satu flag boolean.
 */
object AppVisibilityTracker : Application.ActivityLifecycleCallbacks {

    private val startedActivities = AtomicInteger(0)

    val isForeground: Boolean
        get() = startedActivities.get() > 0

    fun register(application: Application) {
        application.registerActivityLifecycleCallbacks(this)
    }

    override fun onActivityStarted(activity: Activity) {
        startedActivities.incrementAndGet()
    }

    override fun onActivityStopped(activity: Activity) {
        startedActivities.updateAndGet { current -> if (current > 0) current - 1 else 0 }
    }

    override fun onActivityCreated(activity: Activity, savedInstanceState: Bundle?) = Unit
    override fun onActivityResumed(activity: Activity) = Unit
    override fun onActivityPaused(activity: Activity) = Unit
    override fun onActivitySaveInstanceState(activity: Activity, outState: Bundle) = Unit
    override fun onActivityDestroyed(activity: Activity) = Unit
}
