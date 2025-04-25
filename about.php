<?php
/**
 * Recipe App - About Page
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

// Fetch categories for the footer
$categories = [];
$categories_sql = "SELECT * FROM categories ORDER BY name LIMIT 5";
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
    <title>About Us - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* About page specific styles */
        .about-hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://media.istockphoto.com/id/1330605905/photo/citrus-fruit-on-textured-green-background-workspace-blog-hero-header-flat-lay.jpg?s=612x612&w=0&k=20&c=QOP1k8zHQi-8pQ88Pc976ck5p6TKDCLefNBYakdPjqw=');
            background-size: cover;
            background-position: center;
            color: #fff;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 50px;
        }
        
        .mission-section {
            padding: 60px 0;
        }
        
        
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-item {
            padding: 20px 30px;
            border-left: 2px solid #dc3545;
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item:before {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #dc3545;
            left: -9px;
            top: 20px;
        }
        
        .stats-section {
            background-color: #f8f9fa;
            padding: 60px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #dc3545;
        }
        
        .testimonial {
            background-color: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .testimonial-author img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
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
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About Us</a>
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

    <!-- About Hero Section -->
    <section class="about-hero">
        <div class="container">
            <h1>About Recipe App</h1>
            <p class="lead">Connecting food lovers and empowering home cooks around the world</p>
        </div>
    </section>

    <!-- Our Mission Section -->
    <section class="mission-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="https://lifehacker.com/imagery/articles/01HF2GG557ZPA8WJB9A2NWWRPG/hero-image.fill.size_1248x702.v1699835004.jpg" alt="Our Mission" class="img-fluid rounded">
                </div>
                <div class="col-lg-6">
                    <h2 class="section-title">Our Mission</h2>
                    <p>At Recipe App, we believe that cooking is more than just preparing food—it's about creating memories, expressing creativity, and bringing people together. Our mission is to empower home cooks around the world by providing a platform to discover, create, and share culinary inspiration.</p>
                    <p>We strive to make cooking accessible to everyone, regardless of their skill level or background. Whether you're a seasoned chef or just starting your culinary journey, Recipe App is your trusted companion in the kitchen.</p>
                    <div class="mt-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-hands-helping fa-2x me-3" style="color: #dc3545;"></i>
                            <div>
                                <h5 class="mb-0">Building Community</h5>
                                <p class="mb-0">Connecting food enthusiasts from diverse culinary backgrounds</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-lightbulb fa-2x me-3" style="color: #dc3545;"></i>
                            <div>
                                <h5 class="mb-0">Inspiring Creativity</h5>
                                <p class="mb-0">Encouraging culinary innovation and personal expression</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-book-open fa-2x me-3" style="color: #dc3545;"></i>
                            <div>
                                <h5 class="mb-0">Preserving Traditions</h5>
                                <p class="mb-0">Celebrating cultural heritage through food</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   
    <!-- Our Story Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">Our Story</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="timeline">
                        <div class="timeline-item">
                            <h4>2018 - The Beginning</h4>
                            <p>Recipe App started as a small personal blog by Gayani Sandeepa, who wanted to share her family recipes and cooking adventures. What began as a hobby quickly gained popularity among food enthusiasts.</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2019 - Building the Community</h4>
                            <p>As the blog grew, Gayani realized the need for a platform where everyone could contribute and share their recipes. With the help of Amal Perera, a web developer and fellow food lover, they launched the first version of the Recipe App.</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2020 - Expanding Horizons</h4>
                            <p>The Recipe App team grew with the addition of Emily Wong and Marcus Johnson. Together, they expanded the platform's features, including meal planning, nutritional information, and improved recipe organization.</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2022 - Growing Globally</h4>
                            <p>Recipe App went international, supporting multiple languages and regional cuisines from around the world. Our community grew to over 100,000 members, sharing their culinary traditions and innovations.</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2023 - Today and Beyond</h4>
                            <p>Today, Recipe App continues to innovate and improve. We're committed to our mission of building a global cooking community where everyone can find inspiration and share their passion for food.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <h2 class="section-title text-center mb-5">Recipe App by the Numbers</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">10K+</div>
                        <p>Recipes Shared</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">250K+</div>
                        <p>Active Users</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <p>Cuisine Types</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">15M+</div>
                        <p>Recipe Views</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">What Our Community Says</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="testimonial">
                        <p class="mb-3">"Recipe App has transformed my cooking journey! I've discovered so many amazing recipes and connected with food lovers around the world. It's more than just a recipe platform—it's a community."</p>
                        <div class="d-flex align-items-center testimonial-author">
                            <div>
                                <h5 class="mb-0">Sarah M.</h5>
                                <p class="text-muted mb-0">Member since 2020</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="testimonial">
                        <p class="mb-3">"As a professional chef, I'm impressed by the quality and diversity of recipes on this platform. I've found inspiration for my restaurant menu and enjoy sharing my own creations with the community."</p>
                        <div class="d-flex align-items-center testimonial-author">
                            <div>
                                <h5 class="mb-0">Chef Carlos R.</h5>
                                <p class="text-muted mb-0">Member since 2019</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="testimonial">
                        <p class="mb-3">"I never thought I could cook until I found Recipe App. The detailed instructions and supportive community gave me the confidence to try new recipes. Now cooking is my favorite hobby!"</p>
                        <div class="d-flex align-items-center testimonial-author">
                            <div>
                                <h5 class="mb-0">Priya K.</h5>
                                <p class="text-muted mb-0">Member since 2021</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="call-to-action">
        <div class="container">
            <?php if (!$logged_in): ?>
            <h2 class="cta-title">Join Our Cooking Community Today</h2>
            <p class="cta-text">Become a part of our growing family of food enthusiasts. Share recipes, discover new flavors, and connect with people who share your passion for cooking.</p>
            <a href="auth/register.php" class="btn cta-button">Create Your Account</a>
            <?php else: ?>
            <h2 class="cta-title">Share Your Culinary Story</h2>
            <p class="cta-text">Every recipe has a story. Share yours with our community and inspire others with your unique culinary perspective.</p>
            <a href="add-recipe.php" class="btn cta-button">Share Your Recipe</a>
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
                        <?php foreach ($categories as $category): ?>
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
        // Form submission handler for newsletter
        document.getElementById('newsletter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for subscribing to our newsletter!');
            this.reset();
        });
    </script>
</body>
</html>