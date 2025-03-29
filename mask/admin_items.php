<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';
verifyAdminAccess();

$conn = getDbConnectionMysqli();

// Handle item actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['item_id'])) {
                    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                    if ($item_id === false || $item_id <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
                        exit;
                    }
                    
                    try {
                        // Get item details before deletion for logging
                        $stmt = $conn->prepare("SELECT title FROM items WHERE id = ? ");
                        $stmt->bind_param("i", $item_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $item = $result->fetch_assoc();
                        
                        if (!$item) {
                            throw new Exception('Item not found or already deleted');
                        }
                        
                        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
                        $stmt->bind_param("i", $item_id);
                        $stmt->execute();
                        
                        // Log the deletion
                        logUserActivity($_SESSION['user_id'], 'delete_item', "Deleted item #{$item_id}: " . htmlspecialchars($item['title']));
                        
                        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                        exit;
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Error deleting item']);
                        exit;
                    }
                }
                break;
                
            case 'edit':
                // [Previous edit case code remains unchanged]
                break;
        }
    }
}

// Get categories for filter
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Get all users for filter
$stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get all trips for filter
$stmt = $conn->prepare("SELECT id, title FROM trips ORDER BY title");
$stmt->execute();
$result = $stmt->get_result();
$trips = $result->fetch_all(MYSQLI_ASSOC);

// Pagination settings
$items_per_page = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $items_per_page;

// Build query with filters
$where_clauses = [];
$params = [];
$param_types = "";

// Category filter
$categoryFilter = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
if ($categoryFilter) {
    $where_clauses[] = "i.category_id = ?";
    $params[] = $categoryFilter;
    $param_types .= "i";
}

// User filter
$userFilter = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
if ($userFilter) {
    $where_clauses[] = "i.user_id = ?";
    $params[] = $userFilter;
    $param_types .= "i";
}

// Trip filter
$tripFilter = filter_input(INPUT_GET, 'trip', FILTER_VALIDATE_INT);
if ($tripFilter) {
    $where_clauses[] = "i.trip_id = ?";
    $params[] = $tripFilter;
    $param_types .= "i";
}

// Build the base query
$sql = "SELECT i.*, u.username, c.name as category_name, t.title as trip_title 
        FROM items i 
        JOIN users u ON i.user_id = u.id 
        JOIN categories c ON i.category_id = c.id
        LEFT JOIN trips t ON i.trip_id = t.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as count FROM (" . $sql . ") as count_table";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_items / $items_per_page);

// Add sorting and pagination to main query
$sql .= " ORDER BY i.id DESC LIMIT ? OFFSET ?";
$param_types .= "ii";
$params[] = $items_per_page;
$params[] = $offset;

// Execute main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

includeHeader();
?>

<div class="container mt-4">
    <h1>Item Management</h1>
    
    <div class="row mb-3">
        <div class="col-md-3">
            <label>Filter by Category:</label>
            <select class="form-control" onchange="updateFilters();" id="categoryFilter">
                <option value="">All Categories</option>
                <?php foreach($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($categoryFilter == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-3">
            <label>Filter by User:</label>
            <select class="form-control" onchange="updateFilters();" id="userFilter">
                <option value="">All Users</option>
                <?php foreach($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo ($userFilter == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-3">
            <label>Filter by Trip:</label>
            <select class="form-control" onchange="updateFilters();" id="tripFilter">
                <option value="">All Trips</option>
                <?php foreach($trips as $trip): ?>
                    <option value="<?php echo htmlspecialchars($trip['id']); ?>" <?php echo ($tripFilter == $trip['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($trip['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>User</th>
                <th>Trip</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['id']); ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?></td>
                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                <td><?php echo htmlspecialchars($item['item_status']); ?></td>
                <td><?php echo htmlspecialchars($item['username']); ?></td>
                <td><?php echo htmlspecialchars($item['trip_title'] ?? 'N/A'); ?></td>
                <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editItemModal" 
                            onclick="populateEditModal(<?php echo htmlspecialchars(json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Edit</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteItemModal"
                            onclick="populateDeleteModal(<?php echo htmlspecialchars($item['id']); ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): $start = max(1, $page - 2);?>
        
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" onclick="changePage(1)">First</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $page - 1; ?>)">Previous</a>
                </li>
            <?php endif; ?>

            <?php if ($start > 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php 
            
            $end = min($total_pages, $page + 2);
            for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i < $start || $i > $end): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i == $start - 1) || ($i == $end + 1)): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></a>
                    </li>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $page + 1; ?>)">Next</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" onclick="changePage(<?php echo $total_pages; ?>)">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- [Previous modal definitions remain unchanged] -->

<script>
function updateFilters() {
    const category = document.getElementById('categoryFilter').value;
    const user = document.getElementById('userFilter').value;
    const trip = document.getElementById('tripFilter').value;
    
    let url = new URL(window.location.href);
    url.searchParams.set('page', '1');
    
    if (category) url.searchParams.set('category', category);
    else url.searchParams.delete('category');
    
    if (user) url.searchParams.set('user', user);
    else url.searchParams.delete('user');
    
    if (trip) url.searchParams.set('trip', trip);
    else url.searchParams.delete('trip');
    
    window.location.href = url.toString();
}

function changePage(page) {
    let url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

// [Previous JavaScript functions remain unchanged]
</script>

<?php includeFooter(); ?>