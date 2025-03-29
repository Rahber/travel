<?php
require_once 'common.php';


// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}



includeHeader();

$message = '';

if (isset($_POST['register'])) {
    $conn = null;
    $stmt = null;
    
    try {
        // Rate limiting check
        checkRateLimit('registration_attempt', 300, 3);
        
        // Validate CSRF token
        validateCSRFToken($_POST['csrf_token'] ?? '');

        // Sanitize and validate inputs
        $username = sanitizeString($_POST['username'] ?? '');
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!validateUsername($username)) {
            throw new Exception("Invalid username format!");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format!");
        }

        if (!validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters and contain uppercase, lowercase, number and special character!");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match!");
        }

        $conn = getDbConnectionMysqli();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }

        mysqli_begin_transaction($conn);

        // Check existing username/email using prepared statement
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
        }
        
        $stmt->bind_param("ss", $username, $email);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . htmlspecialchars($stmt->error));
        }
        
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];
        
        if ($count > 0) {
            throw new Exception("Username or email already exists!");
        }

        // Generate activation token and hash password
        $activation_token = bin2hex(random_bytes(32));
        $password_hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);

        // Close previous statement
        $stmt->close();
        $stmt = null;

        // Insert new user using prepared statement
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, activation_token, activated, status, tier, authlevel, first_login_ip, first_login_referer, first_login_browser) VALUES (?, ?, ?, ?, 0, 'active', 'basic', 'user', ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        $referer = filter_var($_SERVER['HTTP_REFERER'] ?? '', FILTER_SANITIZE_URL);
        $browser = filter_var($_SERVER['HTTP_USER_AGENT'] ?? '', FILTER_SANITIZE_STRING);
        
        $stmt->bind_param("sssssss", 
            $username, 
            $email, 
            $password_hash, 
            $activation_token,
            $ip,
            $referer,
            $browser
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . htmlspecialchars($stmt->error));
        }

        $user_id = $conn->insert_id;

        // Generate activation link
        $activation_hash = hash_hmac('sha256', $activation_token, $_SERVER['HTTP_USER_AGENT'] ?? '');
        $activation_link = "https://" . htmlspecialchars($_SERVER['HTTP_HOST']) . "/activate.php?token=" . urlencode($activation_token) . "&hash=" . urlencode($activation_hash);

        // Send activation email
        $to = $email;
        $subject = "Trip!T - Activate Your Account";
        $message_body = "Hi " . htmlspecialchars($username) . ",\n\n";
        $message_body .= "Thank you for registering! Please click the following link to activate your account:\n\n";
        $message_body .= $activation_link . "\n\n";
        $message_body .= "If you did not register for this account, please ignore this email.\n\n";
        $message_body .= "Best regards,\nTrip!T Team";

        $headers = "MIME-Version: 1.0\r\n";
           $headers .= "Content-type: text/html; charset=UTF-8\r\n";
           $headers .= "From: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
           $headers .= "reply-to: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
           $headers .= "X-Mailer: PHP/" . phpversion();

        if(!mail($to, $subject, $message_body, $headers)) {
            throw new Exception("Failed to send activation email");
        }

        // Log registration
        logUserActivity($user_id, 'registration', 'Successful registration');

        mysqli_commit($conn);

        $message = '<div class="alert alert-success">Registration successful! Please check your email for the activation link.</div>';

    } catch (Exception $e) {
        if ($conn && mysqli_ping($conn)) {
            mysqli_rollback($conn);
        }
        $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        error_log("Registration error: " . $e->getMessage());
    } finally {
        if ($stmt) {
            $stmt->close();
        }
        if ($conn) {
            mysqli_close($conn);
        }
    }
}
?>

<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Register</h3>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   maxlength="50" pattern="[A-Za-z0-9_]{3,50}" value="<?php echo DUMMY_USERNAME; ?>"
                                   title="Username must be between 3-50 characters and can only contain letters, numbers and underscore"
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   maxlength="100" value="<?php echo DUMMY_EMAIL; ?>" required> 
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                   title="Must contain at least 8 characters with at least one uppercase letter, one lowercase letter, one number and one special character"
                                   maxlength="72" value="<?php echo DUMMY_PASSWORD; ?>" required>    
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   maxlength="72" value="<?php echo DUMMY_PASSWORD; ?>" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php includeFooter(); ?>
