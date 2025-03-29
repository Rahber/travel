<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';
verifyAdminAccess();
includeHeader();
$conn = getDbConnectionMysqli();

// Handle trip actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['trip_id'])) {
                    $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
                    if ($trip_id === false) {
                        die('Invalid trip ID');
                    }

                    // Check if trip has associated items
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE trip_id = ?");
                    $stmt->bind_param("i", $trip_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item_count = $result->fetch_assoc()['count'];
                    $stmt->close();

                    if ($item_count > 0) {
                        echo '<div class="alert alert-warning">Warning: This trip has associated items. Are you sure you want to delete it?</div>';
                        logUserActivity($_SESSION['user_id'], 'trip_delete_attempt', 'Attempted to delete Trip ID: ' . $trip_id . ' with associated items');
                    } else {
                        $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?");
                        $stmt->bind_param("i", $trip_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        logUserActivity($_SESSION['user_id'], 'trip_delete', 'Deleted Trip ID: ' . $trip_id);
                        echo '<div class="alert alert-success">Trip deleted successfully!</div>';
                    }
                }
                break;

            case 'edit':
                if (isset($_POST['trip_id'], $_POST['title'], $_POST['description'])) {
                    $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
                    $title = $conn->real_escape_string($_POST['title']);
                    $description = $conn->real_escape_string($_POST['description']);
                    $status = $conn->real_escape_string($_POST['status']);
                    $trip_type = $conn->real_escape_string($_POST['trip_type']);

                    if ($trip_id === false || empty($title) || empty($description)) {
                        die('Invalid input data');
                    }

                    if (!empty($status)) {
                        $stmt = $conn->prepare("UPDATE trips SET title = ?, description = ?, status = ?, trip_type = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("ssssi", $title, $description, $status, $trip_type, $trip_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE trips SET title = ?, description = ?, trip_type = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("sssi", $title, $description, $trip_type, $trip_id);
                    }
                    
                    $stmt->execute();
                    $stmt->close();

                    logUserActivity($_SESSION['user_id'], 'trip_edit', 'Trip ID: ' . $trip_id . ' edited - Title: ' . $title);
                }
                break;

            case 'change_status':
                if (isset($_POST['trip_id'], $_POST['status'])) {
                    $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
                    $status = $conn->real_escape_string($_POST['status']);

                    if ($trip_id === false || empty($status)) {
                        die('Invalid input data');
                    }

                    $stmt = $conn->prepare("UPDATE trips SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $status, $trip_id);
                    $stmt->execute();
                    $stmt->close();

                    logUserActivity($_SESSION['user_id'], 'trip_status_change', 'Trip ID: ' . $trip_id . ' status changed to ' . $status);
                }
                break;
        }
        
        $conn->close();
    }
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get filter values
$user_filter = isset($_GET['user']) ? $conn->real_escape_string($_GET['user']) : '';

// Build query with filters
$where_clause = "";
$params = [];
$param_types = "";

if (!empty($user_filter)) {
    $where_clause = " WHERE u.username LIKE ? ";
    $params[] = "%$user_filter%";
    $param_types .= "s";
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM trips t INNER JOIN users u ON t.user_id = u.id" . $where_clause;
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $items_per_page);

// Get filtered trips with pagination
$query = "SELECT 
    t.id,
    t.title,
    t.description,
    t.status,
    t.trip_type,
    t.created_at,
    u.username,
    u.email,
    t.user_id,
    u.tier as user_tier
FROM trips t 
INNER JOIN users u ON t.user_id = u.id" . 
$where_clause . 
" ORDER BY t.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $params[] = $items_per_page;
    $params[] = $offset;
    $param_types .= "ii";
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param("ii", $items_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$trips = [];
while ($row = $result->fetch_assoc()) {
    $trips[] = $row;
}
$conn->close();
?>

<div class="container mt-4">
    <h1>Trip Management</h1>
    
    <!-- Filter Form -->
    <form method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" name="user" placeholder="Filter by username" 
                           value="<?= htmlspecialchars($user_filter) ?>">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <?php if(!empty($user_filter)): ?>
                        <a href="?" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>User</th>
                <th>View Items</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($trips as $trip): ?>
            <tr>
                <td><a href="admin_items.php?trip=<?= htmlspecialchars($trip['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($trip['id'], ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><a href="admin_items.php?trip=<?= htmlspecialchars($trip['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><a href="admin_users.php?user_id=<?= htmlspecialchars($trip['user_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($trip['username'], ENT_QUOTES, 'UTF-8') ?></a ></td>
                <td><a href="admin_items.php?user=<?= htmlspecialchars($trip['user_id'], ENT_QUOTES, 'UTF-8') ?>">View Items</a></td>
                <td><?= htmlspecialchars(ucfirst($trip['trip_type']), ENT_QUOTES, 'UTF-8') ?></td>
                
                <td><?= htmlspecialchars($trip['status'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editTripModal" 
                            onclick="populateEditModal(<?= htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8') ?>)">Edit</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTripModal"
                            onclick="populateDeleteModal(<?= (int)$trip['id'] ?>)">Delete</button>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#statusTripModal"
                            onclick="populateStatusModal(<?= htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8') ?>)">Change Status</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= !empty($user_filter) ? '&user='.urlencode($user_filter) : '' ?>">First</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page-1 ?><?= !empty($user_filter) ? '&user='.urlencode($user_filter) : '' ?>">Previous</a>
                </li>
            <?php endif; ?>
            
            <?php if($start > 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php 
            // Display two pages before and after the current page
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= !empty($user_filter) ? '&user='.urlencode($user_filter) : '' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if($end < $total_pages): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php if($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($user_filter) ? '&user='.urlencode($user_filter) : '' ?>">Next</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($user_filter) ? '&user='.urlencode($user_filter) : '' ?>">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Edit Trip Modal -->
<div class="modal fade" id="editTripModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editTripForm">
                    <input type="hidden" id="edit_trip_id" name="trip_id">
                    <input type="hidden" name="action" value="edit">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" required maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trip Type</label>
                        <select class="form-select" id="edit_trip_type" name="trip_type">
                            <option value="leisure">Personal</option>
                            <option value="business">Business</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Trip Modal -->
<div class="modal fade" id="deleteTripModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this trip?</p>
                <form id="deleteTripForm">
                    <input type="hidden" id="delete_trip_id" name="trip_id">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="statusTripModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Trip Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusTripForm">
                    <input type="hidden" id="status_trip_id" name="trip_id">
                    <input type="hidden" name="action" value="change_status">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="status">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function populateEditModal(trip) {
    document.getElementById('edit_trip_id').value = trip.id;
    document.getElementById('edit_title').value = trip.title;
    document.getElementById('edit_description').value = trip.description;
    document.getElementById('edit_status').value = trip.status;
    document.getElementById('edit_trip_type').value = trip.trip_type;
}

function populateDeleteModal(tripId) {
    document.getElementById('delete_trip_id').value = tripId;
}

function populateStatusModal(trip) {
    document.getElementById('status_trip_id').value = trip.id;
    document.getElementById('new_status').value = trip.status;
}

// Handle form submissions with CSRF protection
['editTripForm', 'deleteTripForm', 'statusTripForm'].forEach(formId => {
    document.getElementById(formId).addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.text();
            bootstrap.Modal.getInstance(this.closest('.modal')).hide();
            location.reload();
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });
});
</script>

<?php includeFooter(); ?>