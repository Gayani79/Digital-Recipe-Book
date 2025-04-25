<?php
/**
 * Recipe App - Home Page
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

// Fetch featured recipes
$featured_recipes = [];
$featured_sql = "SELECT r.*, u.username, c.name as category_name, 
                (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
                (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
                FROM recipes r 
                JOIN users u ON r.user_id = u.user_id
                LEFT JOIN categories c ON r.category_id = c.category_id
                WHERE r.status = 'published' AND r.featured = 1
                ORDER BY r.created_at DESC
                LIMIT 6";

$featured_result = $conn->query($featured_sql);
if ($featured_result && $featured_result->num_rows > 0) {
    while ($row = $featured_result->fetch_assoc()) {
        $featured_recipes[] = $row;
    }
}

// Fetch categories
$categories = [];
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch recent recipes
$recent_recipes = [];
$recent_sql = "SELECT r.*, u.username, c.name as category_name,
              (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
              (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
              FROM recipes r 
              JOIN users u ON r.user_id = u.user_id
              LEFT JOIN categories c ON r.category_id = c.category_id
              WHERE r.status = 'published'
              ORDER BY r.created_at DESC
              LIMIT 8";

$recent_result = $conn->query($recent_sql);
if ($recent_result && $recent_result->num_rows > 0) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_recipes[] = $row;
    }
}

// If user is logged in, fetch their favorite recipes
$favorite_recipes = [];
if ($logged_in) {
    $favorite_sql = "SELECT r.*, u.username, c.name as category_name,
                    (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating
                    FROM recipes r 
                    JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN categories c ON r.category_id = c.category_id
                    JOIN favorites f ON r.recipe_id = f.recipe_id
                    WHERE f.user_id = ? AND r.status = 'published'
                    ORDER BY f.created_at DESC
                    LIMIT 4";
    
    if ($stmt = $conn->prepare($favorite_sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $favorite_recipes[] = $row;
        }
        
        $stmt->close();
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
    <title>Recipe App - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recipes.php">Recipes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Find & Share Amazing Recipes</h1>
            <p class="hero-text">Discover thousands of recipes, share your favorites, and connect with food lovers around the world.</p>
            <div class="search-bar">
                <form action="search.php" method="GET">
                    <div class="input-group">
                        <input type="text" name="query" class="form-control search-input" placeholder="Search for recipes, ingredients, cuisines...">
                        <button class="btn search-button" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Featured Recipes Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">Featured Recipes</h2>
            <div class="row">
                <?php foreach ($featured_recipes as $recipe): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="recipe-card">
                        <div class="position-relative">
                            <img src="<?= !empty($recipe['image']) ? 'uploads/recipes/' . $recipe['image'] : 'https://placehold.co/600x400' ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image">
                            <span class="recipe-badge">Featured</span>
                        </div>
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
                            <p class="recipe-description"><?= htmlspecialchars(substr($recipe['description'] ?? '', 0, 100)) . (strlen($recipe['description'] ?? '') > 100 ? '...' : '') ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-view-more">View Recipe</a>
                                <small class="text-muted">By <?= htmlspecialchars($recipe['username']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($featured_recipes)): ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No featured recipes available at the moment.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="recipes.php?featured=1" class="btn btn-view-more">View All Featured Recipes</a>
            </div>
        </div>
    </section>

    <!-- Browse Categories Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">Browse Categories</h2>
            <div class="row">
                <?php foreach (array_slice($categories, 0, 8) as $category): ?>
                <div class="col-md-3 col-sm-6">
                    <a href="recipes.php?category=<?= $category['category_id'] ?>" class="text-decoration-none">
                        <div class="category-card">
                            <img src="assets/images/breakfast.jpg" class="category-image">
                            <div class="category-overlay">
                                <h3 class="category-title"><?= htmlspecialchars($category['name']) ?></h3>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($categories)): ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No categories available at the moment.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="categories.php" class="btn btn-view-more">View All Categories</a>
            </div>
        </div>
    </section>

    <!-- Recent Recipes Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">Recently Added</h2>
            <div class="row">
                <?php foreach (array_slice($recent_recipes, 0, 4) as $recipe): ?>
                <div class="col-md-6 col-lg-3 mb-4">
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
                                </span>
                            </div>
                            <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-view-more w-100 mt-2">View Recipe</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($recent_recipes)): ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No recipes available at the moment.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="recipes.php" class="btn btn-view-more">View All Recipes</a>
            </div>
        </div>
    </section>

    <?php if ($logged_in && !empty($favorite_recipes)): ?>
    <!-- User's Favorites Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">Your Favorite Recipes</h2>
            <div class="row">
                <?php foreach ($favorite_recipes as $recipe): ?>
                <div class="col-md-6 col-lg-3 mb-4">
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
                                </span>
                            </div>
                            <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-view-more w-100 mt-2">View Recipe</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="favorites.php" class="btn btn-view-more">View All Favorites</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
<script src="js/index.js"></script>
</body>
</html>