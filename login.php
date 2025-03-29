<?php
require_once 'common.php';


// Check if user is already logged in
if (isset($_SESSION['user'])) {
    redirectToSite("home.php");
    exit();
}

includeHeader();

$message = '';

if (isset($_POST['login'])) {
    try {
        // Rate limiting check
        checkRateLimit('login_attempt', 300, 5);
        
        // Validate CSRF token
        validateCSRFToken($_POST['csrf_token'] ?? '');

        // Validate inputs
        $username = sanitizeString($_POST['username']);
        $password = $_POST['password'];
        
        if (!$username || !$password) {
            throw new Exception("Please enter both username and password");
        }

        if (!validateUsername($username)) {
            throw new Exception("Invalid username format!");
        }

        $conn = getDbConnectionMysqli();
        
        // Check credentials against database
        $stmt = $conn->prepare("SELECT id, username, password, activated, status, authlevel FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['activated'] == 0) {
                throw new Exception("Please activate your account first. Check your email for activation link.");
            }
            
            if ($user['status'] !== 'active') {
                throw new Exception("Your account is currently " . htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8') . ". Please contact support.");
            }

            // Log successful login
            logUserActivity($user['id'], 'login', 'Successful login');

            // Set session and redirect
            $_SESSION['user'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['authlevel'] = $user['authlevel'];
            
            $redirectUrl = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL);
            
            // Use header instead of JavaScript for redirect
            redirectToSite($redirectUrl ? $redirectUrl : 'home.php');
            exit();
        } else {
            // Log failed login attempt
            logUserActivity($user['id'] ?? null, 'login_attempt', 'Failed login attempt');
            throw new Exception("Invalid username or password");
        }

        $stmt->close();

    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<div class="login-container mt-3">
    <h2>Login</h2>
    <?php echo $message; ?>
    
    <form method="POST" action="" class="p-4">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-group mb-4">
            <label for="username" class="form-label">Username:</label>
            <input type="text" id="username" name="username" class="form-control" 
                   maxlength="50" pattern="[A-Za-z0-9_]{3,50}"
                   title="Username must be between 3-50 characters and can only contain letters, numbers and underscore"
                   value="<?php echo DUMMY_USERNAME; ?>" required>
        </div>
        <div class="form-group mb-4">
            <label for="password" class="form-label">Password:</label>
            <input type="password" id="password" name="password" class="form-control" 
                   maxlength="72" value="<?php echo DUMMY_PASSWORD; ?>" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100 mb-3">Login</button>
    </form>
    <div class="forgot-password text-center">
        <a href="forgot-password.php" class="text-muted">Forgot Password?</a>
        <br>
        <a href="register.php" class="text-muted">Register</a>
    </div>
</div>

<?php includeFooter(); ?>
