<?php
/**
 * Recipe App - Recipes Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_id = $logged_in ? $_SESSION['user_id'] : null;

// Initialize query parameters
$where_clauses = ["r.status = 'published'"];
$params = [];
$param_types = "";

// Filter by category
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_id = intval($_GET['category']);
    $where_clauses[] = "r.category_id = ?";
    $params[] = $category_id;
    $param_types .= "i";
}

// Filter by difficulty
if (isset($_GET['difficulty']) && !empty($_GET['difficulty'])) {
    $difficulty_id = intval($_GET['difficulty']);
    $where_clauses[] = "r.difficulty_id = ?";
    $params[] = $difficulty_id;
    $param_types .= "i";
}

// Filter by featured
if (isset($_GET['featured']) && $_GET['featured'] == '1') {
    $where_clauses[] = "r.featured = 1";
}

// Filter by dietary preference
if (isset($_GET['preference']) && !empty($_GET['preference'])) {
    $preference_id = intval($_GET['preference']);
    $where_clauses[] = "EXISTS (SELECT 1 FROM recipe_dietary_preferences rdp WHERE rdp.recipe_id = r.recipe_id AND rdp.preference_id = ?)";
    $params[] = $preference_id;
    $param_types .= "i";
}

// Filter by tag
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    $tag_id = intval($_GET['tag']);
    $where_clauses[] = "EXISTS (SELECT 1 FROM recipe_tags rt WHERE rt.recipe_id = r.recipe_id AND rt.tag_id = ?)";
    $params[] = $tag_id;
    $param_types .= "i";
}

// Filter by cooking time
if (isset($_GET['time']) && !empty($_GET['time'])) {
    $time = intval($_GET['time']);
    $where_clauses[] = "r.total_time <= ?";
    $params[] = $time;
    $param_types .= "i";
}

// Search query
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $param_types .= "ss";
}

// Build the WHERE clause
$where_clause = implode(" AND ", $where_clauses);

// Pagination
$recipes_per_page = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recipes_per_page;

// Sorting
$valid_sort_options = ['newest', 'oldest', 'rating', 'popularity'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_sort_options) ? $_GET['sort'] : 'newest';

$order_by = "r.created_at DESC"; // Default sorting

switch ($sort) {
    case 'oldest':
        $order_by = "r.created_at ASC";
        break;
    case 'rating':
        $order_by = "avg_rating DESC, r.created_at DESC";
        break;
    case 'popularity':
        $order_by = "r.views DESC, r.created_at DESC";
        break;
}

// Count total recipes matching filters
$count_sql = "
    SELECT COUNT(*) as total 
    FROM recipes r
    WHERE $where_clause
";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_recipes = $count_row['total'];
$total_pages = ceil($total_recipes / $recipes_per_page);

// Get recipes with pagination
$recipes_sql = "
    SELECT r.*, u.username, c.name as category_name, d.name as difficulty_name,
    (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
    (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
    FROM recipes r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN categories c ON r.category_id = c.category_id
    LEFT JOIN difficulty_levels d ON r.difficulty_id = d.difficulty_id
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT ?, ?
";

$recipes = [];
$stmt = $conn->prepare($recipes_sql);
$all_params = array_merge($params, [$offset, $recipes_per_page]);
$param_types .= "ii";
$stmt->bind_param($param_types, ...$all_params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recipes[] = $row;
}

// Fetch categories for filter
$categories = [];
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch difficulty levels for filter
$difficulty_levels = [];
$difficulty_sql = "SELECT * FROM difficulty_levels ORDER BY difficulty_id";
$difficulty_result = $conn->query($difficulty_sql);
if ($difficulty_result && $difficulty_result->num_rows > 0) {
    while ($row = $difficulty_result->fetch_assoc()) {
        $difficulty_levels[] = $row;
    }
}

// Fetch dietary preferences for filter
$dietary_preferences = [];
$preferences_sql = "SELECT * FROM dietary_preferences ORDER BY name";
$preferences_result = $conn->query($preferences_sql);
if ($preferences_result && $preferences_result->num_rows > 0) {
    while ($row = $preferences_result->fetch_assoc()) {
        $dietary_preferences[] = $row;
    }
}

// Fetch tags for filter
$tags = [];
$tags_sql = "SELECT * FROM tags ORDER BY name";
$tags_result = $conn->query($tags_sql);
if ($tags_result && $tags_result->num_rows > 0) {
    while ($row = $tags_result->fetch_assoc()) {
        $tags[] = $row;
    }
}

// Close database connection
$conn->close();

// Build current query string for pagination links
$current_query = [];
foreach ($_GET as $key => $value) {
    if ($key != 'page') {
        $current_query[] = htmlspecialchars($key) . '=' . htmlspecialchars($value);
    }
}
$current_query_string = implode('&', $current_query);
$current_query_string = !empty($current_query_string) ? $current_query_string . '&' : '';

// Page title and description
$page_title = "Recipes";
if (isset($_GET['category']) && !empty($_GET['category'])) {
    foreach ($categories as $category) {
        if ($category['category_id'] == $_GET['category']) {
            $page_title = $category['name'] . " Recipes";
            break;
        }
    }
}
if (isset($_GET['featured']) && $_GET['featured'] == '1') {
    $page_title = "Featured Recipes";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .filters {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-heading {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .filter-group {
            margin-bottom: 20px;
        }
        .filter-label {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .sort-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .pagination {
            margin-top: 30px;
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
                        <a class="nav-link active" href="recipes.php">Recipes</a>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1><?= $page_title ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $page_title ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Filters Sidebar -->
                <div class="col-lg-3 mb-4">
                    <div class="filters">
                        <h3 class="filter-heading">Filter Recipes</h3>
                        <form action="recipes.php" method="GET" id="filter-form">
                            <!-- Preserve any existing featured filter -->
                            <?php if (isset($_GET['featured']) && $_GET['featured'] == '1'): ?>
                                <input type="hidden" name="featured" value="1">
                            <?php endif; ?>
                            
                            <!-- Search -->
                            <div class="filter-group">
                                <div class="mb-3">
                                    <label for="search" class="form-label filter-label">Search</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" 
                                               placeholder="Search recipes...">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Categories -->
                            <div class="filter-group">
                                <label class="filter-label">Categories</label>
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>" 
                                            <?= (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Difficulty -->
                            <div class="filter-group">
                                <label class="filter-label">Difficulty</label>
                                <select class="form-select" name="difficulty">
                                    <option value="">Any Difficulty</option>
                                    <?php foreach ($difficulty_levels as $difficulty): ?>
                                        <option value="<?= $difficulty['difficulty_id'] ?>" 
                                            <?= (isset($_GET['difficulty']) && $_GET['difficulty'] == $difficulty['difficulty_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($difficulty['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Cooking Time -->
                            <div class="filter-group">
                                <label class="filter-label">Max Cooking Time</label>
                                <select class="form-select" name="time">
                                    <option value="">Any Time</option>
                                    <option value="15" <?= (isset($_GET['time']) && $_GET['time'] == 15) ? 'selected' : '' ?>>15 minutes or less</option>
                                    <option value="30" <?= (isset($_GET['time']) && $_GET['time'] == 30) ? 'selected' : '' ?>>30 minutes or less</option>
                                    <option value="45" <?= (isset($_GET['time']) && $_GET['time'] == 45) ? 'selected' : '' ?>>45 minutes or less</option>
                                    <option value="60" <?= (isset($_GET['time']) && $_GET['time'] == 60) ? 'selected' : '' ?>>1 hour or less</option>
                                </select>
                            </div>
                            
                            <!-- Dietary Preferences -->
                            <div class="filter-group">
                                <label class="filter-label">Dietary Preferences</label>
                                <select class="form-select" name="preference">
                                    <option value="">Any Preference</option>
                                    <?php foreach ($dietary_preferences as $preference): ?>
                                        <option value="<?= $preference['preference_id'] ?>" 
                                            <?= (isset($_GET['preference']) && $_GET['preference'] == $preference['preference_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($preference['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Tags -->
                            <div class="filter-group">
                                <label class="filter-label">Tags</label>
                                <select class="form-select" name="tag">
                                    <option value="">Any Tag</option>
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?= $tag['tag_id'] ?>" 
                                            <?= (isset($_GET['tag']) && $_GET['tag'] == $tag['tag_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                                
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="recipes.php" class="btn btn-outline-secondary">Reset Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Recipes Grid -->
                <div class="col-lg-9">
                    <div class="sort-controls">
                        <p class="mb-0">Found <?= $total_recipes ?> recipe<?= $total_recipes != 1 ? 's' : '' ?></p>
                        <div class="sorting">
                            <label class="me-2">Sort by:</label>
                            <select class="form-select form-select-sm d-inline-block w-auto" id="sort-select">
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                                <option value="popularity" <?= $sort == 'popularity' ? 'selected' : '' ?>>Most Popular</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($recipes)): ?>
                        <div class="alert alert-info">
                            <p class="mb-0">No recipes found matching your criteria. Try adjusting your filters or <a href="recipes.php">view all recipes</a>.</p>
                        </div>
                    <?php else: ?>
                        <div class="recipe-grid">
                            <?php foreach ($recipes as $recipe): ?>
                                <div class="recipe-card">
                                    <div class="position-relative">
                                        <img src="<?= !empty($recipe['image']) ? 'uploads/recipes/' . $recipe['image'] : 'https://placehold.co/600x400' ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image">
                                        <?php if ($recipe['featured'] == 1): ?>
                                            <span class="recipe-badge">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="recipe-info">
                                        <div class="recipe-category"><?= htmlspecialchars($recipe['category_name'] ?? 'Uncategorized') ?></div>
                                        <h3 class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></h3>
                                        <div class="recipe-meta">
                                            <span><i class="far fa-clock me-1"></i><?= $recipe['total_time'] ?? 'N/A' ?> mins</span>
                                            <span><i class="fas fa-utensils me-1"></i><?= htmlspecialchars($recipe['difficulty_name'] ?? 'N/A') ?></span>
                                        </div>
                                        <div class="recipe-rating my-2">
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
                                        </div>
                                        <p class="recipe-description"><?= htmlspecialchars(substr($recipe['description'] ?? '', 0, 100)) . (strlen($recipe['description'] ?? '') > 100 ? '...' : '') ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="recipe.php?id=<?= $recipe['recipe_id'] ?>" class="btn btn-view-more">View Recipe</a>
                                            <small class="text-muted">By <?= htmlspecialchars($recipe['username']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $current_query_string ?>page=<?= $page - 1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?' . $current_query_string . 'page=1">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                              <a class="page-link" href="?' . $current_query_string . 'page=' . $i . '">' . $i . '</a>
                                              </li>';
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?' . $current_query_string . 'page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $current_query_string ?>page=<?= $page + 1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
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
        // Sort select change handler
        document.getElementById('sort-select').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort', this.value);
            currentUrl.searchParams.delete('page'); // Reset to page 1 when sorting changes
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html>