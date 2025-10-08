package com.christopheraldoo.adminwafeoffood.activities

import android.os.Bundle
import android.util.Log
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.christopheraldoo.adminwafeoffood.databinding.ActivityTestConnectionBinding
import com.christopheraldoo.adminwafeoffood.utils.FirebaseConnectionTester
import kotlinx.coroutines.launch

/**
 * Activity untuk testing koneksi Firebase
 * Debugging dan troubleshooting masalah koneksi
 */
class TestConnectionActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityTestConnectionBinding
    private val TAG = "TestConnectionActivity"
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityTestConnectionBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        setupUI()
        startConnectionTest()
    }
    
    private fun setupUI() {
        binding.apply {
            toolbar.title = "Firebase Connection Test"
            setSupportActionBar(toolbar)
            supportActionBar?.setDisplayHomeAsUpEnabled(true)
            
            btnRunTest.setOnClickListener {
                startConnectionTest()
            }
            
            btnClearLog.setOnClickListener {
                tvTestLog.text = ""
            }
        }
    }
    
    private fun startConnectionTest() {
        binding.apply {
            progressBar.visibility = android.view.View.VISIBLE
            btnRunTest.isEnabled = false
            tvTestResult.text = "Running tests..."
            tvTestLog.text = "🔥 Starting Firebase connection test...\n"
        }
        
        lifecycleScope.launch {
            try {
                Log.d(TAG, "Starting Firebase connection test...")
                
                val result = FirebaseConnectionTester.runCompleteConnectionTest()
                
                runOnUiThread {
                    binding.apply {
                        progressBar.visibility = android.view.View.GONE
                        btnRunTest.isEnabled = true
                        
                        if (result.success) {
                            tvTestResult.text = "✅ SUCCESS: ${result.message}"
                            tvTestResult.setTextColor(getColor(android.R.color.holo_green_dark))
                        } else {
                            tvTestResult.text = "❌ FAILED: ${result.message}"
                            tvTestResult.setTextColor(getColor(android.R.color.holo_red_dark))
                        }
                        
                        // Show detailed results
                        val logText = StringBuilder()
                        logText.append("🔥 Firebase Connection Test Results\n")
                        logText.append("==========================================\n\n")
                        
                        result.details.forEachIndexed { index, detail ->
                            logText.append("${index + 1}. $detail\n")
                        }
                        
                        logText.append("\n==========================================\n")
                        logText.append("Final Result: ${if (result.success) "✅ SUCCESS" else "❌ FAILED"}\n")
                        logText.append("Message: ${result.message}\n")
                        
                        tvTestLog.text = logText.toString()
                        
                        Log.d(TAG, "Test completed: ${result.message}")
                    }
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error running connection test", e)
                
                runOnUiThread {
                    binding.apply {
                        progressBar.visibility = android.view.View.GONE
                        btnRunTest.isEnabled = true
                        tvTestResult.text = "💥 ERROR: ${e.message}"
                        tvTestResult.setTextColor(getColor(android.R.color.holo_red_dark))
                        
                        tvTestLog.text = "💥 Exception occurred during test:\n${e.message}\n\nStack trace:\n${e.stackTraceToString()}"
                    }
                }
            }
        }
    }
    
    override fun onSupportNavigateUp(): Boolean {
        finish()
        return true
    }
}
