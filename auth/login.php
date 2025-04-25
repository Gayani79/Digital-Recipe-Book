<?php
/**
 * Recipe App - Login Page
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

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate form input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Query to check user credentials
        $sql = "SELECT user_id, username, password FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                // Check if username exists
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $username, $hashed_password);
                    
                    if ($stmt->fetch()) {
                        // Verify password
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $username;
                            
                            // Redirect to index page
                            header("Location: ../index.php");
                            exit();
                        } else {
                            $error = "Invalid username or password.";
                        }
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
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
    <title>Recipe App - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <h1>Recipe App</h1>
                        <p>Find and share amazing recipes</p>
                    </div>
                    <div class="login-form">
                        <div class="text-center mb-4">
                            <i class="fas fa-utensils login-icon"></i>
                            <h2 class="mt-3">Welcome Back</h2>
                            <p class="text-muted">Login to your account</p>
                        </div>
                        
                        <?php if(!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                            
                            <div class="forgot-password">
                                <a href="#">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </form>
                        
                        <div class="register-link">
                            <p>Don't have an account? <a href="register.php">Register Now</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any login page JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Animation for login form
            const loginContainer = document.querySelector('.login-container');
            loginContainer.style.opacity = '0';
            
            setTimeout(() => {
                loginContainer.style.transition = 'opacity 0.5s ease-in-out';
                loginContainer.style.opacity = '1';
            }, 200);
        });
    </script>
</body>
</html>