<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php'; // Include common.php for session and security functions
verifyAdminAccess();
includeHeader();

// Handle log deletion actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'delete_all':
                $stmt = $conn->prepare("DELETE FROM user_logs");
                $stmt->execute();
                $stmt->close();

                logUserActivity($_SESSION['user_id'], 'delete_all_logs', 'Deleted all system logs');

                echo '<div class="alert alert-success">All logs deleted successfully!</div>';
                break;
                
            case 'delete_by_user':
                if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                    $user_id = intval($_POST['user_id']);

                    // Get username for logging
                    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $user_stmt->bind_param("i", $user_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    $user = $user_result->fetch_assoc();
                    $user_stmt->close();

                    if ($user) {
                        $stmt = $conn->prepare("DELETE FROM user_logs WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $stmt->close();

                        // Log the action
                        logUserActivity($_SESSION['user_id'], 'delete_user_logs', "Deleted all logs for user: " . htmlspecialchars($user['username']));

                        echo '<div class="alert alert-success">Logs for selected user deleted successfully!</div>';
                    }
                }
                break;
                
            case 'delete_by_action':
                if (isset($_POST['log_action']) && !empty($_POST['log_action'])) {
                    $action = trim($_POST['log_action']);
                    
                    $stmt = $conn->prepare("DELETE FROM user_logs WHERE action = ?");
                    $stmt->bind_param("s", $action);
                    $stmt->execute();
                    $stmt->close();

                    // Log the action
                    logUserActivity($_SESSION['user_id'], 'delete_action_logs', "Deleted all logs with action: " . htmlspecialchars($action));

                    echo '<div class="alert alert-success">Logs for selected action deleted successfully!</div>';
                }
                break;
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo '<div class="alert alert-danger">An error occurred while processing your request.</div>';
    }
}

// Get unique users and actions for filters using prepared statements
try {
    $users_stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.username 
        FROM user_logs l 
        JOIN users u ON l.user_id = u.id 
        ORDER BY u.username
    ");
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();

    $actions_stmt = $conn->prepare("
        SELECT DISTINCT action 
        FROM user_logs 
        ORDER BY action
    ");
    $actions_stmt->execute();
    $actions_result = $actions_stmt->get_result();
    $actions = $actions_result->fetch_all(MYSQLI_ASSOC);
    $actions_stmt->close();

    // Get all logs with pagination and optional filtering
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    $filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $filter_action = isset($_GET['log_action']) ? trim($_GET['log_action']) : null;

    $count_query = "SELECT COUNT(*) as total FROM user_logs";
    $where_clause = "";
    $query_params = [];
    $param_types = "";
    
    if ($filter_user) {
        $where_clause = " WHERE user_id = ?";
        $query_params[] = $filter_user;
        $param_types .= "i";
    } elseif ($filter_action) {
        $where_clause = " WHERE action = ?";
        $query_params[] = $filter_action;
        $param_types .= "s";
    }

    $count_stmt = $conn->prepare($count_query . $where_clause);
    if (!empty($query_params)) {
        $count_stmt->bind_param($param_types, ...$query_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_logs = $count_row['total'];
    $count_stmt->close();
    
    $total_pages = ceil($total_logs / $per_page);

    $stmt_query = "
        SELECT l.*, u.username 
        FROM user_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
    ";
    
    if ($filter_user) {
        $stmt_query .= " WHERE l.user_id = ?";
        $param_types = "i";
    } elseif ($filter_action) {
        $stmt_query .= " WHERE l.action = ?";
        $param_types = "s";
    }
    
    $stmt_query .= " ORDER BY l.id DESC LIMIT ? OFFSET ?";
    $param_types .= "ii";

    $stmt = $conn->prepare($stmt_query);
    
    if ($filter_user || $filter_action) {
        $params = array_merge([$param_types], $query_params, [$per_page, $offset]);
        $stmt->bind_param(...$params);
    } else {
        $stmt->bind_param("ii", $per_page, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while fetching the logs.</div>';
    $logs = [];
    $users = [];
    $actions = [];
    $total_pages = 0;
}
?>

<div class="container mt-4">
    <h1>System Logs</h1>

    
    
    <div class="row mb-4">
        <div class="col-12 mb-3">
            <button class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete ALL logs? This action cannot be undone.')) { deleteAllLogs(); }">
                Delete All Logs
            </button>
            
            <form class="d-inline-block ms-2" onsubmit="return confirm('Delete all logs for this user? This action cannot be undone.');" method="POST">
                <input type="hidden" name="action" value="delete_by_user">
                <select name="user_id" class="form-select d-inline-block w-auto" required>
                    <option value="">Select User...</option>
                    <?php foreach($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-warning">Delete User Logs</button>
            </form>
            
            <form class="d-inline-block ms-2" onsubmit="return confirm('Delete all logs for this action? This action cannot be undone.');" method="POST">
                <input type="hidden" name="action" value="delete_by_action">
                <select name="log_action" class="form-select d-inline-block w-auto" required>
                    <option value="">Select Action...</option>
                    <?php foreach($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action['action']); ?>"><?php echo htmlspecialchars($action['action']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-warning">Delete Action Logs</button>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <select id="userFilter" class="form-select" onchange="applyFilters()">
                <option value="">Filter by User...</option>
                <?php foreach($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                            <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select id="actionFilter" class="form-select" onchange="applyFilters()">
                <option value="">Filter by Action...</option>
                <?php foreach($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action['action']); ?>"
                            <?php echo ($filter_action == $action['action']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($action['action']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
        </div>
    </div>
    
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="5" class="text-center">No logs found</td>
            </tr>
            <?php else: ?>
                <?php foreach($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                
                <li class="page-item <?= $page > 2 ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $page > 2 ? '?page=' . ($page - 1) . ($filter_user ? '&user_id='.$filter_user : '') . ($filter_action ? '&log_action='.$filter_action : '') : '#' ?>">Previous</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_action ? '&log_action='.$filter_action : '' ?>">1</a>
                </li>
            <?php endif; ?>

            <?php if ($page > 3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_action ? '&log_action='.$filter_action : '' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages - 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?><?= $filter_user ? '&user_id='.$filter_user : '' ?><?= $filter_action ? '&log_action='.$filter_action : '' ?>"><?= $total_pages ?></a>
                </li>
                <li class="page-item <?= $page < $total_pages - 1 ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $page < $total_pages - 1 ? '?page=' . ($page + 1) . ($filter_user ? '&user_id='.$filter_user : '') . ($filter_action ? '&log_action='.$filter_action : '') : '#' ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

<script>
function deleteAllLogs() {
    const form = document.createElement('form');
    form.method = 'POST';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'delete_all';
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function applyFilters() {
    const userFilter = document.getElementById('userFilter').value;
    const actionFilter = document.getElementById('actionFilter').value;
    let url = new URL(window.location.href);
    
    if (userFilter) {
        url.searchParams.set('user_id', userFilter);
        url.searchParams.delete('log_action');
    } else if (actionFilter) {
        url.searchParams.set('log_action', actionFilter);
        url.searchParams.delete('user_id');
    }
    
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function clearFilters() {
    let url = new URL(window.location.href);
    url.searchParams.delete('user_id');
    url.searchParams.delete('log_action');
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

<?php includeFooter(); ?>