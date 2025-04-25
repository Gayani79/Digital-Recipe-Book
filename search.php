<?php
/**
 * Recipe App - Search Page
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

// Get search query
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$time = isset($_GET['time']) ? (int)$_GET['time'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Prepare search query
$search_sql = "SELECT r.*, u.username, c.name as category_name, 
              (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
              (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
              FROM recipes r 
              JOIN users u ON r.user_id = u.user_id
              LEFT JOIN categories c ON r.category_id = c.category_id
              WHERE r.status = 'published' ";

$count_sql = "SELECT COUNT(*) as total FROM recipes r 
              JOIN users u ON r.user_id = u.user_id
              LEFT JOIN categories c ON r.category_id = c.category_id
              WHERE r.status = 'published' ";

$params = [];
$types = "";

// Add search conditions
if (!empty($search_query)) {
    $search_sql .= "AND (r.title LIKE ? OR r.description LIKE ? OR r.ingredients LIKE ? OR r.instructions LIKE ?) ";
    $count_sql .= "AND (r.title LIKE ? OR r.description LIKE ? OR r.ingredients LIKE ? OR r.instructions LIKE ?) ";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Filter by category
if ($category_id > 0) {
    $search_sql .= "AND r.category_id = ? ";
    $count_sql .= "AND r.category_id = ? ";
    $params[] = $category_id;
    $types .= "i";
}

// Filter by difficulty
if (!empty($difficulty)) {
    $search_sql .= "AND r.difficulty = ? ";
    $count_sql .= "AND r.difficulty = ? ";
    $params[] = $difficulty;
    $types .= "s";
}

// Filter by preparation time
if ($time > 0) {
    $search_sql .= "AND r.total_time <= ? ";
    $count_sql .= "AND r.total_time <= ? ";
    $params[] = $time;
    $types .= "i";
}

// Sort results
switch ($sort) {
    case 'oldest':
        $search_sql .= "ORDER BY r.created_at ASC ";
        break;
    case 'a-z':
        $search_sql .= "ORDER BY r.title ASC ";
        break;
    case 'z-a':
        $search_sql .= "ORDER BY r.title DESC ";
        break;
    case 'rating':
        $search_sql .= "ORDER BY avg_rating DESC ";
        break;
    case 'popular':
        $search_sql .= "ORDER BY r.view_count DESC ";
        break;
    default:
        $search_sql .= "ORDER BY r.created_at DESC ";
        break;
}

// Add pagination limit
$search_sql .= "LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

// Fetch categories for filter
$categories = [];
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Execute count query
$total_results = 0;
if ($stmt = $conn->prepare($count_sql)) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_results = $row['total'];
    }
    $stmt->close();
}

// Remove pagination parameters for count
array_pop($params);
array_pop($params);
$types = substr($types, 0, -2);

// Execute search query
$search_results = [];
if ($stmt = $conn->prepare($search_sql)) {
    if (!empty($types)) {
        $stmt->bind_param($types . "ii", ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
    }
    
    $stmt->close();
}

// Calculate pagination data
$total_pages = ceil($total_results / $items_per_page);

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .search-container {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .filter-section {
            margin-bottom: 1.5rem;
        }
        
        .search-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem 0;
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

    <!-- Search Results Section -->
    <div class="container py-5">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="search-container">
                    <h3 class="filter-title">Search Filters</h3>
                    <form action="search.php" method="GET">
                        <div class="filter-section">
                            <label for="query" class="form-label">Search</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="query" name="query" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search recipes...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="filter-section">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" <?= $category_id == $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-section">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="">Any Difficulty</option>
                                <option value="easy" <?= $difficulty == 'easy' ? 'selected' : '' ?>>Easy</option>
                                <option value="medium" <?= $difficulty == 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="hard" <?= $difficulty == 'hard' ? 'selected' : '' ?>>Hard</option>
                            </select>
                        </div>
                        
                        <div class="filter-section">
                            <label for="time" class="form-label">Max Preparation Time (minutes)</label>
                            <select class="form-select" id="time" name="time">
                                <option value="0">Any Time</option>
                                <option value="15" <?= $time == 15 ? 'selected' : '' ?>>15 minutes or less</option>
                                <option value="30" <?= $time == 30 ? 'selected' : '' ?>>30 minutes or less</option>
                                <option value="45" <?= $time == 45 ? 'selected' : '' ?>>45 minutes or less</option>
                                <option value="60" <?= $time == 60 ? 'selected' : '' ?>>1 hour or less</option>
                                <option value="120" <?= $time == 120 ? 'selected' : '' ?>>2 hours or less</option>
                            </select>
                        </div>
                        
                        <div class="filter-section">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="a-z" <?= $sort == 'a-z' ? 'selected' : '' ?>>A-Z</option>
                                <option value="z-a" <?= $sort == 'z-a' ? 'selected' : '' ?>>Z-A</option>
                                <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>Highest Rating</option>
                                <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </form>
                </div>
            </div>
            
            <!-- Search Results -->
            <div class="col-lg-9">
                <div class="search-results-header">
                    <h2>Search Results <?= !empty($search_query) ? 'for "' . htmlspecialchars($search_query) . '"' : '' ?></h2>
                    <span class="text-muted"><?= $total_results ?> recipe<?= $total_results != 1 ? 's' : '' ?> found</span>
                </div>
                
                <?php if (empty($search_results)): ?>
                <div class="no-results">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h3>No recipes found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($search_results as $recipe): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
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
                                <p class="recipe-description"><?= htmlspecialchars(substr($recipe['description'] ?? '', 0, 100)) . (strlen($recipe['description'] ?? '') > 100 ? '...' : '') ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-view-more">View Recipe</a>
                                    <small class="text-muted">By <?= htmlspecialchars($recipe['username']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Search results pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?query=<?= urlencode($search_query) ?>&category=<?= $category_id ?>&difficulty=<?= urlencode($difficulty) ?>&time=<?= $time ?>&sort=<?= $sort ?>&page=<?= $page-1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?query=<?= urlencode($search_query) ?>&category=<?= $category_id ?>&difficulty=<?= urlencode($difficulty) ?>&time=<?= $time ?>&sort=<?= $sort ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?query=<?= urlencode($search_query) ?>&category=<?= $category_id ?>&difficulty=<?= urlencode($difficulty) ?>&time=<?= $time ?>&sort=<?= $sort ?>&page=<?= $page+1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
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
            // Update form when selections change
            $('.form-select').change(function() {
                $(this).closest('form').submit();
            });
        });
    </script>
</body>
</html>