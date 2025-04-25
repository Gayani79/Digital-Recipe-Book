-- Recipe App Database Schema
-- Author: Gayani Sandeepa

-- Create Database with appropriate character set and collation
CREATE DATABASE IF NOT EXISTS recipe_app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE recipe_app;

-- MySQL configuration to allow larger index sizes
-- Uncomment these if you have access to MySQL server configuration
-- SET GLOBAL innodb_file_format=Barracuda;
-- SET GLOBAL innodb_large_prefix=ON;

-- Users Table - reduced VARCHAR sizes for indexable columns
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_image VARCHAR(191) DEFAULT 'default_profile.jpg',
    bio TEXT,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    is_admin TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
) ENGINE=InnoDB;

-- Categories Table - reduced VARCHAR sizes for indexable columns
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(191),
    parent_category_id INT NULL DEFAULT NULL
) ENGINE=InnoDB;

-- Add the foreign key for parent category after the table exists
ALTER TABLE categories
ADD CONSTRAINT fk_category_parent
FOREIGN KEY (parent_category_id) REFERENCES categories(category_id) ON DELETE SET NULL;

-- Difficulty Levels Table
CREATE TABLE IF NOT EXISTS difficulty_levels (
    difficulty_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

-- Units Table (for ingredients)
CREATE TABLE IF NOT EXISTS units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    abbreviation VARCHAR(10)
) ENGINE=InnoDB;

-- Ingredients Table
CREATE TABLE IF NOT EXISTS ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(191),
    category VARCHAR(50)
) ENGINE=InnoDB;

-- Tags Table (created before recipe_tags)
CREATE TABLE IF NOT EXISTS tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Dietary Preferences Table (created before recipes)
CREATE TABLE IF NOT EXISTS dietary_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

