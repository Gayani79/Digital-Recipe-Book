<?php
/**
 * Recipe App - Registration Page
 * Author: Gayani Sandeepa
 */

// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../includes/db_connection.php';

$error = '';
$success = '';

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Password confirmation does not match.";
    } else {
        // Check if username already exists
        $sql = "SELECT user_id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = "This username is already taken.";
            } else {
                $stmt->close();
                
                // Check if email already exists
                $sql = "SELECT user_id FROM users WHERE email = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $error = "This email is already registered.";
                    } else {
                        $stmt->close();
                        
                        // Insert new user
                        $sql = "INSERT INTO users (username, email, password, first_name, last_name, registration_date, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')";
                        
                        if ($stmt = $conn->prepare($sql)) {
                            // Hash the password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            $stmt->bind_param("sssss", $username, $email, $hashed_password, $first_name, $last_name);
                            
                            if ($stmt->execute()) {
                                $success = "Registration successful! You can now <a href='login.php'>login</a>.";
                                
                                // Log user activity
                                $user_id = $stmt->insert_id;
                                $activity_type = 'registration';
                                
                                $log_sql = "INSERT INTO user_activity (user_id, activity_type, entity_id, entity_type) VALUES (?, ?, ?, ?)";
                                if ($log_stmt = $conn->prepare($log_sql)) {
                                    $entity_id = $user_id;
                                    $entity_type = 'user';
                                    $log_stmt->bind_param("isis", $user_id, $activity_type, $entity_id, $entity_type);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                            } else {
                                $error = "Something went wrong. Please try again later.";
                            }
                            
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe App - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/register.css">
    
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-container">
                    <div class="register-header">
                        <h1>Recipe App</h1>
                        <p>Create an account to share and discover recipes</p>
                    </div>
                    <div class="register-form">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus register-icon"></i>
                            <h2 class="mt-3">Create Account</h2>
                            <p class="text-muted">Join our recipe-sharing community</p>
                        </div>
                        
                        <?php if(!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" name="first_name" class="form-control" placeholder="First Name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" name="last_name" class="form-control" placeholder="Last Name">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" value="" id="terms_agree" required>
                                <label class="form-check-label" for="terms_agree">
                                    I agree to the <a href="#" style="color: #F54EA2;">Terms & Conditions</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-register">
                                <i class="fas fa-user-plus me-2"></i> Register
                            </button>
                        </form>
                        
                        <div class="login-link">
                            <p>Already have an account? <a href="login.php">Login Here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Registration page JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Animation for registration form
            const registerContainer = document.querySelector('.register-container');
            registerContainer.style.opacity = '0';
            
            setTimeout(() => {
                registerContainer.style.transition = 'opacity 0.5s ease-in-out';
                registerContainer.style.opacity = '1';
            }, 200);
            
            // Form validation (client-side)
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const password = document.querySelector('input[name="password"]').value;
                const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                
                if (password !== confirmPassword) {
                    event.preventDefault();
                    alert('Password confirmation does not match!');
                }
                
                if (password.length < 6) {
                    event.preventDefault();
                    alert('Password must be at least 6 characters long!');
                }
            });
        });
    </script>
</body>
</html>