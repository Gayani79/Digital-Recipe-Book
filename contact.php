<?php
/**
 * Recipe App - Contact Page
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
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Process contact form submission
$message_sent = false;
$form_error = false;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $form_error = true;
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = true;
        $error_message = "Please enter a valid email address.";
    } else {
        // Store message in database
        $insert_sql = "INSERT INTO contact_messages (name, email, subject, message, user_id, created_at) 
                       VALUES (?, ?, ?, ?, ?, NOW())";
                       
        if ($stmt = $conn->prepare($insert_sql)) {
            $stmt->bind_param("ssssi", $name, $email, $subject, $message, $user_id);
            
            if ($stmt->execute()) {
                $message_sent = true;
            } else {
                $form_error = true;
                $error_message = "Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        } else {
            $form_error = true;
            $error_message = "Database error. Please try again later.";
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
    <title>Contact Us - Recipe App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .contact-section {
            padding: 80px 0;
        }
        .contact-form {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .contact-info {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        .contact-info-item {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        .contact-info-icon {
            width: 50px;
            height: 50px;
            background-color: #ff642e;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .contact-page-title {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        .contact-page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: #ff642e;
        }
        .section-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .map-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 50px;
        }
        .form-label {
            font-weight: 500;
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
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
                    </li>
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
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                    <h1 class="section-title mb-2">Contact Us</h1>
                    <p class="lead">We'd love to hear from you! Use the form below to send us a message.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <?php if ($message_sent): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i>Thank you for your message! We'll get back to you as soon as possible.
                </div>
            <?php endif; ?>
            
            <?php if ($form_error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
        
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="contact-form">
                        <h2 class="contact-page-title">Send Us a Message</h2>
                        <p class="section-subtitle">Have a question or feedback? Fill out the form below and we'll get back to you as soon as possible.</p>
                        
                        <form action="contact.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Your Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= $logged_in ? htmlspecialchars($username) : '' ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" name="submit_contact" class="btn btn-view-more">Send Message</button>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="contact-info">
                        <h2 class="contact-page-title">Contact Information</h2>
                        <p class="section-subtitle">Feel free to contact us using the information below.</p>
                        
                        <div class="contact-info-item">
                            <div class="contact-info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                            <h5 class="mb-1">Office Address</h5>
                            <p class="mb-0">123 Galle Road, Colombo 03<br>Sri Lanka</p>
                            </div>
                        </div>
                        
                        <div class="contact-info-item">
                            <div class="contact-info-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Phone Number</h5>
                                <p class="mb-0">+94 123-4567</p>
                            </div>
                        </div>
                        
                        <div class="contact-info-item">
                            <div class="contact-info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Email Address</h5>
                                <p class="mb-0">info@recipeapp.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-info-item">
                            <div class="contact-info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Office Hours</h5>
                                <p class="mb-0">Monday - Friday: 9:00 AM - 5:00 PM<br>Saturday & Sunday: Closed</p>
                            </div>
                        </div>
                        
                        <div class="social-links mt-4">
                            <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-pinterest"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Map Section -->
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4047271.322044894!2d78.16011061689055!3d7.851730309936339!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae2593cf65a1e9d%3A0xe13da4b400e2d38c!2sSri%20Lanka!5e0!3m2!1sen!2sus!4v1650000000000!5m2!1sen!2sus" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title text-center mb-5">Frequently Asked Questions</h2>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How do I create an account?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To create an account, click on the "Register" button in the top right corner of the page. Fill out the registration form with your details and submit. Once registered, you'll be able to access all features of our Recipe App.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    How do I share my own recipes?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    After logging in, click on the "Add Recipe" button in the navigation bar. Fill out the recipe form with all the details including ingredients, instructions, cooking time, and an image of your dish. Once submitted, your recipe will be reviewed and published on our platform.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Can I save recipes to view later?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! When viewing a recipe, simply click the "Add to Favorites" button. All your favorite recipes can be accessed in the "Favorites" section under your account menu.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    How do I create a meal plan?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    After logging in, go to "Meal Plans" in your account menu. Click on "Create New Meal Plan" and follow the instructions to select recipes for different days of the week. You can create multiple meal plans and adjust them at any time.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFive">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                    How can I provide feedback about a recipe?
                                </button>
                            </h2>
                            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    When viewing a recipe, scroll down to the comments section. You can leave a comment and also rate the recipe from 1 to 5 stars. Your feedback helps other users and the recipe creator.
                                </div>
                            </div>
                        </div>
                    </div>
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
        $(document).ready(function() {
            // Form validation
            $('#newsletter-form').submit(function(e) {
                e.preventDefault();
                alert('Thank you for subscribing to our newsletter!');
                $(this).find('input[type="email"]').val('');
            });
        });
    </script>
</body>
</html>