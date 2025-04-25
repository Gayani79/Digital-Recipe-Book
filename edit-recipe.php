<?php
/**
 * Recipe App - Edit Recipe Page
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

// Get recipe ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid recipe ID.";
    header("Location: my-recipes.php");
    exit;
}

$recipe_id = $_GET['id'];

// Check if the recipe belongs to the current user
$check_sql = "SELECT * FROM recipes WHERE recipe_id = ? AND user_id = ?";
$recipe = null;

if ($stmt = $conn->prepare($check_sql)) {
    $stmt->bind_param("ii", $recipe_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
    } else {
        // Recipe doesn't belong to user or doesn't exist
        $_SESSION['error'] = "You don't have permission to edit this recipe.";
        header("Location: my-recipes.php");
        exit;
    }
    
    $stmt->close();
}

// Fetch categories for dropdown
$categories = [];
$cat_sql = "SELECT * FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_sql);

if ($cat_result && $cat_result->num_rows > 0) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic recipe information
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $prep_time = isset($_POST['prep_time']) ? (int)$_POST['prep_time'] : 0;
    $cook_time = isset($_POST['cook_time']) ? (int)$_POST['cook_time'] : 0;
    $total_time = $prep_time + $cook_time;
    $servings = isset($_POST['servings']) ? (int)$_POST['servings'] : 1;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $status = $_POST['status'];
    
    // Ingredients and instructions as JSON
    $ingredients = isset($_POST['ingredients']) ? $_POST['ingredients'] : [];
    $instructions = isset($_POST['instructions']) ? $_POST['instructions'] : [];
    $ingredients_json = json_encode($ingredients);
    $instructions_json = json_encode($instructions);
    
    // Image handling
    $image = $recipe['image']; // Default to existing image
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/recipes/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old image if it exists and a new one is uploaded
        if (!empty($recipe['image']) && file_exists($upload_dir . $recipe['image'])) {
            unlink($upload_dir . $recipe['image']);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('recipe_') . '.' . $file_extension;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
            $image = $new_filename;
        }
    }
    
    // Validate required fields
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Recipe title is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Recipe description is required.";
    }
    
    if (empty($ingredients)) {
        $errors[] = "At least one ingredient is required.";
    }
    
    if (empty($instructions)) {
        $errors[] = "At least one instruction step is required.";
    }
    
    // Update recipe if no errors
    if (empty($errors)) {
        $update_sql = "UPDATE recipes SET 
                       title = ?, 
                       description = ?, 
                       ingredients = ?, 
                       instructions = ?, 
                       prep_time = ?, 
                       cook_time = ?, 
                       total_time = ?, 
                       servings = ?, 
                       category_id = ?, 
                       image = ?, 
                       status = ?, 
                       updated_at = NOW() 
                       WHERE recipe_id = ? AND user_id = ?";
        
        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param("ssssiiiisssii", 
                $title, 
                $description, 
                $ingredients_json, 
                $instructions_json, 
                $prep_time, 
                $cook_time, 
                $total_time, 
                $servings, 
                $category_id, 
                $image, 
                $status, 
                $recipe_id, 
                $user_id
            );
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Recipe updated successfully!";
                header("Location: my-recipes.php");
                exit;
            } else {
                $errors[] = "Error updating recipe: " . $conn->error;
            }
            
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
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
    <title>Edit Recipe - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .form-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-section-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }
        
        .dynamic-field {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .remove-field {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 16px;
        }
        
        .add-field-btn {
            display: block;
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            text-align: center;
            border-radius: 8px;
            color: #6c757d;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .add-field-btn:hover {
            border-color: #28a745;
            color: #28a745;
            background-color: #f8f9fa;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .note {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .status-toggle {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
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
                <h1 class="mb-3"><i class="fas fa-edit me-2"></i>Edit Recipe</h1>
                <p class="text-muted">Update your recipe details and share your culinary masterpiece with the world.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="my-recipes.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to My Recipes
                </a>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Recipe Form -->
        <form action="edit-recipe.php?id=<?= $recipe_id ?>" method="post" enctype="multipart/form-data">
            <!-- Basic Information -->
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-info-circle me-2"></i>Basic Information</h3>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="title" class="form-label">Recipe Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($recipe['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" <?= ($recipe['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($recipe['description'] ?? '') ?></textarea>
                    <div class="note">Provide a brief description of your recipe. What makes it special? What inspired you to create it?</div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="prep_time" class="form-label">Prep Time (minutes)</label>
                        <input type="number" class="form-control" id="prep_time" name="prep_time" min="0" value="<?= $recipe['prep_time'] ?? 0 ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="cook_time" class="form-label">Cook Time (minutes)</label>
                        <input type="number" class="form-control" id="cook_time" name="cook_time" min="0" value="<?= $recipe['cook_time'] ?? 0 ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="total_time" class="form-label">Total Time</label>
                        <input type="number" class="form-control" id="total_time" value="<?= $recipe['total_time'] ?? 0 ?>" disabled>
                        <div class="note">Automatically calculated</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="servings" class="form-label">Servings</label>
                        <input type="number" class="form-control" id="servings" name="servings" min="1" value="<?= $recipe['servings'] ?? 1 ?>">
                    </div>
                </div>
            </div>

            <!-- Ingredients -->
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-leaf me-2"></i>Ingredients</h3>
                <div class="note mb-3">List all ingredients needed for your recipe. Be as specific as possible with quantities and measurements.</div>
                
                <div id="ingredients-container">
                    <?php 
                    $ingredients = json_decode($recipe['ingredients'] ?? '[]', true);
                    if (!empty($ingredients)):
                        foreach ($ingredients as $index => $ingredient):
                    ?>
                    <div class="dynamic-field">
                        <input type="text" class="form-control" name="ingredients[]" value="<?= htmlspecialchars($ingredient) ?>" placeholder="e.g., 2 cups all-purpose flour" required>
                        <button type="button" class="remove-field" onclick="removeField(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="dynamic-field">
                        <input type="text" class="form-control" name="ingredients[]" placeholder="e.g., 2 cups all-purpose flour" required>
                        <button type="button" class="remove-field" onclick="removeField(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="add-field-btn mt-3" onclick="addIngredient()">
                    <i class="fas fa-plus me-2"></i>Add Ingredient
                </div>
            </div>

            <!-- Instructions -->
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-list-ol me-2"></i>Instructions</h3>
                <div class="note mb-3">Detail the step-by-step process for preparing your recipe.</div>
                
                <div id="instructions-container">
                    <?php 
                    $instructions = json_decode($recipe['instructions'] ?? '[]', true);
                    if (!empty($instructions)):
                        foreach ($instructions as $index => $instruction):
                    ?>
                    <div class="dynamic-field">
                        <textarea class="form-control" name="instructions[]" rows="2" placeholder="Describe this step..." required><?= htmlspecialchars($instruction) ?></textarea>
                        <button type="button" class="remove-field" onclick="removeField(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="dynamic-field">
                        <textarea class="form-control" name="instructions[]" rows="2" placeholder="Describe this step..." required></textarea>
                        <button type="button" class="remove-field" onclick="removeField(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="add-field-btn mt-3" onclick="addInstruction()">
                    <i class="fas fa-plus me-2"></i>Add Instruction
                </div>
            </div>

            <!-- Recipe Image -->
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-image me-2"></i>Recipe Image</h3>
                
                <?php if (!empty($recipe['image'])): ?>
                <div class="mb-3">
                    <label class="form-label">Current Image</label>
                    <div>
                        <img src="uploads/recipes/<?= htmlspecialchars($recipe['image']) ?>" alt="Recipe Image" class="preview-image">
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="image" class="form-label">Upload New Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <div class="note">Upload a new image to replace the current one. Leave empty to keep the current image.</div>
                </div>
            </div>

            <!-- Publication Status -->
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-toggle-on me-2"></i>Publication Status</h3>
                
                <div class="status-toggle">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="status" id="status-published" value="published" <?= ($recipe['status'] === 'published') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status-published">
                            <i class="fas fa-globe me-1"></i> Published
                        </label>
                        <small class="text-muted ms-2">Your recipe will be visible to everyone.</small>
                    </div>
                    <div class="form-check form-check-inline mt-2">
                        <input class="form-check-input" type="radio" name="status" id="status-draft" value="draft" <?= ($recipe['status'] === 'draft') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status-draft">
                            <i class="fas fa-edit me-1"></i> Draft
                        </label>
                        <small class="text-muted ms-2">Only you can see your recipe until you publish it.</small>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-between mt-4">
                <a href="my-recipes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
                <div>
                    <button type="submit" name="save_draft" class="btn btn-primary me-2">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
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

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Update total time when prep or cook time changes
        document.getElementById('prep_time').addEventListener('input', updateTotalTime);
        document.getElementById('cook_time').addEventListener('input', updateTotalTime);
        
        function updateTotalTime() {
            const prepTime = parseInt(document.getElementById('prep_time').value) || 0;
            const cookTime = parseInt(document.getElementById('cook_time').value) || 0;
            document.getElementById('total_time').value = prepTime + cookTime;
        }
        
        // Add new ingredient field
        function addIngredient() {
            const container = document.getElementById('ingredients-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field';
            newField.innerHTML = `
                <input type="text" class="form-control" name="ingredients[]" placeholder="e.g., 2 cups all-purpose flour" required>
                <button type="button" class="remove-field" onclick="removeField(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(newField);
        }
        
        // Add new instruction field
        function addInstruction() {
            const container = document.getElementById('instructions-container');
            const newField = document.createElement('div');
            newField.className = 'dynamic-field';
            newField.innerHTML = `
                <textarea class="form-control" name="instructions[]" rows="2" placeholder="Describe this step..." required></textarea>
                <button type="button" class="remove-field" onclick="removeField(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(newField);
        }
        
        // Remove field
        function removeField(button) {
            const field = button.parentNode;
            const container = field.parentNode;
            
            // Make sure there's at least one field left
            if (container.childElementCount > 1) {
                container.removeChild(field);
            } else {
                alert('You need at least one item in this section.');
            }
        }
        
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Create preview if it doesn't exist, otherwise update
                    let preview = document.querySelector('.preview-image');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'preview-image';
                        document.getElementById('image').parentNode.appendChild(preview);
                    }
                    preview.src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>