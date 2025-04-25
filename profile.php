<?php
/**
 * Recipe App - User Profile Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$success_message = '';
$error_message = '';

// Fetch user data
$user_data = [];
$user_sql = "SELECT * FROM users WHERE user_id = ?";
if ($stmt = $conn->prepare($user_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } else {
        // Check if email is already used by another user
        $email_check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        if ($stmt = $conn->prepare($email_check_sql)) {
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error_message = "Email is already in use by another account.";
            } else {
                // Update user data
                $update_sql = "UPDATE users SET name = ?, email = ?, bio = ? WHERE user_id = ?";
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("sssi", $name, $email, $bio, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Profile updated successfully!";
                        
                        // Update session variables if needed
                        $_SESSION['name'] = $name;
                        
                        // Refresh user data
                        if ($stmt = $conn->prepare($user_sql)) {
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $user_data = $result->fetch_assoc();
                            }
                            
                            $stmt->close();
                        }
                    } else {
                        $error_message = "Error updating profile: " . $conn->error;
                    }
                    
                    $update_stmt->close();
                }
            }
            
            $stmt->close();
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password != $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Verify current password
        $verify_sql = "SELECT password FROM users WHERE user_id = ?";
        if ($stmt = $conn->prepare($verify_sql)) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("si", $hashed_password, $user_id);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Password updated successfully!";
                        } else {
                            $error_message = "Error updating password: " . $conn->error;
                        }
                        
                        $update_stmt->close();
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            }
            
            $stmt->close();
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $error_message = "Only JPG, PNG, and GIF files are allowed.";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $error_message = "File size must be less than 2MB.";
        } else {
            $upload_dir = 'uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                // Update profile picture in database
                $update_sql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                
                if ($stmt = $conn->prepare($update_sql)) {
                    $stmt->bind_param("si", $filename, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Profile picture updated successfully!";
                        
                        // Delete old profile picture if exists
                        if (!empty($user_data['profile_picture']) && $user_data['profile_picture'] != $filename) {
                            $old_file = $upload_dir . $user_data['profile_picture'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        // Update user data
                        $user_data['profile_picture'] = $filename;
                    } else {
                        $error_message = "Error updating profile picture: " . $conn->error;
                    }
                    
                    $stmt->close();
                }
            } else {
                $error_message = "Error uploading file.";
            }
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Fetch user stats
$total_recipes = 0;
$total_favorites = 0;
$total_ratings = 0;
$avg_rating = 0;

// Count total recipes
$recipes_sql = "SELECT COUNT(*) as total FROM recipes WHERE user_id = ?";
if ($stmt = $conn->prepare($recipes_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_recipes = $row['total'];
    }
    
    $stmt->close();
}

// Count total favorites
$favorites_sql = "SELECT COUNT(*) as total FROM favorites WHERE user_id = ?";
if ($stmt = $conn->prepare($favorites_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_favorites = $row['total'];
    }
    
    $stmt->close();
}

// Get ratings info
$ratings_sql = "SELECT COUNT(*) as total, AVG(rating) as average 
                FROM ratings r
                JOIN recipes rec ON r.recipe_id = rec.recipe_id
                WHERE rec.user_id = ?";
if ($stmt = $conn->prepare($ratings_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_ratings = $row['total'];
        $avg_rating = $row['average'] ? round($row['average'], 1) : 0;
    }
    
    $stmt->close();
}

// Get recent recipes
$recent_recipes = [];
$recent_recipes_sql = "SELECT r.*, c.name as category_name,
                      (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
                      (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
                      FROM recipes r 
                      LEFT JOIN categories c ON r.category_id = c.category_id
                      WHERE r.user_id = ?
                      ORDER BY r.created_at DESC
                      LIMIT 3";

if ($stmt = $conn->prepare($recent_recipes_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recent_recipes[] = $row;
    }
    
    $stmt->close();
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .profile-stats {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .profile-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            padding: 15px 20px;
        }
        
        .profile-tabs .nav-link.active {
            color: #ff6b6b;
            background-color: transparent;
            border-bottom: 3px solid #ff6b6b;
        }
        
        .tab-content {
            padding: 30px 0;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .upload-btn-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-utensils me-2"></i>Recipe App
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recipes.php">Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            My Account
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="my-recipes.php"><i class="fas fa-book-open me-2"></i>My Recipes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="add-recipe.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-plus me-2"></i>Add Recipe
                    </a>
                    <a href="auth/logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="<?= !empty($user_data['profile_picture']) ? 'uploads/profiles/' . $user_data['profile_picture'] : 'https://placehold.co/150x150' ?>" alt="Profile Picture" class="profile-picture mb-3">
                    
                </div>
                <div class="col-md-9">
                    <h1><?= htmlspecialchars($user_data['name'] ?? $username) ?></h1>
                    <p class="text-muted mb-2">@<?= htmlspecialchars($username) ?></p>
                    <p><?= htmlspecialchars($user_data['bio'] ?? 'No bio available.') ?></p>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-envelope me-2 text-muted"></i>
                        <span><?= htmlspecialchars($user_data['email'] ?? 'No email available.') ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-alt me-2 text-muted"></i>
                        <span>Member since <?= date('F Y', strtotime($user_data['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Stats -->
        <div class="profile-stats">
            <div class="row">
                <div class="col-md-3 stat-item">
                    <div class="stat-number"><?= $total_recipes ?></div>
                    <div class="stat-label">Recipes Created</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number"><?= $total_favorites ?></div>
                    <div class="stat-label">Recipes Saved</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number"><?= $total_ratings ?></div>
                    <div class="stat-label">Recipe Ratings Received</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number">
                        <?= $avg_rating ?>
                        <?php if ($avg_rating > 0): ?>
                            <i class="fas fa-star text-warning"></i>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
        </div>
        
        <!-- Profile Content -->
        <ul class="nav nav-tabs profile-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="recipes-tab" data-bs-toggle="tab" data-bs-target="#recipes" type="button" role="tab">Recent Recipes</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="edit-profile-tab" data-bs-toggle="tab" data-bs-target="#edit-profile" type="button" role="tab">Edit Profile</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Security</button>
            </li>
        </ul>
        
        <div class="tab-content" id="profileTabsContent">
            <!-- Recent Recipes Tab -->
            <div class="tab-pane fade show active" id="recipes" role="tabpanel" aria-labelledby="recipes-tab">
                <h3 class="mb-4">Your Recent Recipes</h3>
                
                <?php if (empty($recent_recipes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book-open fa-3x mb-3 text-muted"></i>
                        <p class="lead">You haven't created any recipes yet.</p>
                        <a href="add-recipe.php" class="btn btn-primary mt-3">Create Your First Recipe</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($recent_recipes as $recipe): ?>
                        <div class="col-md-4 mb-4">
                            <div class="recipe-card">
                                <img src="<?= !empty($recipe['image']) ? 'uploads/recipes/' . $recipe['image'] : 'https://placehold.co/600x400' ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image">
                                <div class="recipe-info">
                                    <div class="recipe-category"><?= htmlspecialchars($recipe['category_name'] ?? 'Uncategorized') ?></div>
                                    <h3 class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></h3>
                                    <div class="recipe-meta">
                                        <span><i class="far fa-clock me-1"></i><?= $recipe['total_time'] ?? 'N/A' ?> mins</span>
                                        <span class="recipe-rating">
                                            <?php
                                            $rating = floatval($recipe['avg_rating'] ?? 0);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($i - 0.5 <= $rating) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="ms-1">(<?= $recipe['rating_count'] ?? 0 ?>)</span>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-3">
                                        <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-sm btn-view-more">View</a>
                                        <a href="edit-recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="my-recipes.php" class="btn btn-view-more">View All My Recipes</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Edit Profile Tab -->
            <div class="tab-pane fade" id="edit-profile" role="tabpanel" aria-labelledby="edit-profile-tab">
                <h3 class="mb-4">Edit Profile</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($username) ?>" disabled>
                        <small class="text-muted">Username cannot be changed.</small>
                    </div>
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                <h3 class="mb-4">Change Password</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h3 class="footer-title">Recipe App</h3>
                    <p>Discover, create, and share delicious recipes from around the world. Join our community of food lovers today.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h3 class="footer-title">Quick Links</h3>
                    <div class="footer-links">
                        <a href="index.php">Home</a>
                        <a href="recipes.php">Recipes</a>
                        <a href="categories.php">Categories</a>
                        <a href="about.php">About Us</a>
                        <a href="contact.php">Contact</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <h3 class="footer-title">Categories</h3>
                    <div class="footer-links">
                        <a href="recipes.php?category=1">Breakfast</a>
                        <a href="recipes.php?category=2">Lunch</a>
                        <a href="recipes.php?category=3">Dinner</a>
                        <a href="recipes.php?category=4">Desserts</a>
                        <a href="recipes.php?category=5">Vegetarian</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <h3 class="footer-title">Newsletter</h3>
                    <p>Subscribe to our newsletter for new recipes, tips, and more.</p>
                    <form id="newsletter-form" class="mt-3">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your Email" required>
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p class="mb-0">&copy; <?= date('Y') ?> Recipe App. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
   
</body>
</html>