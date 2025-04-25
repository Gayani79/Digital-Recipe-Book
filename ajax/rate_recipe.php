<?php
// ajax/rate_recipe.php
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to rate']);
    exit();
}

// Validate input
if (!isset($_POST['recipe_id']) || !isset($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Missing recipe ID or rating']);
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = (int)$_POST['recipe_id'];
$rating = (int)$_POST['rating'];

// Validate rating value (1-5)
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit();
}

// Check if user has already rated this recipe
$check_sql = "SELECT rating FROM ratings WHERE user_id = ? AND recipe_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $recipe_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing rating
    $update_sql = "UPDATE ratings SET rating = ?, rated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND recipe_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("iii", $rating, $user_id, $recipe_id);
    $success = $update_stmt->execute();
} else {
    // Insert new rating
    $insert_sql = "INSERT INTO ratings (user_id, recipe_id, rating, rated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iii", $user_id, $recipe_id, $rating);
    $success = $insert_stmt->execute();
}

if ($success) {
    // Get updated average rating and count
    $avg_sql = "SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as rating_count FROM ratings WHERE recipe_id = ?";
    $avg_stmt = $conn->prepare($avg_sql);
    $avg_stmt->bind_param("i", $recipe_id);
    $avg_stmt->execute();
    $avg_result = $avg_stmt->get_result();
    $avg_data = $avg_result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'avg_rating' => $avg_data['avg_rating'],
        'rating_count' => $avg_data['rating_count']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error saving rating']);
}

$conn->close();
?>
