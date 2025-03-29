<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';
verifyAdminAccess();
includeHeader();

$conn = getDbConnectionMysqli();
$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/data/airlines.json';
$userId = $_SESSION['user_id'];

// Handle JSON import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_json'])) {
    try {
        if (!file_exists($jsonFilePath)) {
            throw new Exception('JSON file not found');
        }
        
        $jsonData = json_decode(file_get_contents($jsonFilePath), true);
        if (!$jsonData) {
            throw new Exception('Invalid JSON data');
        }

        $conn->begin_transaction();
        
        // Get existing airlines
        $existingAirlines = [];
        $stmt = $conn->prepare("SELECT aid, name, alias, iata, icao, callsign, country, active FROM airlines");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existingAirlines[$row['aid']] = $row;
        }
        $stmt->close();

        // Prepare statements
        $insertStmt = $conn->prepare("INSERT INTO airlines (aid, name, alias, iata, icao, callsign, country, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStmt = $conn->prepare("UPDATE airlines SET name=?, alias=?, iata=?, icao=?, callsign=?, country=?, active=? WHERE aid=?");

        $changes = 0;
        foreach ($jsonData as $airline) {
            // Validate required fields
            if (empty($airline['id']) || empty($airline['name'])) {
                continue;
            }

            $params = [
                $airline['id'],
                $airline['name'],
                $airline['alias'] ?? '',
                $airline['iata'] ?? '',
                $airline['icao'] ?? '', 
                $airline['callsign'] ?? '',
                $airline['country'] ?? '',
                $airline['active'] ?? 'Y'
            ];

            if (!isset($existingAirlines[$airline['id']])) {
                $insertStmt->bind_param("isssssss", ...$params);
                $insertStmt->execute();
                $changes++;
                logUserActivity($userId, 'import_json', 'Inserted airline: ' . $airline['name']);
            } else {
                $existing = $existingAirlines[$airline['id']];
                $needsUpdate = false;
                
                $fields = ['name', 'alias', 'iata', 'icao', 'callsign', 'country', 'active'];
                foreach ($fields as $i => $field) {
                    if ($existing[$field] !== $params[$i + 1]) {
                        $needsUpdate = true;
                        break;
                    }
                }

                if ($needsUpdate) {
                    $updateParams = array_merge(array_slice($params, 1), [$airline['id']]);
                    $updateStmt->bind_param("sssssssi", ...$updateParams);
                    $updateStmt->execute();
                    $changes++;
                    logUserActivity($userId, 'import_json', 'Updated airline: ' . $airline['name']);
                }
            }
        }

        $insertStmt->close();
        $updateStmt->close();
        
        $conn->commit();

        if ($changes > 0) {
            echo '<div class="alert alert-success">Airlines data updated successfully! ' . $changes . ' records modified.</div>';
        } else {
            echo '<div class="alert alert-info">No changes were needed in the airlines data.</div>';
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error importing airlines: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error importing data. Please try again later.</div>';
    }
}

