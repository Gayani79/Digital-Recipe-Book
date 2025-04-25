<?php
/**
 * Recipe App - Add Recipe Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php?redirect=add-recipe.php");
    exit;
}

// Include database connection
require_once 'includes/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch categories for dropdown
$categories = [];
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch difficulty levels for dropdown
$difficulty_levels = [];
$difficulty_sql = "SELECT * FROM difficulty_levels ORDER BY difficulty_id";
$difficulty_result = $conn->query($difficulty_sql);
if ($difficulty_result && $difficulty_result->num_rows > 0) {
    while ($row = $difficulty_result->fetch_assoc()) {
        $difficulty_levels[] = $row;
    }
}

// Fetch dietary preferences for checkboxes
$dietary_preferences = [];
$dietary_sql = "SELECT * FROM dietary_preferences ORDER BY name";
$dietary_result = $conn->query($dietary_sql);
if ($dietary_result && $dietary_result->num_rows > 0) {
    while ($row = $dietary_result->fetch_assoc()) {
        $dietary_preferences[] = $row;
    }
}

// Fetch tags for multi-select
$tags = [];
$tags_sql = "SELECT * FROM tags ORDER BY name";
$tags_result = $conn->query($tags_sql);
if ($tags_result && $tags_result->num_rows > 0) {
    while ($row = $tags_result->fetch_assoc()) {
        $tags[] = $row;
    }
}

// Fetch units for ingredient dropdown
$units = [];
$units_sql = "SELECT * FROM units ORDER BY name";
$units_result = $conn->query($units_sql);
if ($units_result && $units_result->num_rows > 0) {
    while ($row = $units_result->fetch_assoc()) {
        $units[] = $row;
    }
}

// Fetch ingredients for autocomplete
$ingredients = [];
$ingredients_sql = "SELECT * FROM ingredients ORDER BY name";
$ingredients_result = $conn->query($ingredients_sql);
if ($ingredients_result && $ingredients_result->num_rows > 0) {
    while ($row = $ingredients_result->fetch_assoc()) {
        $ingredients[] = $row;
    }
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Recipe basic info
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $instructions = trim($_POST['instructions']);
        $prep_time = !empty($_POST['prep_time']) ? (int)$_POST['prep_time'] : NULL;
        $cook_time = !empty($_POST['cook_time']) ? (int)$_POST['cook_time'] : NULL;
        $servings = !empty($_POST['servings']) ? (int)$_POST['servings'] : NULL;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : NULL;
        $difficulty_id = !empty($_POST['difficulty_id']) ? (int)$_POST['difficulty_id'] : NULL;
        $calories = !empty($_POST['calories']) ? (int)$_POST['calories'] : NULL;
        $status = $_POST['status'];
        
        // Calculate total time
        $total_time = ($prep_time !== NULL && $cook_time !== NULL) ? ($prep_time + $cook_time) : NULL;
        
        // Validate required fields
        if (empty($title) || empty($instructions)) {
            throw new Exception("Title and instructions are required fields.");
        }
        
        // Handle image upload
        $image = NULL;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, and GIF images are allowed.");
            }
            
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['image']['size'] > $max_size) {
                throw new Exception("Image file size must be less than 5MB.");
            }
            
            $upload_dir = 'uploads/recipes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image = $file_name;
            } else {
                throw new Exception("Failed to upload image.");
            }
        }
        
        // Insert recipe
        $recipe_sql = "INSERT INTO recipes (title, description, instructions, prep_time, cook_time, total_time, 
                      servings, difficulty_id, image, calories_per_serving, user_id, category_id, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($recipe_sql);
        $stmt->bind_param("sssiiiiisiiis", $title, $description, $instructions, $prep_time, $cook_time, 
                         $total_time, $servings, $difficulty_id, $image, $calories, $user_id, $category_id, $status);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding recipe: " . $stmt->error);
        }
        
        $recipe_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle instructions steps (if any)
        if (isset($_POST['instruction_steps']) && is_array($_POST['instruction_steps'])) {
            $step_number = 1;
            foreach ($_POST['instruction_steps'] as $idx => $instruction_text) {
                if (empty(trim($instruction_text))) continue;
                
                $instruction_sql = "INSERT INTO recipe_instructions (recipe_id, step_number, instruction) 
                                VALUES (?, ?, ?)";
                $instruction_stmt = $conn->prepare($instruction_sql);
                $instruction_stmt->bind_param("iis", $recipe_id, $step_number, $instruction_text);
                $instruction_stmt->execute();
                $instruction_stmt->close();
                
                $step_number++;
            }
        }

        // Handle ingredients
        if (isset($_POST['ingredient_items']) && is_array($_POST['ingredient_items'])) {
            foreach ($_POST['ingredient_items'] as $idx => $ingredient_name) {
                if (empty($ingredient_name)) continue;
                
                $quantity = isset($_POST['ingredient_quantities'][$idx]) ? (float)$_POST['ingredient_quantities'][$idx] : NULL;
                $unit_id = isset($_POST['ingredient_units'][$idx]) && !empty($_POST['ingredient_units'][$idx]) ? (int)$_POST['ingredient_units'][$idx] : NULL;
                $notes = isset($_POST['ingredient_notes'][$idx]) ? trim($_POST['ingredient_notes'][$idx]) : NULL;
                
                // Check if ingredient exists, if not, create it
                $ingredient_id = NULL;
                $check_ingredient_sql = "SELECT ingredient_id FROM ingredients WHERE name = ?";
                $check_stmt = $conn->prepare($check_ingredient_sql);
                $check_stmt->bind_param("s", $ingredient_name);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $ingredient_id = $row['ingredient_id'];
                } else {
                    // Insert new ingredient
                    $insert_ingredient_sql = "INSERT INTO ingredients (name) VALUES (?)";
                    $insert_stmt = $conn->prepare($insert_ingredient_sql);
                    $insert_stmt->bind_param("s", $ingredient_name);
                    $insert_stmt->execute();
                    $ingredient_id = $insert_stmt->insert_id;
                    $insert_stmt->close();
                }
                
                $check_stmt->close();
                
                // Add ingredient to recipe
                $recipe_ingredient_sql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit_id, notes)
                                        VALUES (?, ?, ?, ?, ?)";
                $ri_stmt = $conn->prepare($recipe_ingredient_sql);
                $ri_stmt->bind_param("iidis", $recipe_id, $ingredient_id, $quantity, $unit_id, $notes);
                $ri_stmt->execute();
                $ri_stmt->close();
            }
        }
        
        // Handle dietary preferences
        if (isset($_POST['dietary_preferences']) && is_array($_POST['dietary_preferences'])) {
            foreach ($_POST['dietary_preferences'] as $preference_id) {
                $pref_sql = "INSERT INTO recipe_dietary_preferences (recipe_id, preference_id) VALUES (?, ?)";
                $pref_stmt = $conn->prepare($pref_sql);
                $pref_stmt->bind_param("ii", $recipe_id, $preference_id);
                $pref_stmt->execute();
                $pref_stmt->close();
            }
        }
        
        // Handle tags
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            foreach ($_POST['tags'] as $tag_id) {
                $tag_sql = "INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)";
                $tag_stmt = $conn->prepare($tag_sql);
                $tag_stmt->bind_param("ii", $recipe_id, $tag_id);
                $tag_stmt->execute();
                $tag_stmt->close();
            }
        }
        
        // Handle nutritional info
        if (isset($_POST['add_nutrition']) && $_POST['add_nutrition'] == '1') {
            $carbs = !empty($_POST['carbs']) ? (float)$_POST['carbs'] : NULL;
            $protein = !empty($_POST['protein']) ? (float)$_POST['protein'] : NULL;
            $fat = !empty($_POST['fat']) ? (float)$_POST['fat'] : NULL;
            $saturated_fat = !empty($_POST['saturated_fat']) ? (float)$_POST['saturated_fat'] : NULL;
            $sugar = !empty($_POST['sugar']) ? (float)$_POST['sugar'] : NULL;
            $fiber = !empty($_POST['fiber']) ? (float)$_POST['fiber'] : NULL;
            $sodium = !empty($_POST['sodium']) ? (float)$_POST['sodium'] : NULL;
            
            $nutrition_sql = "INSERT INTO nutritional_info (recipe_id, calories, carbohydrates, protein, fat, 
                            saturated_fat, sugar, fiber, sodium)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $nutrition_stmt = $conn->prepare($nutrition_sql);
            $nutrition_stmt->bind_param("iiddddddd", $recipe_id, $calories, $carbs, $protein, $fat, 
                                      $saturated_fat, $sugar, $fiber, $sodium);
            $nutrition_stmt->execute();
            $nutrition_stmt->close();
        }
        
        // Log user activity
        $activity_sql = "INSERT INTO user_activity (user_id, activity_type, entity_id, entity_type) 
                        VALUES (?, 'recipe_create', ?, 'recipe')";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->bind_param("ii", $user_id, $recipe_id);
        $activity_stmt->execute();
        $activity_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Recipe added successfully!";
        
        // Redirect to the recipe page
        header("Location: recipe.php?id=" . $recipe_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Close database connection is moved to the bottom of the HTML or in separate footer file
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Recipe - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
            font-weight: 600;
        }
        
        .ingredient-row {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        
        .btn-remove-ingredient {
            color: #dc3545;
            cursor: pointer;
        }
        
        .image-preview {
            width: 100%;
            height: 200px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .upload-label {
            display: block;
            background-color: #f8f9fa;
            color: #6c757d;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-label:hover {
            background-color: #e9ecef;
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        .note-editor {
            margin-bottom: 20px;
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
        
        #nutritionToggle {
            cursor: pointer;
            color: #007bff;
            margin-bottom: 15px;
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
                </ul>
                <div class="d-flex">
                    <a href="add-recipe.php" class="btn btn-outline-light me-2 active">
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
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Add New Recipe</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Recipe</li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="recipe-form">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">Recipe Information</h3>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Recipe Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                <small class="text-muted">A brief description of your recipe.</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Select a category</option>
                                            <?php foreach($categories as $category): ?>
                                                <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="difficulty_id" class="form-label">Difficulty Level</label>
                                        <select class="form-select" id="difficulty_id" name="difficulty_id">
                                            <option value="">Select difficulty</option>
                                            <?php foreach($difficulty_levels as $level): ?>
                                                <option value="<?= $level['difficulty_id'] ?>"><?= htmlspecialchars($level['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="prep_time" class="form-label">Prep Time (minutes)</label>
                                        <input type="number" class="form-control" id="prep_time" name="prep_time" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="cook_time" class="form-label">Cook Time (minutes)</label>
                                        <input type="number" class="form-control" id="cook_time" name="cook_time" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="servings" class="form-label">Servings</label>
                                        <input type="number" class="form-control" id="servings" name="servings" min="1">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="calories" class="form-label">Calories per Serving</label>
                                <input type="number" class="form-control" id="calories" name="calories" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Recipe Image</label>
                                <div class="image-preview" id="imagePreview">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>Select an image</p>
                                    </div>
                                </div>
                                <label for="image" class="upload-label">
                                    <i class="fas fa-upload me-2"></i>Upload Image
                                </label>
                                <input type="file" class="form-control d-none" id="image" name="image" accept="image/*">
                                <small class="text-muted">Max file size: 5MB. Recommended size: 1200x800px.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label d-block">Recipe Status</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="statusPublished" value="published" checked>
                                    <label class="form-check-label" for="statusPublished">Published</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="statusDraft" value="draft">
                                    <label class="form-check-label" for="statusDraft">Draft</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="form-section">
                    <h3 class="form-section-title">Instructions *</h3>
                    <div class="mb-3">
                        <textarea id="instructions" name="instructions" class="form-control" rows="10" required></textarea>
                        <small class="text-muted">Step by step instructions on how to prepare the recipe.</small>
                    </div>
                </div>

                <!-- Ingredients -->
                <div class="form-section">
                    <h3 class="form-section-title">Ingredients</h3>
                    <div id="ingredientContainer">
                        <div class="ingredient-row">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <div class="mb-2">
                                        <label class="form-label">Ingredient</label>
                                        <input type="text" class="form-control ingredient-name" name="ingredient_items[]" list="ingredient-list">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-2">
                                        <label class="form-label">Quantity</label>
                                        <input type="text" class="form-control" name="ingredient_quantities[]">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label">Unit</label>
                                        <select class="form-select" name="ingredient_units[]">
                                            <option value="">Select unit</option>
                                            <?php foreach($units as $unit): ?>
                                                <option value="<?= $unit['unit_id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-2 d-flex align-items-end h-100">
                                        <a href="#" class="btn-remove-ingredient"><i class="fas fa-times"></i></a>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-2">
                                        <label class="form-label">Notes</label>
                                        <input type="text" class="form-control" name="ingredient_notes[]" placeholder="e.g., finely chopped, peeled, etc.">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <datalist id="ingredient-list">
                        <?php foreach($ingredients as $ingredient): ?>
                            <option value="<?= htmlspecialchars($ingredient['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    
                    <div class="mt-3">
                        <button type="button" id="addIngredient" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i>Add Ingredient
                        </button>
                    </div>
                </div>

                <!-- Tags and Dietary Preferences -->
                <div class="form-section">
                    <h3 class="form-section-title">Tags & Dietary Information</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags</label>
                                <select class="form-control" id="tags" name="tags[]" multiple>
                                    <?php foreach($tags as $tag): ?>
                                        <option value="<?= $tag['tag_id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select tags that describe your recipe.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Dietary Preferences</label>
                                <div class="row">
                                    <?php foreach($dietary_preferences as $preference): ?>
                                        <div class="col-md-6">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="dietary_preferences[]" value="<?= $preference['preference_id'] ?>" id="pref_<?= $preference['preference_id'] ?>">
                                                <label class="form-check-label" for="pref_<?= $preference['preference_id'] ?>">
                                                    <?= htmlspecialchars($preference['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nutritional Information (Collapsible) -->
                    <div class="form-section">
                        <h3 class="form-section-title">Nutritional Information</h3>
                        <div id="nutritionToggle">
                            <i class="fas fa-plus-circle me-2"></i>Add Nutritional Information
                        </div>
                        <div id="nutritionForm" style="display: none;">
                            <input type="hidden" name="add_nutrition" value="0" id="add_nutrition">
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="carbs" class="form-label">Carbohydrates (g)</label>
                                        <input type="number" class="form-control" id="carbs" name="carbs" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="protein" class="form-label">Protein (g)</label>
                                        <input type="number" class="form-control" id="protein" name="protein" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fat" class="form-label">Fat (g)</label>
                                        <input type="number" class="form-control" id="fat" name="fat" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="saturated_fat" class="form-label">Saturated Fat (g)</label>
                                        <input type="number" class="form-control" id="saturated_fat" name="saturated_fat" min="0" step="0.1">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="sugar" class="form-label">Sugar (g)</label>
                                        <input type="number" class="form-control" id="sugar" name="sugar" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="fiber" class="form-label">Fiber (g)</label>
                                        <input type="number" class="form-control" id="fiber" name="fiber" min="0" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="sodium" class="form-label">Sodium (mg)</label>
                                        <input type="number" class="form-control" id="sodium" name="sodium" min="0" step="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Submit Button -->
                    <div class="form-section text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Recipe
                        </button>
                        <a href="my-recipes.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                    </form>
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


<!-- JavaScript Section -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize rich text editor for instructions
        $('#instructions').summernote({
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
        
        // Initialize select2 for tags
        $('#tags').select2({
            placeholder: 'Select tags',
            allowClear: true
        });
        
        // Image preview
        $('#image').change(function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').html('<img src="' + e.target.result + '" alt="Recipe Preview">');
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Ingredient functionality
        $('#addIngredient').click(function() {
            const ingredientRow = `
                <div class="ingredient-row">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="mb-2">
                                <label class="form-label">Ingredient</label>
                                <input type="text" class="form-control ingredient-name" name="ingredient_items[]" list="ingredient-list">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Quantity</label>
                                <input type="text" class="form-control" name="ingredient_quantities[]">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label">Unit</label>
                                <select class="form-select" name="ingredient_units[]">
                                    <option value="">Select unit</option>
                                    <?php foreach($units as $unit): ?>
                                        <option value="<?= $unit['unit_id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2 d-flex align-items-end h-100">
                                <a href="#" class="btn-remove-ingredient"><i class="fas fa-times"></i></a>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-2">
                                <label class="form-label">Notes</label>
                                <input type="text" class="form-control" name="ingredient_notes[]" placeholder="e.g., finely chopped, peeled, etc.">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#ingredientContainer').append(ingredientRow);
        });
        
        // Remove ingredient
        $(document).on('click', '.btn-remove-ingredient', function(e) {
            e.preventDefault();
            
            const ingredientContainer = $('#ingredientContainer');
            
            // Don't remove if it's the only ingredient row
            if (ingredientContainer.children('.ingredient-row').length > 1) {
                $(this).closest('.ingredient-row').remove();
            } else {
                alert('You need at least one ingredient.');
            }
        });
        
        // Nutrition information toggle
        $('#nutritionToggle').on('click', function() {
            const nutritionForm = $('#nutritionForm');
            const addNutrition = $('#add_nutrition');
            
            if (nutritionForm.is(':visible')) {
                nutritionForm.hide();
                $(this).html('<i class="fas fa-plus-circle me-2"></i>Add Nutritional Information');
                addNutrition.val('0');
            } else {
                nutritionForm.show();
                $(this).html('<i class="fas fa-minus-circle me-2"></i>Hide Nutritional Information');
                addNutrition.val('1');
            }
        });
        
        // Form validation before submission
        $('#recipe-form').on('submit', function(e) {
            const title = $('#title').val().trim();
            const instructions = $('#instructions').val().trim();
            
            if (!title) {
                e.preventDefault();
                alert('Please enter a recipe title.');
                $('#title').focus();
                return false;
            }
            
            if (!instructions) {
                e.preventDefault();
                alert('Please enter recipe instructions.');
                $('#instructions').focus();
                return false;
            }
            
            return true;
        });
    });
</script>
</body>
</html>