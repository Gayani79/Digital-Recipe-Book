<?php
/**
 * Recipe App - Categories Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_id = $logged_in ? $_SESSION['user_id'] : null;
$username = $logged_in ? $_SESSION['username'] : null;

// Fetch all categories
$categories = [];
$categories_sql = "SELECT c.*, 
                  (SELECT COUNT(*) FROM recipes WHERE category_id = c.category_id AND status = 'published') as recipe_count 
                  FROM categories c 
                  ORDER BY c.name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Categories - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .category-container {
            margin-bottom: 40px;
        }
        
        .category-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            margin-bottom: 20px;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .category-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        
        .category-info {
            padding: 15px;
            background: #fff;
        }
        
        .category-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .category-count {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .category-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            height: 60px;
            overflow: hidden;
        }
        
        .page-header {
            background-color: #f8f9fa;
            padding: 30px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 0;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #495057;
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
                        <a class="nav-link active" href="categories.php">Categories</a>
                    </li>
                    <?php if ($logged_in): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            My Account
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="my-recipes.php"><i class="fas fa-book-open me-2"></i>My Recipes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <?php if ($logged_in): ?>
                        <a href="add-recipe.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-plus me-2"></i>Add Recipe
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="auth/register.php" class="btn btn-outline-light">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Recipe Categories</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Categories</li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="recipes.php?category=<?= $category['category_id'] ?>" class="text-decoration-none">
                            <div class="category-card">
                                <img src="assets/images/breakfast.jpg" class="category-image">
                                <div class="category-info">
                                    <h3 class="category-title"><?= htmlspecialchars($category['name']) ?></h3>
                                    <div class="category-count">
                                        <?= $category['recipe_count'] ?> Recipe<?= $category['recipe_count'] != 1 ? 's' : '' ?>
                                    </div>
                                    <?php if (!empty($category['description'])): ?>
                                    <div class="category-description">
                                        <?= htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : '') ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-center">
                                        <span class="btn btn-view-more">View Recipes</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                        <h3>No Categories Found</h3>
                        <p class="text-muted">Check back later for recipe categories.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="call-to-action">
        <div class="container">
            <?php if (!$logged_in): ?>
            <h2 class="cta-title">Join Our Cooking Community</h2>
            <p class="cta-text">Create an account to share your recipes, save your favorites, create meal plans, and connect with other food enthusiasts.</p>
            <a href="auth/register.php" class="btn cta-button">Sign Up Now</a>
            <?php else: ?>
            <h2 class="cta-title">Share Your Culinary Creations</h2>
            <p class="cta-text">Got a recipe that everyone loves? Share it with our community and get feedback from food enthusiasts around the world.</p>
            <a href="add-recipe.php" class="btn cta-button">Add New Recipe</a>
            <?php endif; ?>
        </div>
    </section>

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
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <a href="recipes.php?category=<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></a>
                        <?php endforeach; ?>
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
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Enable bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>