// Handle external JSON update
if (isset($_POST['update_json'])) {
    try {
        $externalJsonUrl = 'https://raw.githubusercontent.com/npow/airline-codes/refs/heads/master/airlines.json';
        
        // Debug log start of update
        error_log("Starting JSON update from external source: " . $externalJsonUrl);
        
        // Try curl first as primary approach
        if (function_exists('curl_init')) {
            error_log("Using cURL to fetch JSON");
            
            $ch = curl_init($externalJsonUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $jsonData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log("cURL error: " . curl_error($ch));
                throw new Exception('Failed to fetch data via cURL');
            }
            
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log("HTTP error code: " . $httpCode);
                throw new Exception("HTTP error code: $httpCode");
            }
            
        } else {
            // Fallback to file_get_contents
            error_log("Falling back to file_get_contents");
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0',
                    'ignore_errors' => false
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);

            $jsonData = file_get_contents($externalJsonUrl, false, $context);
            
            if ($jsonData === false) {
                $error = error_get_last();
                error_log("file_get_contents error: " . print_r($error, true));
                throw new Exception('Failed to fetch external JSON data');
            }
        }

        // Validate JSON before saving
        $decoded = json_decode($jsonData, true);
        if (!$decoded) {
            $jsonError = json_last_error_msg();
            error_log("JSON decode error: " . $jsonError);
            throw new Exception("Invalid JSON data received: $jsonError");
        }

        // Verify minimum required structure
        if (!is_array($decoded) || empty($decoded)) {
            error_log("Invalid JSON structure - empty or not an array");
            throw new Exception('Invalid JSON structure');
        }

        // Create backup of existing file
        if (file_exists($jsonFilePath)) {
            $backupPath = $jsonFilePath . '.bak.' . date('Y-m-d-H-i-s');
            if (!copy($jsonFilePath, $backupPath)) {
                error_log("Failed to create backup file: " . $backupPath);
                throw new Exception('Failed to create backup file');
            }
        }

        if (!file_put_contents($jsonFilePath, $jsonData)) {
            error_log("Failed to write to file: " . $jsonFilePath);
            throw new Exception('Failed to save JSON file');
        }

        error_log("Successfully updated airlines JSON file");
        logUserActivity($userId, 'update_json', 'Updated airlines JSON from external source');
        echo '<div class="alert alert-success">Airlines JSON data updated successfully!</div>';

    } catch (Exception $e) {
        error_log("Error updating JSON: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error updating JSON data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle airline update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_airline'])) {
    try {
        $stmt = $conn->prepare("UPDATE airlines SET name=?, alias=?, iata=?, icao=?, callsign=?, country=?, active=? WHERE aid=?");
        
        // Validate input
        $name = trim($_POST['name']);
        if (empty($name)) {
            throw new Exception('Airline name is required');
        }

        $stmt->bind_param("sssssssi", 
            $name,
            trim($_POST['alias']),
            trim($_POST['iata']),
            trim($_POST['icao']),
            trim($_POST['callsign']),
            trim($_POST['country']),
            $_POST['active'],
            $_POST['aid']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        logUserActivity($userId, 'update_airline', 'Updated airline: ' . $name);
        echo '<div class="alert alert-success">Airline updated successfully!</div>';
        $stmt->close();

    } catch (Exception $e) {
        error_log("Error updating airline: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error updating airline. Please try again.</div>';
    }
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch total count of airlines with search
try {
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM airlines WHERE name LIKE ? OR icao LIKE ?");
        $countStmt->bind_param("ss", $searchTerm, $searchTerm);
    } else {
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM airlines");
    }
    
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Pagination settings
    $itemsPerPage = 10;
    $totalPages = ceil($totalCount / $itemsPerPage);
    $currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;

    // Fetch paginated airlines with search
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt = $conn->prepare("SELECT * FROM airlines WHERE name LIKE ? OR icao LIKE ? ORDER BY aid ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ssii", $searchTerm, $searchTerm, $itemsPerPage, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM airlines ORDER BY aid ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $itemsPerPage, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $airlines = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching airlines: " . $e->getMessage());
    $airlines = [];
    $totalPages = 0;
    echo '<div class="alert alert-danger">Error loading airlines. Please try again later.</div>';
}
?>

<div class="container mt-5">
    <h1>Airlines Management</h1>
    
    <div class="mb-4">
        <form method="POST" class="d-inline me-2">
            <button type="submit" name="import_json" class="btn btn-warning">
                Import from JSON
            </button>
        </form>
        <form method="POST" class="d-inline">
            <button type="submit" name="update_json" class="btn btn-info">
                Update JSON from GitHub
            </button>
        </form>
    </div>

    <!-- Search Form -->
    <div class="mb-4">
        <form method="GET" class="row g-3">
            <div class="col-auto">
                <input type="text" class="form-control" name="search" placeholder="Search by Name or ICAO" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="?" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Alias</th>
                <th>IATA</th>
                <th>ICAO</th>
                <th>Callsign</th>
                <th>Country</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($airlines as $airline): ?>
                <tr>
                    <td><?php echo htmlspecialchars($airline['aid']); ?></td>
                    <td><?php echo htmlspecialchars($airline['name']); ?></td>
                    <td><?php echo htmlspecialchars($airline['alias']); ?></td>
                    <td><?php echo htmlspecialchars($airline['iata']); ?></td>
                    <td><?php echo htmlspecialchars($airline['icao']); ?></td>
                    <td><?php echo htmlspecialchars($airline['callsign']); ?></td>
                    <td><?php echo htmlspecialchars($airline['country']); ?></td>
                    <td>
                        <span class="badge <?php echo $airline['active'] == 'Y' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $airline['active'] == 'Y' ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                data-bs-target="#editModal<?php echo htmlspecialchars($airline['aid']); ?>">
                            Edit
                        </button>
                    </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo htmlspecialchars($airline['aid']); ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Airline</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="aid" value="<?php echo htmlspecialchars($airline['aid']); ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" maxlength="255"
                                               value="<?php echo htmlspecialchars($airline['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Alias</label>
                                        <input type="text" class="form-control" name="alias" maxlength="255"
                                               value="<?php echo htmlspecialchars($airline['alias']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">IATA Code</label>
                                        <input type="text" class="form-control" name="iata" maxlength="2"
                                               pattern="[A-Z0-9]{0,2}"
                                               value="<?php echo htmlspecialchars($airline['iata']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ICAO Code</label>
                                        <input type="text" class="form-control" name="icao" maxlength="3"
                                               pattern="[A-Z0-9]{0,3}"
                                               value="<?php echo htmlspecialchars($airline['icao']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Callsign</label>
                                        <input type="text" class="form-control" name="callsign" maxlength="255"
                                               value="<?php echo htmlspecialchars($airline['callsign']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <input type="text" class="form-control" name="country" maxlength="255"
                                               value="<?php echo htmlspecialchars($airline['country']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="active">
                                            <option value="Y" <?php echo $airline['active'] == 'Y' ? 'selected' : ''; ?>>Active</option>
                                            <option value="N" <?php echo $airline['active'] == 'N' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_airline" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" tabindex="-1">Previous</a>
            </li>

            <!-- First page -->
            <li class="page-item <?php echo $currentPage == 1 ? 'active' : ''; ?>">
                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">1</a>
            </li>

            <?php if ($currentPage > 3): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>

            <!-- Pages around current -->
            <?php
            $start = max(2, $currentPage - 1);
            $end = min($totalPages - 1, $currentPage + 1);
            
            for ($i = $start; $i <= $end; $i++):
                if ($i > 1 && $i < $totalPages):
            ?>
                <li class="page-item <?php echo $currentPage == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php 
                endif;
            endfor;
            ?>

            <?php if ($currentPage < $totalPages - 2): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>

            <!-- Last page -->
            <?php if ($totalPages > 1): ?>
                <li class="page-item <?php echo $currentPage == $totalPages ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $totalPages; ?></a>
                </li>
            <?php endif; ?>

            <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php 
$conn->close();
includeFooter(); 
?>
