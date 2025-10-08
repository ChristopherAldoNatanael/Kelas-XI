package com.christopheraldoo.basiccalculator

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.ui.Modifier
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.ui.Alignment
import androidx.compose.ui.unit.dp

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            MaterialTheme {
                Scaffold(modifier = Modifier.fillMaxSize()) { innerPadding ->
                    Calculator(
                        modifier = Modifier.padding(innerPadding)
                    )
                }
            }
        }
    }
}

@Composable
fun Calculator(modifier: Modifier = Modifier) {
    var display by remember { mutableStateOf("0") }
    var firstNumber by remember { mutableStateOf<Double?>(null) }
    var operation by remember { mutableStateOf<String?>(null) }
    var waitingForNextNumber by remember { mutableStateOf(false) }

    fun onNumberClick(number: String) {
        if (waitingForNextNumber) {
            display = number
            waitingForNextNumber = false
        } else {
            if (display == "0") {
                display = number
            } else {
                display += number
            }
        }
    }

    fun onOperationClick(op: String) {
        if (firstNumber == null) {
            firstNumber = display.toDoubleOrNull()
        } else if (!waitingForNextNumber) {
            // Calculate if there's already an operation
            firstNumber = performCalculation(firstNumber, display.toDoubleOrNull(), operation)
            display = firstNumber?.toString() ?: "0"
        }
        operation = op
        waitingForNextNumber = true
    }

    fun onEqualsClick() {
        if (firstNumber != null && operation != null) {
            val secondNumber = display.toDoubleOrNull()
            val result = performCalculation(firstNumber, secondNumber, operation)
            display = result?.toString() ?: "Error"
            firstNumber = null
            operation = null
            waitingForNextNumber = true
        }
    }

    fun onClearClick() {
        display = "0"
        firstNumber = null
        operation = null
        waitingForNextNumber = false
    }

    Column(modifier = modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(text = display, style = MaterialTheme.typography.headlineMedium, modifier = Modifier.fillMaxWidth().padding(16.dp))
        Spacer(modifier = Modifier.height(16.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(onClick = { onNumberClick("7") }, modifier = Modifier.weight(1f)) { Text("7") }
            Button(onClick = { onNumberClick("8") }, modifier = Modifier.weight(1f)) { Text("8") }
            Button(onClick = { onNumberClick("9") }, modifier = Modifier.weight(1f)) { Text("9") }
            Button(onClick = { onOperationClick("/") }, modifier = Modifier.weight(1f)) { Text("/") }
        }
        Spacer(modifier = Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(onClick = { onNumberClick("4") }, modifier = Modifier.weight(1f)) { Text("4") }
            Button(onClick = { onNumberClick("5") }, modifier = Modifier.weight(1f)) { Text("5") }
            Button(onClick = { onNumberClick("6") }, modifier = Modifier.weight(1f)) { Text("6") }
            Button(onClick = { onOperationClick("*") }, modifier = Modifier.weight(1f)) { Text("*") }
        }
        Spacer(modifier = Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(onClick = { onNumberClick("1") }, modifier = Modifier.weight(1f)) { Text("1") }
            Button(onClick = { onNumberClick("2") }, modifier = Modifier.weight(1f)) { Text("2") }
            Button(onClick = { onNumberClick("3") }, modifier = Modifier.weight(1f)) { Text("3") }
            Button(onClick = { onOperationClick("-") }, modifier = Modifier.weight(1f)) { Text("-") }
        }
        Spacer(modifier = Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(onClick = { onClearClick() }, modifier = Modifier.weight(1f)) { Text("C") }
            Button(onClick = { onNumberClick("0") }, modifier = Modifier.weight(1f)) { Text("0") }
            Button(onClick = { onEqualsClick() }, modifier = Modifier.weight(1f)) { Text("=") }
            Button(onClick = { onOperationClick("+") }, modifier = Modifier.weight(1f)) { Text("+") }
        }
    }
}

fun performCalculation(first: Double?, second: Double?, operation: String?): Double? {
    if (first == null || second == null || operation == null) return null
    return when (operation) {
        "+" -> first + second
        "-" -> first - second
        "*" -> first * second
        "/" -> if (second != 0.0) first / second else null
        else -> null
    }
}
