<?php
require_once 'common.php';
requireLogin();

try {
    includeHeader();

    $user_id = $_SESSION['user_id'];
    $conn = getDbConnectionMysqli();

    // Get active trips
    $activeTripsQuery = "
        SELECT t.*
        FROM trips t
        WHERE t.user_id = ? 
        AND t.status = 'active'
        ORDER BY t.start_date ASC
    ";

    $activeTripsStmt = $conn->prepare($activeTripsQuery);
    if (!$activeTripsStmt) {
        throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $activeTripsStmt->bind_param("i", $user_id);
    if (!$activeTripsStmt->execute()) {
        throw new Exception("Execute failed: " . htmlspecialchars($activeTripsStmt->error));
    }
    $activeTripsResult = $activeTripsStmt->get_result();
    $activeTrips = $activeTripsResult->fetch_all(MYSQLI_ASSOC);
    $activeTripsStmt->close();

    // Get past/cancelled/completed trips
    $pastTripsQuery = "
        SELECT t.*
        FROM trips t 
        WHERE t.user_id = ? 
        AND t.status != 'active'
        ORDER BY t.start_date DESC
    ";

    $pastTripsStmt = $conn->prepare($pastTripsQuery);
    if (!$pastTripsStmt) {
        throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $pastTripsStmt->bind_param("i", $user_id);
    if (!$pastTripsStmt->execute()) {
        throw new Exception("Execute failed: " . htmlspecialchars($pastTripsStmt->error));
    }
    $pastTripsResult = $pastTripsStmt->get_result();
    $pastTrips = $pastTripsResult->fetch_all(MYSQLI_ASSOC);
    $pastTripsStmt->close();
?>

<div class="container mt-3">
<div class="trip-panel p-2 rounded-lg shadow-lg bg-white" style="border: 1px solid #e0e0e0; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%;"> 
    <div class="justify-content-between align-items-center mb-4">
        <h2 class="text-center mb-4" style="font-family: 'Arial', sans-serif; color: #2c3e50;">
            <span class="fw-bold d-block mb-2" style="font-size: 1.8rem; background: linear-gradient(120deg, #2c3e50, #3498db); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">My Trips</span>
        </h2>
        <button type="button" class="btn btn-success w-100 mb-4 px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTripModal" style="background: linear-gradient(120deg, #27ae60, #2ecc71);">
            <i class="fas fa-plus-circle me-2"></i> Add New Trip
        </button>
    </div>

    <!-- Active Trips Section -->
    <div class="card mb-4">
        <div class="card-header" id="activeTripsHeader" style="cursor: pointer;">
            <h3 class="d-flex justify-content-between align-items-center mb-0">
                <span class="flex-grow-1 text-start">Active Trips</span>
                <i class="fas fa-chevron-down ms-2"></i>
            </h3>
        </div>
        <div id="activeTripsContent" class="show">
            <div class="card-body">
                <div id="activeTripsContainer">
                    <?php if (empty($activeTrips)): ?>
                        <p>No active trips found.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($activeTrips as $trip): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($trip['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h5>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?php 
                                                        $start_date = !empty($trip['start_date']) ? htmlspecialchars(date('M d, Y', strtotime($trip['start_date'])), ENT_QUOTES, 'UTF-8') : '';
                                                        $end_date = !empty($trip['end_date']) ? htmlspecialchars(date('M d, Y', strtotime($trip['end_date'])), ENT_QUOTES, 'UTF-8') : '';
                                                        echo "$start_date - $end_date";
                                                    ?>
                                                </small>
                                            </p>
                                            <p class="card-text"><?php echo htmlspecialchars($trip['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                            <a href="trip-details.php?id=<?php echo (int)($trip['id'] ?? 0); ?>" class="btn btn-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Past Trips Section -->
    <div class="card">
        <div class="card-header" id="pastTripsHeader" style="cursor: pointer;">
            <h3 class="d-flex justify-content-between align-items-center mb-0">
                <span class="flex-grow-1 text-start">Past Trips</span>
                <i class="fas fa-chevron-down ms-2"></i>
            </h3>
        </div>
        <div id="pastTripsContent" class="collapse">
            <div class="card-body">
                <div id="pastTripsContainer">
                    <?php if (empty($pastTrips)): ?>
                        <p>No past trips found.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pastTrips as $trip): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($trip['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h5>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?php 
                                                        $start_date = !empty($trip['start_date']) ? htmlspecialchars(date('M d, Y', strtotime($trip['start_date'])), ENT_QUOTES, 'UTF-8') : '';
                                                        $end_date = !empty($trip['end_date']) ? htmlspecialchars(date('M d, Y', strtotime($trip['end_date'])), ENT_QUOTES, 'UTF-8') : '';
                                                        echo "$start_date - $end_date";
                                                    ?>
                                                </small>
                                            </p>
                                            <p class="card-text"><?php echo htmlspecialchars($trip['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="card-text">
                                                <small class="text-muted">Status: <?php echo htmlspecialchars(ucfirst($trip['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small><br>
                                            </p>
                                            <a href="trip-details.php?id=<?php echo (int)($trip['id'] ?? 0); ?>" class="btn btn-secondary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
                            </div>

<!-- Modal for adding a new trip -->
<div class="modal fade" id="addTripModal" tabindex="-1" aria-labelledby="addTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white" style="background: linear-gradient(120deg, #27ae60, #2ecc71);">
                <h5 class="modal-title" id="addTripModalLabel">Add New Trip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTripForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-4">
                        <label for="tripTitle" class="form-label">Title</label>
                        <input type="text" class="form-control form-control-lg" id="tripTitle" name="title" 
                               maxlength="100" pattern="[A-Za-z0-9\s\-_.,!?]{1,100}" 
                               title="Title can only contain letters, numbers, spaces and basic punctuation"
                               required>
                    </div>
                    <div class="mb-4">
                        <label for="tripDescription" class="form-label">Anything to add about this trip?</label>
                        <textarea class="form-control" id="tripDescription" name="description" 
                                  maxlength="500" rows="4" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="tripType" class="form-label">Trip Type</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="tripTypeToggle" name="tripType" value="business" onchange="toggleTripType(this)">
                            <label class="form-check-label" for="tripTypeToggle">Business Trip</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="tripTypeToggleLeisure" name="tripType" value="leisure" onchange="toggleTripType(this)">
                            <label class="form-check-label" for="tripTypeToggleLeisure">Leisure Trip</label>
                        </div>
                        <script>
                            function toggleTripType(checkbox) {
                                const otherCheckbox = checkbox.id === 'tripTypeToggle' ? document.getElementById('tripTypeToggleLeisure') : document.getElementById('tripTypeToggle');
                                if (checkbox.checked) {
                                    otherCheckbox.checked = false;
                                }
                            }
                        </script>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-plus-circle me-2"></i>Add Trip
                    </button>
                </form>
                <div id="addTripResponse" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Secure HTML escaping function
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Add click handlers for section headers
    document.addEventListener('DOMContentLoaded', function() {
        const activeTripsHeader = document.getElementById('activeTripsHeader');
        const activeTripsContent = document.getElementById('activeTripsContent');
        const pastTripsHeader = document.getElementById('pastTripsHeader');
        const pastTripsContent = document.getElementById('pastTripsContent');

        function toggleSection(header, content) {
            const icon = header.querySelector('.fas');
            if (content.classList.contains('show')) {
                content.classList.remove('show');
                content.classList.add('collapse');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                content.classList.add('show');
                content.classList.remove('collapse');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        activeTripsHeader.addEventListener('click', () => toggleSection(activeTripsHeader, activeTripsContent));
        pastTripsHeader.addEventListener('click', () => toggleSection(pastTripsHeader, pastTripsContent));

        // Set initial states
        const activeIcon = activeTripsHeader.querySelector('.fas');
        activeIcon.classList.remove('fa-chevron-down');
        activeIcon.classList.add('fa-chevron-up');
    });

    document.getElementById('addTripForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const form = this;
        const responseDiv = document.getElementById('addTripResponse');
        const formData = new FormData(form);
        
        // Client-side validation with strict patterns
        const title = formData.get('title')?.trim() || '';
        const description = formData.get('description')?.trim() || '';
        const tripType = formData.get('tripType')?.trim() || '';
        
        // Validate input patterns
        const titlePattern = /^[A-Za-z0-9\s\-_.,!?]{1,100}$/;
        const descPattern = /^[\w\s.,!?-]{1,500}$/;
        const validTripTypes = ['business', 'leisure'];
        
        if (!title || !description || !tripType || 
            !titlePattern.test(title) || 
            !descPattern.test(description) || 
            !validTripTypes.includes(tripType)) {
            responseDiv.innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Invalid input data</div>';
            return;
        }
        
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        
        fetch('add-trip.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': formData.get('csrf_token')
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                responseDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + 
                                      escapeHtml(data.message || 'Trip added successfully!') + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Failed to add trip');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            responseDiv.innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' +
                escapeHtml(error.message || 'An error occurred. Please try again.') + '</div>';
        })
        .finally(() => {
            submitButton.disabled = false;
        });
    });
</script>

<?php 
    includeFooter(); 
} catch (Exception $e) {
    error_log("Error in trip.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
}
?>
