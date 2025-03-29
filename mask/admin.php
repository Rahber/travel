<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php'; // Include common.php for session and security functions
verifyAdminAccess();
includeHeader();

// Get counts for dashboard using MySQLi
$userStmt = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
$userCount = $userStmt->fetch_row()[0];

$tripStmt = $conn->query("SELECT COUNT(*) FROM trips");
$tripCount = $tripStmt->fetch_row()[0];

$itemStmt = $conn->query("SELECT COUNT(*) FROM items");
$itemCount = $itemStmt->fetch_row()[0];

// Get latest users with LIMIT instead of TOP
$stmt = $conn->query("SELECT id, username, created_at FROM users 
                       WHERE status = 'active' 
                       ORDER BY created_at DESC LIMIT 5");
$latestUsers = $stmt->fetch_all(MYSQLI_ASSOC);

// Get latest trips
$stmt = $conn->query("SELECT t.id, t.title, t.created_at, u.username 
                       FROM trips t 
                       JOIN users u ON t.user_id = u.id 
                       ORDER BY t.created_at DESC LIMIT 5");
$latestTrips = $stmt->fetch_all(MYSQLI_ASSOC);

// Get latest items
$stmt = $conn->query("SELECT i.id, i.title, i.created_at, u.username, c.name as category_name 
                       FROM items i 
                       JOIN users u ON i.user_id = u.id 
                       JOIN categories c ON i.category_id = c.id 
                       ORDER BY i.created_at DESC LIMIT 5");
$latestItems = $stmt->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1>Admin Dashboard</h1>
    
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text display-4"><?= htmlspecialchars($userCount, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="admin_users.php" class="btn btn-primary">View All Users</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Trips</h5>
                    <p class="card-text display-4"><?= htmlspecialchars($tripCount, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="admin_trips.php" class="btn btn-primary">View All Trips</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Items</h5>
                    <p class="card-text display-4"><?= htmlspecialchars($itemCount, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="admin_items.php" class="btn btn-primary">View All Items</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <h3>Latest Users</h3>
            <div class="list-group">
                <?php foreach($latestUsers as $user): ?>
                    <div class="list-group-item">
                        <h6><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <small>Joined: <?= htmlspecialchars(date('M j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-4">
            <h3>Latest Trips</h3>
            <div class="list-group">
                <?php foreach($latestTrips as $trip): ?>
                    <div class="list-group-item">
                        <h6><?= htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <small>By: <?= htmlspecialchars($trip['username'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-4">
            <h3>Latest Items</h3>
            <div class="list-group">
                <?php foreach($latestItems as $item): ?>
                    <div class="list-group-item">
                        <h6><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <small>Category: <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?></small><br>
                        <small>By: <?= htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php includeFooter(); ?>
