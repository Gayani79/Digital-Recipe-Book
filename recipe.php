<?php
/**
 * Recipe App - Single Recipe Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connection.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_id = $logged_in ? $_SESSION['user_id'] : null;

// Check if recipe ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: recipes.php");
    exit();
}

$recipe_id = (int)$_GET['id'];

// Fetch recipe details
$recipe_sql = "
    SELECT r.*, u.username, c.name as category_name, d.name as difficulty_name,
    (SELECT ROUND(AVG(rating), 1) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating,
    (SELECT COUNT(*) FROM ratings WHERE recipe_id = r.recipe_id) as rating_count
    FROM recipes r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN categories c ON r.category_id = c.category_id
    LEFT JOIN difficulty_levels d ON r.difficulty_id = d.difficulty_id
    WHERE r.recipe_id = ? AND r.status = 'published'
";

$recipe_stmt = $conn->prepare($recipe_sql);
$recipe_stmt->bind_param("i", $recipe_id);
$recipe_stmt->execute();
$recipe_result = $recipe_stmt->get_result();

if ($recipe_result->num_rows === 0) {
    header("Location: recipes.php");
    exit();
}

$recipe = $recipe_result->fetch_assoc();

// Increment view count
$update_views_sql = "UPDATE recipes SET views = views + 1 WHERE recipe_id = ?";
$update_views_stmt = $conn->prepare($update_views_sql);
$update_views_stmt->bind_param("i", $recipe_id);
$update_views_stmt->execute();

// Fetch ingredients
$ingredients_sql = "
    SELECT ri.*, i.name, u.name as unit_name, u.abbreviation 
    FROM recipe_ingredients ri
    LEFT JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
    LEFT JOIN units u ON ri.unit_id = u.unit_id
    WHERE ri.recipe_id = ? 
    ORDER BY COALESCE(ri.ingredient_order, 0), ri.recipe_ingredient_id";

$ingredients_stmt = $conn->prepare($ingredients_sql);
if (!$ingredients_stmt) {
    die("Prepare failed: " . $conn->error);
}

$ingredients_stmt->bind_param("i", $recipe_id);
$ingredients_stmt->execute();
$ingredients_result = $ingredients_stmt->get_result();
$ingredients = [];

while ($row = $ingredients_result->fetch_assoc()) {
    $ingredients[] = [
        'quantity' => $row['quantity'],
        'unit' => $row['unit_name'] ?: $row['abbreviation'],
        'name' => $row['name'],
        'notes' => $row['notes']
    ];
}

// Fetch instructions
$instructions = [];

// First try to fetch from recipe_instructions table
$instructions_sql = "
    SELECT * FROM recipe_instructions 
    WHERE recipe_id = ? 
    ORDER BY step_number";

$instructions_stmt = $conn->prepare($instructions_sql);
$instructions_stmt->bind_param("i", $recipe_id);
$instructions_stmt->execute();
$instructions_result = $instructions_stmt->get_result();

if ($instructions_result->num_rows > 0) {
    while ($row = $instructions_result->fetch_assoc()) {
        $instructions[] = [
            'step_number' => $row['step_number'],
            'instruction' => $row['instruction'],
            'image' => $row['image']
        ];
    }
} else {
    // If no separate instructions found, parse from the recipes table
    if (!empty($recipe['instructions'])) {
        $steps = preg_split('/\r\n|\r|\n/', $recipe['instructions']);
        foreach ($steps as $index => $step) {
            $step = trim($step);
            if (!empty($step)) {
                // Remove step numbers if they exist in the text
                $step = preg_replace('/^\d+\.\s/', '', $step);
                $instructions[] = [
                    'step_number' => $index + 1,
                    'instruction' => $step,
                    'image' => null
                ];
            }
        }
    }
}

// Fetch dietary preferences
$preferences_sql = "
    SELECT dp.* FROM dietary_preferences dp
    JOIN recipe_dietary_preferences rdp ON dp.preference_id = rdp.preference_id
    WHERE rdp.recipe_id = ?
";
$preferences_stmt = $conn->prepare($preferences_sql);
$preferences_stmt->bind_param("i", $recipe_id);
$preferences_stmt->execute();
$preferences_result = $preferences_stmt->get_result();
$dietary_preferences = [];
while ($row = $preferences_result->fetch_assoc()) {
    $dietary_preferences[] = $row;
}

// Fetch tags
$tags_sql = "
    SELECT t.* FROM tags t
    JOIN recipe_tags rt ON t.tag_id = rt.tag_id
    WHERE rt.recipe_id = ?
";
$tags_stmt = $conn->prepare($tags_sql);
$tags_stmt->bind_param("i", $recipe_id);
$tags_stmt->execute();
$tags_result = $tags_stmt->get_result();
$tags = [];
while ($row = $tags_result->fetch_assoc()) {
    $tags[] = $row;
}

// Check if user has favorited this recipe
$is_favorited = false;
if ($logged_in) {
    $favorite_sql = "SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?";
    $favorite_stmt = $conn->prepare($favorite_sql);
    $favorite_stmt->bind_param("ii", $user_id, $recipe_id);
    $favorite_stmt->execute();
    $is_favorited = $favorite_stmt->get_result()->num_rows > 0;
}

// Check if user has rated this recipe
$user_rating = null;
if ($logged_in) {
    $rating_sql = "SELECT rating FROM ratings WHERE user_id = ? AND recipe_id = ?";
    $rating_stmt = $conn->prepare($rating_sql);
    $rating_stmt->bind_param("ii", $user_id, $recipe_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result();
    if ($rating_result->num_rows > 0) {
        $user_rating = $rating_result->fetch_assoc()['rating'];
    }
}

// Fetch comments
$comments_sql = "
    SELECT c.*, u.username FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.recipe_id = ? AND c.parent_comment_id IS NULL
    ORDER BY c.created_at DESC
";
$comments_stmt = $conn->prepare($comments_sql);
$comments_stmt->bind_param("i", $recipe_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
$comments = [];
while ($row = $comments_result->fetch_assoc()) {
    $comments[] = $row;
}

// Fetch related recipes (same category, excluding current recipe)
$related_sql = "
    SELECT r.*, u.username, 
    (SELECT AVG(rating) FROM ratings WHERE recipe_id = r.recipe_id) as avg_rating
    FROM recipes r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.category_id = ? AND r.recipe_id != ? AND r.status = 'published'
    ORDER BY RAND()
    LIMIT 3
";
$related_stmt = $conn->prepare($related_sql);
$related_stmt->bind_param("ii", $recipe['category_id'], $recipe_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
$related_recipes = [];
while ($row = $related_result->fetch_assoc()) {
    $related_recipes[] = $row;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($recipe['title']) ?> - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .recipe-header {
            background: #f8f9fa;
            padding: 2rem 0;
        }
        .recipe-image-large {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
        }
        .ingredients-section, .instructions-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .instruction-step {
            display: flex;
            margin-bottom: 20px;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #ef233c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .nutrition-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .recipe-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .comment {
            border-bottom: 1px solid #dee2e6;
            padding: 15px 0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment-avatar {
            width: 40px;
            height: 40px;
            background: #ef233c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .rating-stars {
            cursor: pointer;
        }
        .rating-stars .fas.fa-star {
            color: #ffc107;
        }
        .tag-badge {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar (same as recipes.php) -->
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

    <!-- Recipe Header -->
    <section class="recipe-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="recipes.php">Recipes</a></li>
                    <li class="breadcrumb-item"><a href="recipes.php?category=<?= $recipe['category_id'] ?>"><?= htmlspecialchars($recipe['category_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($recipe['title']) ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Recipe Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Recipe Main Info -->
                    <div class="mb-4">
                        <h1 class="mb-3"><?= htmlspecialchars($recipe['title']) ?></h1>
                        <div class="d-flex align-items-center mb-4">
                            <div class="recipe-rating me-3">
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
                                <span class="ms-1">(<?= $recipe['rating_count'] ?? 0 ?> ratings)</span>
                            </div>
                            <div class="text-muted">By <?= htmlspecialchars($recipe['username']) ?> | Published <?= date('F j, Y', strtotime($recipe['created_at'])) ?></div>
                        </div>
                        
                        <img src="<?= !empty($recipe['image']) ? 'uploads/recipes/' . $recipe['image'] : 'https://placehold.co/800x400' ?>" 
                             alt="<?= htmlspecialchars($recipe['title']) ?>" 
                             class="recipe-image-large mb-4">
                        
                        <?php if (!empty($dietary_preferences)): ?>
                        <div class="mb-3">
                            <?php foreach ($dietary_preferences as $pref): ?>
                                <span class="tag-badge"><i class="fas fa-check me-1"></i><?= htmlspecialchars($pref['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($tags)): ?>
                        <div class="mb-3">
                            <?php foreach ($tags as $tag): ?>
                                <a href="recipes.php?tag=<?= $tag['tag_id'] ?>" class="tag-badge text-decoration-none text-dark">
                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($tag['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <p class="lead"><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
                    </div>

                    <!-- Recipe Actions -->
                    <div class="recipe-actions mb-4">
                        <?php if ($logged_in): ?>
                            <button class="btn btn-outline-danger favorite-btn" 
                                    data-recipe-id="<?= $recipe_id ?>" 
                                    data-favorited="<?= $is_favorited ? '1' : '0' ?>">
                                <i class="<?= $is_favorited ? 'fas' : 'far' ?> fa-heart me-2"></i>
                                <?= $is_favorited ? 'Unfavorite' : 'Favorite' ?>
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <button class="btn btn-outline-secondary share-btn">
                            <i class="fas fa-share-alt me-2"></i>Share
                        </button>
                    </div>

                    <!-- Ingredients -->
                    <div class="ingredients-section">
                        <h2 class="h4 mb-4">Ingredients</h2>
                        <ul class="list-unstyled">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?= htmlspecialchars($ingredient['quantity']) ?> 
                                    <?= htmlspecialchars($ingredient['unit']) ?> 
                                    <?= htmlspecialchars($ingredient['name']) ?>
                                    <?= !empty($ingredient['notes']) ? '(' . htmlspecialchars($ingredient['notes']) . ')' : '' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Instructions -->
                    <div class="instructions-section">
                        <h2 class="h4 mb-4">Instructions</h2>
                        <?php foreach ($instructions as $instruction): ?>
                            <div class="instruction-step">
                                <div class="step-number"><?= $instruction['step_number'] ?></div>
                                <div class="step-content">
                                    <p><?= nl2br(htmlspecialchars($instruction['instruction'])) ?></p>
                                    <?php if (!empty($instruction['image'])): ?>
                                        <img src="uploads/instructions/<?= $instruction['image'] ?>" 
                                             alt="Step <?= $instruction['step_number'] ?>" 
                                             class="img-fluid rounded mt-2">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Nutrition Info -->
                    <?php if (!empty($recipe['nutrition_info'])): ?>
                    <div class="nutrition-info mb-4">
                        <h2 class="h5 mb-3">Nutrition Information</h2>
                        <p><?= nl2br(htmlspecialchars($recipe['nutrition_info'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Rating Section -->
                    <?php if ($logged_in): ?>
                    <div class="rating-section mb-4">
                        <h2 class="h5 mb-3">Rate This Recipe</h2>
                        <div class="rating-stars" data-recipe-id="<?= $recipe_id ?>" data-user-rating="<?= $user_rating ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= ($user_rating && $i <= $user_rating) ? 'fas' : 'far' ?> fa-star" data-rating="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <div class="comments-section">
                        <h2 class="h5 mb-4">Comments (<?= count($comments) ?>)</h2>
                        
                        <?php if ($logged_in): ?>
                        <form id="comment-form" class="mb-4">
                            <input type="hidden" name="recipe_id" value="<?= $recipe_id ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="comment" rows="3" placeholder="Leave a comment..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-info">
                            Please <a href="auth/login.php">log in</a> to leave a comment.
                        </div>
                        <?php endif; ?>

                        <div id="comments-list">
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="d-flex">
                                    <div class="comment-avatar me-3">
                                        <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($comment['username']) ?></div>
                                        <div class="text-muted small mb-2">
                                            <?= date('F j, Y g:i A', strtotime($comment['created_at'])) ?>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Recipe Info Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Recipe Information</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-clock me-2 text-primary"></i>
                                    <strong>Prep Time:</strong> <?= $recipe['prep_time'] ?> mins
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-fire me-2 text-primary"></i>
                                    <strong>Cook Time:</strong> <?= $recipe['cook_time'] ?> mins
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-hourglass-half me-2 text-primary"></i>
                                    <strong>Total Time:</strong> <?= $recipe['total_time'] ?> mins
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-utensils me-2 text-primary"></i>
                                    <strong>Servings:</strong> <?= $recipe['servings'] ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-signal me-2 text-primary"></i>
                                    <strong>Difficulty:</strong> <?= htmlspecialchars($recipe['difficulty_name']) ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-eye me-2 text-primary"></i>
                                    <strong>Views:</strong> <?= $recipe['views'] ?>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Related Recipes -->
                    <?php if (!empty($related_recipes)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Related Recipes</h5>
                            <div class="row">
                                <?php foreach ($related_recipes as $related): ?>
                                <div class="col-12 mb-3">
                                    <div class="d-flex">
                                        <img src="<?= !empty($related['image']) ? 'uploads/recipes/' . $related['image'] : 'https://placehold.co/100x100' ?>" 
                                             alt="<?= htmlspecialchars($related['title']) ?>" 
                                             class="img-fluid rounded" 
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                        <div class="ms-3">
                                            <h6 class="mb-1">
                                                <a href="recipe.php?id=<?= $related['recipe_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($related['title']) ?>
                                                </a>
                                            </h6>
                                            <div class="small text-muted">
                                                <i class="far fa-clock me-1"></i><?= $related['total_time'] ?> mins
                                            </div>
                                            <div class="recipe-rating small mt-1">
                                                <?php
                                                $rating = floatval($related['avg_rating'] ?? 0);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<i class="fas fa-star text-warning"></i>';
                                                    } else {
                                                        echo '<i class="far fa-star text-warning"></i>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer-->
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
                        <!-- Categories would be populated dynamically here -->
                        <a href="recipes.php?category=1">Breakfast</a>
                        <a href="recipes.php?category=2">Lunch</a>
                        <a href="recipes.php?category=3">Dinner</a>
                        <a href="recipes.php?category=4">Desserts</a>
                        <a href="recipes.php?category=5">Appetizers</a>
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
    // Favorite button handler
    $('.favorite-btn').click(function() {
        const btn = $(this);
        const recipeId = btn.data('recipe-id');
        const isFavorited = btn.data('favorited') === 1;
        
        $.ajax({
            url: 'ajax/toggle_favorite.php',
            method: 'POST',
            data: { recipe_id: recipeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (isFavorited) {
                        btn.data('favorited', 0);
                        btn.html('<i class="far fa-heart me-2"></i>Favorite');
                    } else {
                        btn.data('favorited', 1);
                        btn.html('<i class="fas fa-heart me-2"></i>Unfavorite');
                    }
                } else {
                    alert(response.message || 'Error updating favorite status');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    });

    // Rating stars handler
    $('.rating-stars i').click(function() {
        const rating = $(this).data('rating');
        const recipeId = $(this).parent().data('recipe-id');
        
        $.ajax({
            url: 'ajax/rate_recipe.php',
            method: 'POST',
            data: { 
                recipe_id: recipeId,
                rating: rating
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update star display
                    $('.rating-stars i').each(function(index) {
                        if (index < rating) {
                            $(this).removeClass('far').addClass('fas');
                        } else {
                            $(this).removeClass('fas').addClass('far');
                        }
                    });
                    
                    // Update user rating data attribute
                    $('.rating-stars').data('user-rating', rating);
                    
                    // Optional: Update the average rating display
                    if (response.avg_rating && response.rating_count) {
                        updateRatingDisplay(response.avg_rating, response.rating_count);
                    }
                } else {
                    alert(response.message || 'Error rating recipe');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    });

    // Comment form submission handler
    $('#comment-form').submit(function(e) {
        e.preventDefault();
        
        const form = $(this);
        const commentText = form.find('textarea[name="comment"]').val();
        const recipeId = form.find('input[name="recipe_id"]').val();
        
        $.ajax({
            url: 'ajax/add_comment.php',
            method: 'POST',
            data: {
                recipe_id: recipeId,
                comment: commentText
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear the textarea
                    form.find('textarea[name="comment"]').val('');
                    
                    // Prepend the new comment to the comments list
                    const commentHtml = `
                        <div class="comment">
                            <div class="d-flex">
                                <div class="comment-avatar me-3">
                                    ${response.username.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="fw-bold">${response.username}</div>
                                    <div class="text-muted small mb-2">
                                        ${response.created_at}
                                    </div>
                                    <p class="mb-0">${escapeHtml(commentText)}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#comments-list').prepend(commentHtml);
                    
                    // Update comment count
                    const currentCount = $('.comments-section h2').text().match(/\((\d+)\)/)[1];
                    $('.comments-section h2').text(`Comments (${parseInt(currentCount) + 1})`);
                } else {
                    alert(response.message || 'Error posting comment');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    });

    // Share button handler
    $('.share-btn').click(function() {
        // Check if Web Share API is supported
        if (navigator.share) {
            navigator.share({
                title: document.title,
                url: window.location.href
            }).then(() => {
                console.log('Successfully shared');
            }).catch((error) => {
                console.log('Error sharing:', error);
                fallbackShare();
            });
        } else {
            fallbackShare();
        }
    });

    // Helper function for fallback sharing
    function fallbackShare() {
        // Create a temporary input to copy the URL
        const input = document.createElement('input');
        input.style.position = 'fixed';
        input.style.opacity = 0;
        input.value = window.location.href;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        
        // Show a tooltip or alert
        alert('Recipe URL copied to clipboard!');
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper function to update rating display
    function updateRatingDisplay(avgRating, ratingCount) {
        const ratingDiv = $('.recipe-rating').first();
        let starsHtml = '';
        
        for (let i = 1; i <= 5; i++) {
            if (i <= avgRating) {
                starsHtml += '<i class="fas fa-star"></i>';
            } else if (i - 0.5 <= avgRating) {
                starsHtml += '<i class="fas fa-star-half-alt"></i>';
            } else {
                starsHtml += '<i class="far fa-star"></i>';
            }
        }
        
        starsHtml += ` <span class="ms-1">(${ratingCount} ratings)</span>`;
        ratingDiv.html(starsHtml);
    }

    // Newsletter form submission
    $('#newsletter-form').submit(function(e) {
        e.preventDefault();
        const email = $(this).find('input[type="email"]').val();
        
        $.ajax({
            url: 'ajax/subscribe_newsletter.php',
            method: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Thank you for subscribing!');
                    $('#newsletter-form')[0].reset();
                } else {
                    alert(response.message || 'Error subscribing to newsletter');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    });

    // Rating stars hover effect
    $('.rating-stars i').hover(
        function() {
            const rating = $(this).data('rating');
            $('.rating-stars i').each(function(index) {
                if (index < rating) {
                    $(this).removeClass('far').addClass('fas');
                } else {
                    $(this).removeClass('fas').addClass('far');
                }
            });
        },
        function() {
            // Return to the user's rating on mouseout
            const userRating = $('.rating-stars').data('user-rating');
            $('.rating-stars i').each(function(index) {
                if (userRating && index < userRating) {
                    $(this).removeClass('far').addClass('fas');
                } else {
                    $(this).removeClass('fas').addClass('far');
                }
            });
        }
    );

    // Print functionality
    window.print = (function(oldPrint) {
        return function() {
            // Hide non-essential elements for printing
            $('.navbar, .footer, .recipe-actions, .comments-section, .card, .related-recipes').hide();
            
            // Print
            oldPrint();
            
            // Show elements again
            $('.navbar, .footer, .recipe-actions, .comments-section, .card, .related-recipes').show();
        };
    })(window.print);
});
</script>
</body>
</html>