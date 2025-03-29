<?php
require_once 'common.php';
requireLogin();

includeHeader();

$message = '';
$user_id = $_SESSION['user_id'];
$csrf_token = initCSRFToken();

$conn = getDbConnectionMysqli();

// Fetch current user info
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, mobile, country FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validateCSRFToken($_POST['csrf_token']);

        // Update password
        if (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!validatePassword($new_password)) {
                throw new Exception("Password must be at least 8 characters and contain uppercase, lowercase, number and special character!");
            }

            // Verify current password and update
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            if (!password_verify($current_password, $hash)) {
                throw new Exception("Current password is incorrect!");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match!");
            }

            if ($current_password === $new_password) {
                throw new Exception("New password must be different from current password!");
            }

            $new_hash = password_hash($new_password, PASSWORD_ARGON2ID);
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);
            $stmt->execute();
            $stmt->close();
            
            logUserActivity($user_id, 'password_update', 'Password updated successfully');
            $message = '<div class="alert alert-success">Password updated successfully!</div>';
        }

        // Update email
        if (isset($_POST['update_email'])) {
            $new_email = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format!");
            }

            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $new_email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()[0] > 0) {
                throw new Exception("Email already in use!");
            }
            $stmt->close();

            $activation_token = bin2hex(random_bytes(32));
            
            $stmt = $conn->prepare("UPDATE users SET email = ?, activation_token = ?, activated = 0, status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ssi", $new_email, $activation_token, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $activation_hash = hash_hmac('sha256', $activation_token, $_SERVER['HTTP_USER_AGENT']);
            $activation_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . urlencode($activation_token) . "&hash=" . urlencode($activation_hash);
            
            logUserActivity($user_id, 'email_update', 'Email updated successfully');
            $message = '<div class="alert alert-success">Email updated successfully! Please check your new email to reactivate your account.<br>
                       <a href="' . htmlspecialchars($activation_link, ENT_QUOTES, 'UTF-8') . '">Activate Account</a></div>';
            $user['email'] = $new_email;
        }

        // Update personal information
        if (isset($_POST['update_info'])) {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $mobile = trim($_POST['mobile']);
            $country = trim($_POST['country']);

            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, mobile = ?, country = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $mobile, $country, $user_id);
            $stmt->execute();
            $stmt->close();

            logUserActivity($user_id, 'info_update', 'Personal information updated successfully');
            $message = '<div class="alert alert-success">Personal information updated successfully!</div>';

            // Refetch user information
            $stmt = $conn->prepare("SELECT username, email, first_name, last_name, mobile, country FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        error_log("Profile update error: " . $e->getMessage());
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Profile Settings</h3>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>

                    <div class="mb-5">
                        <h4 class="border-bottom pb-2">Account Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>First Name:</strong> <span id="first_name_display"><?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Last Name:</strong> <span id="last_name_display"><?php echo htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                                <p><strong>Mobile:</strong> <span id="mobile_display"><?php echo htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                                <p><strong>Country:</strong> <span id="country_display"><?php echo htmlspecialchars($user['country'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <h4 class="border-bottom pb-2">Change Password</h4>
                        <form method="POST" action="" autocomplete="off" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-md-12">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                       title="Must contain at least 8 characters with at least one uppercase letter, one lowercase letter, one number and one special character"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>

                    <div class="mb-5">
                        <h4 class="border-bottom pb-2">Change Email</h4>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-md-8">
                                <label for="new_email" class="form-label">New Email</label>
                                <input type="email" class="form-control" id="new_email" name="new_email" maxlength="100" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" name="update_email" class="btn btn-primary w-100">Update Email</button>
                            </div>
                        </form>
                    </div>

                    <div class="mb-4">
                        <h4 class="border-bottom pb-2">Update Personal Information</h4>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="USA" <?php echo ($user['country'] == 'USA') ? 'selected' : ''; ?>>🇺🇸 USA</option>
                                    <option value="Canada" <?php echo ($user['country'] == 'Canada') ? 'selected' : ''; ?>>🇨🇦 Canada</option>
                                    <option value="UK" <?php echo ($user['country'] == 'UK') ? 'selected' : ''; ?>>🇬🇧 UK</option>
                                    <option value="Australia" <?php echo ($user['country'] == 'Australia') ? 'selected' : ''; ?>>🇦🇺 Australia</option>
                                    <!-- Add more countries and flags as needed -->
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="update_info" class="btn btn-primary">Update Information</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php includeFooter(); ?>
