<?php
require_once 'common.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

includeHeader();

$message = '';
$showResetForm = false;
$showActivationLink = false;
$conn = getDbConnectionMysqli();

// Handle password reset link from email
if (isset($_GET['token'])) {
    try {
        $token = $conn->real_escape_string(sanitizeString($_GET['token']));
        
        // Verify token and check if it's expired
        $sql = "SELECT pr.user_id, pr.expires_at, u.username, u.status 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.reset_token = ? AND pr.used = 0 
                AND pr.expires_at > NOW()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $reset = $result->fetch_assoc();
        
        if ($reset) {
            $showResetForm = true;
            
            // Handle new password submission
            if (isset($_POST['update'])) {
                $password = $conn->real_escape_string($_POST['password']);
                $confirm_password = $conn->real_escape_string($_POST['confirm_password']);
                
                if (!validatePassword($password)) {
                    throw new Exception("Password must be at least 8 characters and contain uppercase, lowercase, number and special character!");
                }
                
                if ($password !== $confirm_password) {
                    throw new Exception("Passwords do not match");
                }

                $conn->begin_transaction();
                
                // Update password and set status to inactive
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                $updateStmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW(), status = 'active' WHERE id = ?");
                $updateStmt->bind_param("si", $passwordHash, $reset['user_id']);
                $updateStmt->execute();
                
                // Mark token as used
                $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                // Log password reset
                logUserActivity($reset['user_id'], 'password_reset', 'Password reset via email link');
                
                $conn->commit();
                $showActivationLink = true;
                $message = "Password updated successfully";
            }
        } else {
            throw new Exception("Invalid or expired reset link");
        }
    } catch (Exception $e) {
        if ($conn->in_transaction()) {
            $conn->rollback();
        }
        $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        error_log("Password reset error: " . $e->getMessage());
    }
}

// Handle initial password reset request
if (isset($_POST['reset']) && !$showResetForm) {
    try {
        $email = $conn->real_escape_string(sanitizeEmail($_POST['email']));
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Generate reset token and store
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at, used, request_ip) 
                   VALUES (?, ?, ?, 0, ?)");
            $stmt->bind_param("isss", 
                $user['id'], 
                $token, 
                $expires,
                $_SERVER['REMOTE_ADDR']
            );
            $stmt->execute();
            
            // Build reset link
            $resetLink = "https://" . $_SERVER['HTTP_HOST'] . 
                        dirname($_SERVER['PHP_SELF']) . 
                        "/forgot-password.php?token=" . urlencode($token);
            
            // Send password reset email
            $to = $user['email'];
            $subject = "Trip!T -  Password Reset Request";
           // Headers for HTML email
           $headers = "MIME-Version: 1.0\r\n";
           $headers .= "Content-type: text/html; charset=UTF-8\r\n";
           $headers .= "From: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
           $headers .= "reply-to: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
           $headers .= "X-Mailer: PHP/" . phpversion();
            $message = "Hello " . $user['username'] . ",\n\n" .
                      "You recently requested to reset your password. Click the link below to reset it:\n\n" .
                      $resetLink . "\n\n" .
                      "This link will expire in 1 hour.\n\n" .
                      "If you did not request this reset, please ignore this email.";
            
            mail($to, $subject, $message, $headers);
            
            logUserActivity($user['id'], 'password_reset_request', 'Password reset email sent');
            $message = "If an account exists with that email address, password reset instructions will be sent shortly.";
        } else {
            // Show same message even if email not found to prevent email enumeration
            $message = "If an account exists with that email address, password reset instructions will be sent shortly.";
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        error_log("Password reset request error: " . $e->getMessage());
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">
                        <?php echo htmlspecialchars($showResetForm ? 'Set New Password' : 'Reset Password'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-info'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($showResetForm): ?>
                        <!-- New Password Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                       title="Must contain at least 8 characters with at least one uppercase letter, one lowercase letter, one number and one special character"
                                       maxlength="72"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       maxlength="72"
                                       required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="update" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Email Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       maxlength="254"
                                       required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="reset" class="btn btn-primary">Send Reset Link</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php includeFooter(); ?>
