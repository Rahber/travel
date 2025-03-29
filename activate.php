<?php
require_once 'common.php';
includeHeader();

$message = '';

if (isset($_GET['token']) && isset($_GET['hash'])) {
    // Validate token format - only allow alphanumeric characters
    $token = trim(filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING));
    $provided_hash = trim(filter_input(INPUT_GET, 'hash', FILTER_SANITIZE_STRING));
    
    if (!preg_match('/^[a-zA-Z0-9]+$/', $token)) {
        $message = '<div class="alert alert-danger">Invalid token format.</div>';
    } else {
        $conn = null;
        $stmt = null;
        
        try {
            $conn = getDbConnectionMysqli();
            mysqli_begin_transaction($conn);
            
            // Find user with matching token
            $stmt = $conn->prepare("SELECT * FROM users WHERE activation_token = ? AND activated = 0");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $token);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                // Verify hash matches
                $expected_hash = hash_hmac('sha256', $token, $_SERVER['HTTP_USER_AGENT']);
                if (!hash_equals($expected_hash, $provided_hash)) {
                    throw new Exception("Invalid activation hash.");
                }
                
                // Close previous statement
                $stmt->close();
                $stmt = null;
                
                // Update user status to activated
                $stmt = $conn->prepare("UPDATE users SET activated = 1, activation_token = NULL WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $user['id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                // Send welcome email
                $to = filter_var($user['email'], FILTER_SANITIZE_EMAIL);
                $subject = "Welcome to Trip!T - Your account has been activated";
                $username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
                $message_body = "
                    <html>
                    <head>
                        <title>Welcome to Travel Booking Portal</title>
                    </head>
                    <body>
                        <h2>Welcome to Travel Booking Portal!</h2>
                        <p>Dear {$username},</p>
                        <p>Your account has been successfully activated. You can now log in and start planning your next adventure!</p>
                        <p>Thank you for joining our community.</p>
                        <p>Best regards,<br>Travel Booking Portal Team</p>
                    </body>
                    </html>
                ";

                // Headers for HTML email
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                $headers .= "reply-to: Trip!T <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                // Send email
                if (!mail($to, $subject, $message_body, $headers)) {
                    throw new Exception("Failed to send welcome email");
                }

                // Close previous statement
                $stmt->close();
                $stmt = null;

                // Log successful activation
                $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, description, ip_address, browser, created_at) VALUES (?, 'activation', 'Account activated successfully', ?, ?, NOW())");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $ip = $_SERVER['REMOTE_ADDR'];
                $browser = $_SERVER['HTTP_USER_AGENT'];
                
                $stmt->bind_param("iss", $user['id'], $ip, $browser);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                mysqli_commit($conn);

                $message = '<div class="alert alert-success">
                    Your account has been successfully activated! A welcome email has been sent to your registered email address.
                    <br><br>
                    <a href="login.php" class="btn btn-primary">Click here to login</a>
                </div>';
            } else {
                throw new Exception("Invalid activation token or account already activated.");
            }
        } catch (Exception $e) {
            if ($conn && mysqli_ping($conn)) {
                mysqli_rollback($conn);
            }
            $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
            error_log("Activation error: " . $e->getMessage());
        } finally {
            if ($stmt) {
                $stmt->close();
            }
            if ($conn) {
                mysqli_close($conn);
            }
        }
    }
} else {
    $message = '<div class="alert alert-danger">Invalid activation request.</div>';
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Account Activation</h3>
                </div>
                <div class="card-body text-center">
                    <?php echo $message; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php includeFooter(); ?>
