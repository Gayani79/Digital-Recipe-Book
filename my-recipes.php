<?php
/**
 * Recipe App - My Recipes Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connection.php';

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle recipe deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $recipe_id = $_GET['delete'];
    
    // Verify the recipe belongs to the current user
    $check_sql = "SELECT recipe_id FROM recipes WHERE recipe_id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($check_sql)) {
        $stmt->bind_param("ii", $recipe_id, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            // Delete the recipe
            $delete_sql = "DELETE FROM recipes WHERE recipe_id = ?";
            if ($delete_stmt = $conn->prepare($delete_sql)) {
                $delete_stmt->bind_param("i", $recipe_id);
                if ($delete_stmt->execute()) {
                    // Success message
                    $success_message = "Recipe deleted successfully!";
                } else {
                    // Error message
                    $error_message = "Error deleting recipe. Please try again.";
                }
                $delete_stmt->close();
            }
        } else {
            // Error message if recipe doesn't belong to user
            $error_message = "You don't have permission to delete this recipe.";
        }
        
        $stmt->close();
    }
}

// Process status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$status_clause = "";

if ($status_filter === 'published') {
    $status_clause = " AND r.status = 'published'";
} elseif ($status_filter === 'draft') {
    $status_clause = " AND r.status = 'draft'";
}

// Process sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_by = "r.created_at DESC"; // default

if ($sort === 'oldest') {
    $order_by = "r.created_at ASC";
} elseif ($sort === 'name_asc') {
    $order_by = "r.title ASC";
} elseif ($sort === 'name_desc') {
    $order_by = "r.title DESC";
} elseif ($sort === 'popular') {
    $order_by = "avg_rating DESC, rating_count DESC";
}

// Pagination settings
$recipes_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $recipes_per_page;

// Fetch user's recipes with pagination
$recipes = [];
$recipes_sql = "SELECT r.*, c.name as category_name, 
               (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
               (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
               FROM recipes r 
               LEFT JOIN categories c ON r.category_id = c.category_id
               WHERE r.user_id = ?$status_clause
               ORDER BY $order_by
               LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($recipes_sql)) {
    $stmt->bind_param("iii", $user_id, $recipes_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }
    
    $stmt->close();
}

// Get total recipes count for pagination
$count_sql = "SELECT COUNT(*) as total FROM recipes r WHERE r.user_id = ?$status_clause";
$total_recipes = 0;

if ($stmt = $conn->prepare($count_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_recipes = $row['total'];
    }
    
    $stmt->close();
}

$total_pages = ceil($total_recipes / $recipes_per_page);

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recipes - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .recipe-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        
        .recipe-status {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .edit-btn {
            background-color: #007bff;
        }
        
        .delete-btn {
            background-color: #dc3545;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .filter-bar {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .stats-box {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item active" href="my-recipes.php"><i class="fas fa-book-open me-2"></i>My Recipes</a></li>
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

    <!-- Page Header -->
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-3"><i class="fas fa-book me-2"></i>My Recipes</h1>
                <p class="text-muted">Manage all your created recipes in one place.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="add-recipe.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Create New Recipe
                </a>
            </div>
        </div>
    </div>

    <!-- Recipe Stats -->
    <div class="container pb-4">
        <div class="row stats-box">
            <div class="col-md-3 col-6 stat-item">
                <div class="stat-number"><?= $total_recipes ?></div>
                <div class="stat-label">Total Recipes</div>
            </div>
            <?php 
            // Calculate published recipes
            $published_count = 0;
            $draft_count = 0;
            foreach ($recipes as $recipe) {
                if ($recipe['status'] === 'published') {
                    $published_count++;
                } else {
                    $draft_count++;
                }
            }
            ?>
            <div class="col-md-3 col-6 stat-item">
                <div class="stat-number"><?= $published_count ?></div>
                <div class="stat-label">Published Recipes</div>
            </div>
            <div class="col-md-3 col-6 stat-item">
                <div class="stat-number"><?= $draft_count ?></div>
                <div class="stat-label">Draft Recipes</div>
            </div>
            <div class="col-md-3 col-6 stat-item">
                <?php
                // Calculate average rating
                $total_rating = 0;
                $rated_recipes = 0;
                foreach ($recipes as $recipe) {
                    if (!empty($recipe['avg_rating'])) {
                        $total_rating += $recipe['avg_rating'];
                        $rated_recipes++;
                    }
                }
                $avg_overall_rating = $rated_recipes > 0 ? round($total_rating / $rated_recipes, 1) : 0;
                ?>
                <div class="stat-number"><?= $avg_overall_rating ?> <i class="fas fa-star" style="font-size: 0.8em; color: gold;"></i></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label mb-2">Filter by Status:</label>
                    <div class="btn-group" role="group">
                        <a href="my-recipes.php?status=all<?= isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : '' ?>" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                        <a href="my-recipes.php?status=published<?= isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : '' ?>" class="btn <?= $status_filter === 'published' ? 'btn-primary' : 'btn-outline-primary' ?>">Published</a>
                        <a href="my-recipes.php?status=draft<?= isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : '' ?>" class="btn <?= $status_filter === 'draft' ? 'btn-primary' : 'btn-outline-primary' ?>">Drafts</a>
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <label class="form-label mb-2">Sort by:</label>
                    <select class="form-select d-inline-block w-auto" id="sort-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Recipe List -->
        <div class="row">
            <?php if (empty($recipes)): ?>
                <div class="col-12 text-center py-5">
                    <div class="py-5">
                        <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                        <h3>No recipes found</h3>
                        <p class="text-muted">You haven't created any recipes yet or none match your filters.</p>
                        <a href="add-recipe.php" class="btn btn-primary mt-3">Create Your First Recipe</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="recipe-card">
                            <div class="position-relative">
                                <img src="<?= !empty($recipe['image']) ? 'uploads/recipes/' . $recipe['image'] : 'https://placehold.co/600x400' ?>" alt="<?= htmlspecialchars($recipe['title']) ?>" class="recipe-image">
                                <div class="recipe-status">
                                    <?php if ($recipe['status'] === 'published'): ?>
                                        <i class="fas fa-check-circle me-1"></i> Published
                                    <?php else: ?>
                                        <i class="fas fa-edit me-1"></i> Draft
                                    <?php endif; ?>
                                </div>
                                <div class="recipe-actions">
                                    <a href="edit-recipe.php?id=<?= $recipe['recipe_id'] ?>" class="action-btn edit-btn" title="Edit Recipe">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?= $recipe['recipe_id'] ?>, '<?= htmlspecialchars(addslashes($recipe['title'])) ?>')" class="action-btn delete-btn" title="Delete Recipe">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
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
                                    <small class="text-muted">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?= date('M d, Y', strtotime($recipe['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="my-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page <= 1) ? '#' : '?page='.($page-1).'&status='.$status_filter.(isset($_GET['sort']) ? '&sort='.$_GET['sort'] : '') ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?><?= isset($_GET['sort']) ? '&sort='.$_GET['sort'] : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page >= $total_pages) ? '#' : '?page='.($page+1).'&status='.$status_filter.(isset($_GET['sort']) ? '&sort='.$_GET['sort'] : '') ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
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
                        <a href="recipes.php?category=5">Vegan</a>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="recipe-title"></span>"? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirm-delete" class="btn btn-danger">Delete Recipe</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Handle sort change
        document.getElementById('sort-select').addEventListener('change', function() {
            const status = '<?= $status_filter ?>';
            window.location.href = `my-recipes.php?status=${status}&sort=${this.value}`;
        });
        
        // Delete confirmation
        function confirmDelete(recipeId, recipeTitle) {
            document.getElementById('recipe-title').textContent = recipeTitle;
            document.getElementById('confirm-delete').href = `my-recipes.php?delete=${recipeId}`;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>