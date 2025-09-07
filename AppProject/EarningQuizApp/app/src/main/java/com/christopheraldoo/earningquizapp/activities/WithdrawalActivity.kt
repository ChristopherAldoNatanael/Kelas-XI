package com.christopheraldoo.earningquizapp.activities

import android.os.Bundle
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager

/**
 * Activity for simulating point withdrawal.
 *
 * This screen allows users to simulate withdrawing their earned points
 * to various payment methods. This is frontend-only simulation.
 */
class WithdrawalActivity : AppCompatActivity() {

    private lateinit var tvCurrentPoints: TextView
    private lateinit var etWithdrawAmount: EditText
    private lateinit var spinnerPaymentMethod: Spinner
    private lateinit var etPaymentDetails: EditText
    private lateinit var btnWithdraw: Button
    private lateinit var tvMinimumWithdraw: TextView

    private val minimumWithdrawal = 1000 // Minimum points required
    private val paymentMethods = listOf(
        "Select Payment Method",
        "PayPal",
        "Bank Transfer",
        "GoPay",
        "OVO",
        "DANA",
        "ShopeePay"
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_withdrawal)

        initializeViews()
        setupInitialState()
        setupPaymentMethodSpinner()
        setupClickListeners()
    }

    /**
     * Initialize view components
     */
    private fun initializeViews() {
        tvCurrentPoints = findViewById(R.id.tv_current_points)
        etWithdrawAmount = findViewById(R.id.et_withdraw_amount)
        spinnerPaymentMethod = findViewById(R.id.spinner_payment_method)
        etPaymentDetails = findViewById(R.id.et_payment_details)
        btnWithdraw = findViewById(R.id.btn_withdraw)
        tvMinimumWithdraw = findViewById(R.id.tv_minimum_withdraw)
    }

    /**
     * Setup initial state
     */
    private fun setupInitialState() {
        updatePointsDisplay()
        tvMinimumWithdraw.text = getString(R.string.minimum_withdrawal, minimumWithdrawal)
        etPaymentDetails.hint = getString(R.string.hint_select_payment_method)
    }

    /**
     * Setup payment method spinner
     */
    private fun setupPaymentMethodSpinner() {
        val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_item, paymentMethods)
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinnerPaymentMethod.adapter = adapter

        spinnerPaymentMethod.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: android.view.View?, position: Int, id: Long) {
                updatePaymentDetailsHint(position)
            }
            override fun onNothingSelected(parent: AdapterView<*>?) {}
        }
    }

    /**
     * Update payment details hint based on selected method
     */
    private fun updatePaymentDetailsHint(position: Int) {
        val hint = when (position) {
            1 -> getString(R.string.hint_paypal_email) // PayPal
            2 -> getString(R.string.hint_bank_account) // Bank Transfer
            3 -> getString(R.string.hint_gopay_number) // GoPay
            4 -> getString(R.string.hint_ovo_number) // OVO
            5 -> getString(R.string.hint_dana_number) // DANA
            6 -> getString(R.string.hint_shopee_number) // ShopeePay
            else -> getString(R.string.hint_select_payment_method)
        }
        etPaymentDetails.hint = hint
    }

    /**
     * Setup click listeners
     */
    private fun setupClickListeners() {
        btnWithdraw.setOnClickListener {
            performWithdrawal()
        }
    }

    /**
     * Perform withdrawal simulation with validation
     */
    private fun performWithdrawal() {
        val currentUser = SharedPrefsManager.getCurrentUser(this)
        if (currentUser == null) {
            Toast.makeText(this, getString(R.string.error_user_not_found), Toast.LENGTH_SHORT).show()
            return
        }

        // Validate withdrawal amount
        val withdrawAmountStr = etWithdrawAmount.text.toString().trim()
        if (withdrawAmountStr.isEmpty()) {
            etWithdrawAmount.error = getString(R.string.error_field_required)
            return
        }

        val withdrawAmount = try {
            withdrawAmountStr.toInt()
        } catch (e: NumberFormatException) {
            etWithdrawAmount.error = getString(R.string.error_invalid_amount)
            return
        }

        // Validate minimum withdrawal
        if (withdrawAmount < minimumWithdrawal) {
            etWithdrawAmount.error = getString(R.string.error_minimum_withdrawal, minimumWithdrawal)
            return
        }

        // Validate sufficient balance
        if (withdrawAmount > currentUser.points) {
            etWithdrawAmount.error = getString(R.string.error_insufficient_balance)
            return
        }

        // Validate payment method selection
        if (spinnerPaymentMethod.selectedItemPosition == 0) {
            Toast.makeText(this, getString(R.string.error_select_payment_method), Toast.LENGTH_SHORT).show()
            return
        }

        // Validate payment details
        val paymentDetails = etPaymentDetails.text.toString().trim()
        if (paymentDetails.isEmpty()) {
            etPaymentDetails.error = getString(R.string.error_field_required)
            return
        }

        // Simulate withdrawal process
        processWithdrawal(currentUser, withdrawAmount, paymentDetails)
    }

    /**
     * Process the withdrawal simulation
     */
    private fun processWithdrawal(user: com.christopheraldoo.earningquizapp.models.User, amount: Int, paymentDetails: String) {
        // Deduct points from user account
        user.points -= amount
        SharedPrefsManager.saveUser(this, user)

        // Show success message
        val paymentMethod = paymentMethods[spinnerPaymentMethod.selectedItemPosition]
        val message = getString(R.string.withdrawal_success, amount, paymentMethod)
        
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()

        // Update UI
        updatePointsDisplay()
        clearForm()

        // In a real app, you would also:
        // - Save withdrawal history
        // - Send request to payment processor
        // - Show processing status
    }

    /**
     * Update points display
     */
    private fun updatePointsDisplay() {
        val currentUser = SharedPrefsManager.getCurrentUser(this)
        val points = currentUser?.points ?: 0
        tvCurrentPoints.text = getString(R.string.current_points, points)
    }

    /**
     * Clear the withdrawal form
     */
    private fun clearForm() {
        etWithdrawAmount.text.clear()
        etPaymentDetails.text.clear()
        spinnerPaymentMethod.setSelection(0)
    }
}
