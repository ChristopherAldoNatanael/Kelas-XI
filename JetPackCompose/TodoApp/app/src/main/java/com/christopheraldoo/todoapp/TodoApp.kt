package com.christopheraldoo.todoapp

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.runtime.collectAsState
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.compose.ui.platform.LocalContext
import com.christopheraldoo.todoapp.data.Todo
import com.christopheraldoo.todoapp.data.TodoDatabase
import com.christopheraldoo.todoapp.data.TodoFilter
import com.christopheraldoo.todoapp.data.TodoRepository
import com.christopheraldoo.todoapp.ui.AddTodoDialog
import com.christopheraldoo.todoapp.ui.EditTodoDialog
import com.christopheraldoo.todoapp.ui.TodoViewModel
import com.christopheraldoo.todoapp.ui.TodoViewModelFactory

@Composable
fun TodoApp(viewModel: TodoViewModel = viewModel(factory = TodoViewModelFactory(LocalContext.current))) {
    var showAddDialog by remember { mutableStateOf(false) }
    var todoToEdit by remember { mutableStateOf<Todo?>(null) }

    // Observe ViewModel state
    val currentFilter by viewModel.currentFilter.collectAsState()
    val allTodos by viewModel.allTodos.collectAsState(emptyList())
    val activeCount by viewModel.activeTodoCount.collectAsState(0)
    val completedCount by viewModel.completedTodoCount.collectAsState(0)

    // Filter todos based on current filter
    val filteredTodos = when (currentFilter) {
        TodoFilter.ALL -> allTodos
        TodoFilter.ACTIVE -> allTodos.filter { !it.isCompleted }
        TodoFilter.COMPLETED -> allTodos.filter { it.isCompleted }
    }

    Scaffold(
        modifier = Modifier.safeDrawingPadding(),
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showAddDialog = true },
                containerColor = MaterialTheme.colorScheme.primaryContainer,
                contentColor = MaterialTheme.colorScheme.onPrimaryContainer
            ) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_input_add),
                    contentDescription = "Add Todo"
                )
            }
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
        ) {
            // Header
            Text(
                text = "Todo App",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(bottom = 16.dp)
            )

            // Filter Tabs
            FilterTabs(
                currentFilter = currentFilter,
                onFilterChange = viewModel::setFilter,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(16.dp))

            // Todo Counter
            Text(
                text = "$activeCount active, $completedCount completed",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(bottom = 8.dp)
            )

            // Todo List
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.weight(1f)
            ) {
                items(
                    items = filteredTodos,
                    key = { todo -> todo.id }
                ) { todo ->
                    TodoItem(
                        todo = todo,
                        onToggleComplete = { viewModel.toggleTodoStatus(todo) },
                        onDelete = { viewModel.deleteTodo(todo) },
                        onEdit = { todoToEdit = todo }
                    )
                }
            }

            // Clear Completed Button (if there are completed todos)
            AnimatedVisibility(
                visible = completedCount > 0,
                enter = fadeIn(tween(300)),
                exit = fadeOut(tween(300))
            ) {
                Button(
                    onClick = viewModel::deleteCompletedTodos,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.secondaryContainer,
                        contentColor = MaterialTheme.colorScheme.onSecondaryContainer
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp)
                ) {
                    Text("Clear Completed")
                }
            }
        }
    }

    if (showAddDialog) {
        AddTodoDialog(
            onDismiss = { showAddDialog = false },
            onConfirm = { title, description, priority ->
                viewModel.addTodo(title, description, priority)
                showAddDialog = false
            }
        )
    }

    todoToEdit?.let { todo ->
        EditTodoDialog(
            todo = todo,
            onDismiss = { todoToEdit = null },
            onConfirm = { newTitle, newDescription ->
                viewModel.updateTodoDetails(todo.id, newTitle, newDescription)
                todoToEdit = null
            }
        )
    }
}

@Composable
private fun FilterTabs(
    currentFilter: TodoFilter,
    onFilterChange: (TodoFilter) -> Unit,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        TodoFilter.values().forEach { filter ->
            val isSelected = currentFilter == filter
            Button(
                onClick = { onFilterChange(filter) },
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (isSelected) {
                        MaterialTheme.colorScheme.primary
                    } else {
                        MaterialTheme.colorScheme.surfaceVariant
                    },
                    contentColor = if (isSelected) {
                        MaterialTheme.colorScheme.onPrimary
                    } else {
                        MaterialTheme.colorScheme.onSurfaceVariant
                    }
                ),
                shape = RoundedCornerShape(20.dp)
            ) {
                Text(
                    text = filter.name.lowercase().replaceFirstChar { it.uppercase() },
                    style = MaterialTheme.typography.labelLarge
                )
            }
        }
    }
}

@Composable
private fun TodoItem(
    todo: Todo,
    onToggleComplete: () -> Unit,
    onDelete: () -> Unit,
    onEdit: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceContainerLow
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Checkbox(
                checked = todo.isCompleted,
                onCheckedChange = { onToggleComplete() },
                colors = CheckboxDefaults.colors(
                    checkedColor = MaterialTheme.colorScheme.primary,
                    uncheckedColor = MaterialTheme.colorScheme.outline
                )
            )

            Spacer(modifier = Modifier.width(12.dp))

            Text(
                text = todo.title,
                style = MaterialTheme.typography.bodyLarge,
                textDecoration = if (todo.isCompleted) {
                    TextDecoration.LineThrough
                } else {
                    TextDecoration.None
                },
                color = if (todo.isCompleted) {
                    MaterialTheme.colorScheme.onSurfaceVariant
                } else {
                    MaterialTheme.colorScheme.onSurface
                },
                modifier = Modifier.weight(1f)
            )

            IconButton(onClick = onEdit) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_menu_edit),
                    contentDescription = "Edit Todo",
                    tint = MaterialTheme.colorScheme.primary
                )
            }

            IconButton(onClick = onDelete) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_menu_delete),
                    contentDescription = "Delete Todo",
                    tint = MaterialTheme.colorScheme.error
                )
            }
        }
    }
}