-- Recipes Table - reduced VARCHAR sizes for indexable columns
CREATE TABLE IF NOT EXISTS recipes (
    recipe_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    instructions TEXT NOT NULL,
    prep_time INT,
    cook_time INT,
    total_time INT,
    servings INT,
    difficulty_id INT,
    image VARCHAR(191),
    video_url VARCHAR(191),
    calories_per_serving INT,
    user_id INT NOT NULL,
    category_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('published', 'draft', 'archived') DEFAULT 'published',
    featured TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (difficulty_id) REFERENCES difficulty_levels(difficulty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Recipe Instructions Table
CREATE TABLE IF NOT EXISTS recipe_instructions (
    instruction_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    step_number INT NOT NULL,
    instruction TEXT NOT NULL,
    image VARCHAR(191),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Also add the missing column to recipe_ingredients table
ALTER TABLE recipe_ingredients ADD COLUMN ingredient_order INT DEFAULT 0 AFTER notes;

-- Recipe Ingredients Table
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2),
    unit_id INT,
    notes VARCHAR(191),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Recipe Images Table (multiple images per recipe)
CREATE TABLE IF NOT EXISTS recipe_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    image_url VARCHAR(191) NOT NULL,
    caption VARCHAR(191),
    display_order INT DEFAULT 0,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recipe-Tag Relationship Table
CREATE TABLE IF NOT EXISTS recipe_tags (
    recipe_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (recipe_id, tag_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recipe Ratings Table
CREATE TABLE IF NOT EXISTS ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (recipe_id, user_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recipe Comments Table (with self-reference handled properly)
CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    parent_comment_id INT DEFAULT NULL,
    status ENUM('approved', 'pending', 'spam') DEFAULT 'approved',
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add the self-reference foreign key after the table exists
ALTER TABLE comments
ADD CONSTRAINT fk_comment_parent
FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE SET NULL;

-- User Favorites Table
CREATE TABLE IF NOT EXISTS favorites (
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Meal Plans Table
CREATE TABLE IF NOT EXISTS meal_plans (
    meal_plan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Meal Plan Items Table
CREATE TABLE IF NOT EXISTS meal_plan_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id INT NOT NULL,
    recipe_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    meal_type ENUM('Breakfast', 'Lunch', 'Dinner', 'Snack'),
    notes TEXT,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(meal_plan_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Shopping Lists Table
CREATE TABLE IF NOT EXISTS shopping_lists (
    list_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Shopping List Items Table
CREATE TABLE IF NOT EXISTS shopping_list_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    ingredient_id INT,
    custom_item VARCHAR(100),
    quantity DECIMAL(10,2),
    unit_id INT,
    is_checked TINYINT(1) DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (list_id) REFERENCES shopping_lists(list_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Recipe Dietary Preferences Table
CREATE TABLE IF NOT EXISTS recipe_dietary_preferences (
    recipe_id INT NOT NULL,
    preference_id INT NOT NULL,
    PRIMARY KEY (recipe_id, preference_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (preference_id) REFERENCES dietary_preferences(preference_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Dietary Preferences Table
CREATE TABLE IF NOT EXISTS user_dietary_preferences (
    user_id INT NOT NULL,
    preference_id INT NOT NULL,
    PRIMARY KEY (user_id, preference_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (preference_id) REFERENCES dietary_preferences(preference_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Nutritional Information Table
CREATE TABLE IF NOT EXISTS nutritional_info (
    nutrition_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL UNIQUE,
    calories INT,
    carbohydrates DECIMAL(10,2),
    protein DECIMAL(10,2),
    fat DECIMAL(10,2),
    saturated_fat DECIMAL(10,2),
    sugar DECIMAL(10,2),
    fiber DECIMAL(10,2),
    sodium DECIMAL(10,2),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Activity Log
CREATE TABLE IF NOT EXISTS user_activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('recipe_create', 'recipe_edit', 'comment', 'rating', 'favorite', 'login', 'registration'),
    entity_id INT,
    entity_type VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cooking Tips Table
CREATE TABLE IF NOT EXISTS cooking_tips (
    tip_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    tip TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recipe Collections Table
CREATE TABLE IF NOT EXISTS collections (
    collection_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Collection Items Table
CREATE TABLE IF NOT EXISTS collection_items (
    collection_id INT NOT NULL,
    recipe_id INT NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    PRIMARY KEY (collection_id, recipe_id),
    FOREIGN KEY (collection_id) REFERENCES collections(collection_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(191) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Password Reset Table
CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(100) NOT NULL,
    token VARCHAR(191) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX password_resets_email (email)
) ENGINE=InnoDB;

-- Insert default data for testing

-- Default difficulty levels
INSERT INTO difficulty_levels (name, description) VALUES 
('Easy', 'Simple recipes for beginners with minimal cooking experience'),
('Medium', 'Recipes requiring some cooking experience and moderate techniques'),
('Hard', 'Complex recipes requiring advanced cooking skills');

-- Default measurement units
INSERT INTO units (name, abbreviation) VALUES 
('Teaspoon', 'tsp'),
('Tablespoon', 'tbsp'),
('Cup', 'cup'),
('Milliliter', 'ml'),
('Liter', 'L'),
('Fluid Ounce', 'fl oz'),
('Pint', 'pt'),
('Quart', 'qt'),
('Gallon', 'gal'),
('Gram', 'g'),
('Kilogram', 'kg'),
('Ounce', 'oz'),
('Pound', 'lb'),
('Pinch', 'pinch'),
('Piece', 'pc'),
('Slice', 'slice'),
('Clove', 'clove'),
('Bunch', 'bunch'),
('To taste', '');

-- Default dietary preferences
INSERT INTO dietary_preferences (name, description) VALUES 
('Vegetarian', 'No meat, poultry, or seafood'),
('Vegan', 'No animal products including eggs, dairy, and honey'),
('Gluten-Free', 'No ingredients containing gluten'),
('Dairy-Free', 'No milk or dairy products'),
('Nut-Free', 'No nuts or nut-derived ingredients'),
('Low-Carb', 'Limited carbohydrate content'),
('Keto', 'High fat, adequate protein, and very low carbohydrate'),
('Paleo', 'Based on foods presumed to be available to paleolithic humans');

-- Default categories
INSERT INTO categories (name, description, image) VALUES 
('Breakfast', 'Start your day right with these breakfast recipes', 'assests/images/breakfast.jpg'),
('Lunch', 'Midday meal recipes perfect for a break', 'assests/images/lunch.jpg'),
('Dinner', 'Evening meal recipes to end your day', 'assests/images/dinner.jpg'),
('Appetizers', 'Small dishes served before a meal', 'assests/images/appetizers.jpg'),
('Soups', 'Warm and comforting soup recipes', 'assests/images/soups.jpg'),
('Salads', 'Fresh and healthy salad recipes', 'assests/images/salads.jpg'),
('Main Dishes', 'Centerpiece recipes for your meal', 'assests/images/main_dishes.jpg'),
('Side Dishes', 'Complementary dishes to accompany your main course', 'assests/images/side_dishes.jpg'),
('Desserts', 'Sweet treats to end your meal', 'assests/images/desserts.jpg'),
('Baking', 'Bread, pastry, and other baked goods', 'assests/images/baking.jpg'),
('Beverages', 'Drinks from smoothies to cocktails', 'assests/images/beverages.jpg'),
('Snacks', 'Quick bites between meals', 'assests/images/snacks.jpg');

-- Default tags
INSERT INTO tags (name) VALUES 
('Quick'), ('Easy'), ('Healthy'), ('Budget-Friendly'), ('Family-Friendly'),
('Comfort Food'), ('Spicy'), ('Sweet'), ('Savory'), ('Holiday'),
('Summer'), ('Winter'), ('Fall'), ('Spring'), ('Party'),
('BBQ'), ('One-pot'), ('High-Protein'), ('Low-Calorie'), ('Mediterranean');

-- Create admin user (password: admin123)
INSERT INTO users (username, email, password, first_name, last_name, is_admin, status) VALUES 
('admin', 'admin@recipeapp.com', '$2y$10$MgBHDOrZueBFVkm5PZ1I9.jyQHvMTIsqMoPVNE8UbqHVcHJ2yOeNi', 'Admin', 'User', 1, 'active');

-- Create indexes for performance optimization
CREATE INDEX idx_recipes_user_id ON recipes(user_id);
CREATE INDEX idx_recipes_category_id ON recipes(category_id);
CREATE INDEX idx_recipes_status ON recipes(status);
CREATE INDEX idx_recipes_featured ON recipes(featured);
CREATE INDEX idx_recipe_ingredients_recipe_id ON recipe_ingredients(recipe_id);
CREATE INDEX idx_recipe_ingredients_ingredient_id ON recipe_ingredients(ingredient_id);
CREATE INDEX idx_comments_recipe_id ON comments(recipe_id);
CREATE INDEX idx_comments_user_id ON comments(user_id);
CREATE INDEX idx_ratings_recipe_id ON ratings(recipe_id);
CREATE INDEX idx_user_activity_user_id ON user_activity(user_id);
CREATE INDEX idx_user_activity_type ON user_activity(activity_type);