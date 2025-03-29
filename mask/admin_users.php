<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php'; // Include common.php for session and security functions
verifyAdminAccess();
includeHeader();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['user_id'])) {
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                    if ($user_id === false) {
                        die('Invalid user ID');
                    }
                    
                    // Get user details before deletion for logging
                    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    // Delete the user
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    
                    // Log the deletion using the logUserActivity function
                    logUserActivity($_SESSION['user_id'], 'delete_user', "Deleted user #{$user_id}: " . $user['username']);
                    
                    echo '<div class="alert alert-success">User deleted successfully!</div>';
                }
                break;
            case 'edit':
                if (isset($_POST['user_id'])) {
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                    if ($user_id === false) {
                        die('Invalid user ID');
                    }

                    // Validate and sanitize inputs
                    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
                    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
                    $country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
                    $mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING);
                    $preference = filter_input(INPUT_POST, 'preference', FILTER_SANITIZE_STRING);
                    $activated = filter_input(INPUT_POST, 'activated', FILTER_VALIDATE_INT);
                    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                    $tier = filter_input(INPUT_POST, 'tier', FILTER_SANITIZE_STRING);
                    $authlevel = filter_input(INPUT_POST, 'authlevel', FILTER_SANITIZE_STRING);
                    
                    // Get original user data for comparison
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $original_user = $stmt->get_result()->fetch_assoc();
                    
                    // Update user
                    $stmt = $conn->prepare("UPDATE users SET 
                        username = ?,
                        email = ?,
                        first_name = ?,
                        last_name = ?,
                        country = ?,
                        mobile = ?,
                        preference = ?,
                        activated = ?,
                        status = ?,
                        tier = ?,
                        authlevel = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    
                    $stmt->bind_param("sssssissssi", $username, $email, $first_name, $last_name, $country, $mobile, $preference, $activated, $status, $tier, $authlevel, $user_id);
                    $stmt->execute();
                    
                    // Build description of changes
                    $changes = [];
                    foreach($original_user as $key => $value) {
                        if(isset($_POST[$key]) && $_POST[$key] != $value) {
                            $changes[] = "$key: " . htmlspecialchars($value) . " -> " . htmlspecialchars($_POST[$key]);
                        }
                    }
                    $change_description = "Updated user #{$user_id}: " . implode(", ", $changes);
                    
                    logUserActivity($_SESSION['user_id'], 'edit_user', $change_description);
                    
                    echo '<div class="alert alert-success">User updated successfully!</div>';
                }
                break;
        }
    }
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get filter values
$user_id_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Build query with filters
$where_clause = "";
$params = [];
$param_types = "";

if ($user_id_filter) {
    $where_clause = " WHERE id = ? ";
    $params[] = $user_id_filter;
    $param_types .= "i";
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM users" . $where_clause;
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $items_per_page);

// Get filtered users with pagination
$query = "SELECT * FROM users" . $where_clause . " ORDER BY id DESC LIMIT ? OFFSET ?";

if (!empty($params)) {
    $params[] = $items_per_page;
    $params[] = $offset;
    $param_types .= "ii";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $items_per_page, $offset);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1>User Management</h1>

    <!-- Filter Form -->
    <form method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="number" class="form-control" name="user_id" placeholder="Filter by User ID" 
                           value="<?= htmlspecialchars($user_id_filter) ?>">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <?php if($user_id_filter): ?>
                        <a href="?" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
    
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Auth Level</th>
                <th>Status</th>
                <th>Tier</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td>
                    <a href="#" class="user-details" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                       onclick="populateUserModal(<?= htmlspecialchars(json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                        <?= htmlspecialchars($user['username']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['authlevel']) ?></td>
                <td>
                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($user['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($user['tier']) ?></td>
                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal"
                            onclick="populateUserModal(<?= htmlspecialchars(json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">Edit</button>
                    <button class="btn btn-sm btn-<?= $user['status'] === 'active' ? 'warning' : 'success' ?>"
                            onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= $user['status'] ?>')">
                        <?= $user['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= $user_id_filter ? '&user_id='.$user_id_filter : '' ?>">First</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page-1 ?><?= $user_id_filter ? '&user_id='.$user_id_filter : '' ?>">Previous</a>
                </li>
            <?php endif; ?>

            <?php 
            // Display "..." if there are pages before the first page
            if ($start > 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            // Display two pages before and after the current page
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $user_id_filter ? '&user_id='.$user_id_filter : '' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            // Display "..." if there are pages after the last page
            if ($end < $total_pages) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            <?php if($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page+1 ?><?= $user_id_filter ? '&user_id='.$user_id_filter : '' ?>">Next</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?><?= $user_id_filter ? '&user_id='.$user_id_filter : '' ?>">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" id="edit_country" name="country">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="edit_mobile" name="mobile">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Preference</label>
                            <input type="text" class="form-control" id="edit_preference" name="preference">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tier</label>
                            <select class="form-select" id="edit_tier" name="tier">
                                <option value="basic">Basic</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Auth Level</label>
                            <select class="form-select" id="edit_authlevel" name="authlevel">
                                <option value="user">User</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_activated" name="activated" value="1">
                            <label class="form-check-label">Account Activated</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function populateUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_country').value = user.country || '';
    document.getElementById('edit_mobile').value = user.mobile || '';
    document.getElementById('edit_preference').value = user.preference || '';
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_tier').value = user.tier;
    document.getElementById('edit_authlevel').value = user.authlevel;
    document.getElementById('edit_activated').checked = user.activated == 1;
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    if(confirm('Are you sure you want to change this user\'s status to ' + newStatus + '?')) {
        const formData = new FormData();
        formData.append('action', 'edit');
        formData.append('user_id', userId);
        formData.append('status', newStatus);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(() => location.reload())
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the user status');
        });
    }
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(() => location.reload())
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the user');
        });
    }
}

document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch(window.location.href, {
        method: 'POST',
        body: new FormData(this),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
    })
    .then(() => location.reload())
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the user');
    });
});
</script>

<?php includeFooter(); ?>