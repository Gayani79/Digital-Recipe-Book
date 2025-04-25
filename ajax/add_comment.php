<?php 
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to comment']);
    exit();
}

// Validate input
if (!isset($_POST['recipe_id']) || !isset($_POST['comment']) || empty(trim($_POST['comment']))) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = (int)$_POST['recipe_id'];
$comment = trim($_POST['comment']);

// Get username for response
$user_sql = "SELECT username FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$username = $user_result->fetch_assoc()['username'];

// Insert comment
$sql = "INSERT INTO comments (user_id, recipe_id, comment, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $user_id, $recipe_id, $comment);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'username' => $username,
        'created_at' => date('F j, Y g:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error posting comment']);
}

$conn->close();
?>
