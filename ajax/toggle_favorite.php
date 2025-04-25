<?php
// ajax/toggle_favorite.php 
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to favorite']);
    exit();
}

// Validate input
if (!isset($_POST['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing recipe ID']);
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = (int)$_POST['recipe_id'];

// Check if recipe is already favorited
$check_sql = "SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $recipe_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Remove from favorites
    $delete_sql = "DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $user_id, $recipe_id);
    $success = $delete_stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing from favorites']);
    }
} else {
    // Add to favorites
    $insert_sql = "INSERT INTO favorites (user_id, recipe_id, favorited_at) VALUES (?, ?, CURRENT_TIMESTAMP)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $user_id, $recipe_id);
    $success = $insert_stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true, 'favorited' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding to favorites']);
    }
}

$conn->close();
?>