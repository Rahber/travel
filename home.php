<?php
require_once 'common.php';
requireLogin();

// Verify CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

includeHeader();

$user_id = $_SESSION['user_id'];

// Get database connection with mysqli
try {
    $conn = getDbConnectionMysqli();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Fetch active trips using prepared statement with parameter binding
    $stmt = $conn->prepare("SELECT id, title, description, status, start_date, trip_type FROM trips WHERE user_id = ? ORDER BY start_date DESC");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $trips = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Error in home.php: " . $e->getMessage());
    die('<div class="alert alert-danger">An error occurred while fetching trips. Please try again later.</div>');
}
?>

<div class="container mt-3">
    <div class="trip-panel p-2 rounded-lg shadow-lg bg-white" style="border: 1px solid #e0e0e0; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%;">
        <h2 class="text-center mb-4" style="font-family: 'Arial', sans-serif; color: #2c3e50;">
            <span class="fw-bold d-block mb-2" style="font-size: 1.8rem; background: linear-gradient(120deg, #2c3e50, #3498db); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Your Upcoming Trips</span>
        </h2>

        <button class="btn btn-success w-100 mb-4 px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTripModal" style="background: linear-gradient(120deg, #27ae60, #2ecc71);">
            <i class="fas fa-plus-circle me-2"></i>Add New Trip
        </button>

        <div class="position-relative bg-light p-0 rounded-lg shadow" style="border: 1px solid #e0e0e0;">
            <!-- Trip Container -->
            <div class="trip-container mx-auto" style="width: 100%; overflow: hidden;">
                <div class="trip-slider d-flex gap-4 p-2" style="transition: transform 0.5s ease;">
                    <?php if (!empty($trips)): ?>
                        <?php foreach ($trips as $trip): ?>
                            <?php 
                                // Strict sanitization of all output data
                                $tripId = filter_var($trip['id'], FILTER_SANITIZE_NUMBER_INT);
                                $tripTitle = htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8');
                                $tripDesc = htmlspecialchars($trip['description'], ENT_QUOTES, 'UTF-8');
                                $tripStatus = htmlspecialchars($trip['status'], ENT_QUOTES, 'UTF-8');
                                $tripType = htmlspecialchars($trip['trip_type'], ENT_QUOTES, 'UTF-8');
                                
                                // Validate status before assigning class
                                $statusClass = 'warning';
                                $validStatuses = ['active' => 'success', 'completed' => 'info'];
                                if (isset($validStatuses[$trip['status']])) {
                                    $statusClass = $validStatuses[$trip['status']];
                                }

                                // Set trip type class
                                $typeClass = $tripType === 'business' ? 'primary' : 'secondary';
                            ?>
                            <div class="trip-card card border-0 shadow-lg transform-hover" style="min-width: 300px; max-width: 300px; min-height: 400px; transition: all 0.3s ease; border-radius: 15px;">
                                <div class="card-body p-2 position-relative">
                                    <div class="position-absolute top-0 start-0 w-100 h-100" style="z-index: 0; overflow: hidden;">
                                        <div style="width: 100%; height: 100%; background: url('https://picsum.photos/300/200/?blur') center/cover no-repeat; opacity: 0.2; border-radius: 15px;"></div>
                                    </div>
                                    <div class="position-absolute top-0 end-0 p-3 d-flex gap-2" style="z-index: 2;">
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($tripStatus); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $typeClass; ?>">
                                            <?php echo ucfirst($tripType); ?>
                                        </span>
                                    </div>
                                    <div class="p-4 position-relative" style="z-index: 2;">
                                        <h5 style="margin-top: 10px;" class="card-title h4 mb-3"><?php echo $tripTitle; ?></h5>
                                        <p class="card-text text-muted mb-4"><?php echo $tripDesc; ?></p>
                                        <div class="trip-details mt-auto">
                                            <a href="trip-details.php?id=<?php echo $tripId; ?>" 
                                               class="btn btn-outline-primary w-100"
                                               data-trip-id="<?php echo $tripId; ?>"
                                               onclick="return validateTripId(this)">
                                                <i class="fas fa-suitcase-rolling me-2"></i>Trip Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info w-100">
                            <i class="fas fa-info-circle me-2"></i>No trips found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <button class="btn btn-light rounded-circle shadow-lg position-absolute top-50 start-0 translate-middle-y" 
                    onclick="scrollTrips('prev')" 
                    id="prevBtn"
                    style="z-index: 1000; margin-left: 0px; width: 40px; height: 40px;">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="btn btn-light rounded-circle shadow-lg position-absolute top-50 end-0 translate-middle-y" 
                    onclick="scrollTrips('next')" 
                    id="nextBtn"
                    style="z-index: 1000; margin-right: 0px; width: 40px; height: 40px;">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Security measure: Validate trip IDs before navigation
function validateTripId(element) {
    const tripId = element.getAttribute('data-trip-id');
    return /^\d+$/.test(tripId);
}

let currentPosition = 0;
const slider = document.querySelector('.trip-slider');
const cards = document.querySelectorAll('.trip-card');
const cardWidth = 316; // 300px card width + 16px gap
const visibleCards = Math.floor(document.querySelector('.trip-container').offsetWidth / cardWidth);

function scrollTrips(direction) {
    // Input validation
    if (direction !== 'next' && direction !== 'prev') return;
    
    const maxPosition = Math.max(0, cards.length - visibleCards);
    
    if (direction === 'next' && currentPosition < maxPosition) {
        currentPosition++;
    } else if (direction === 'prev' && currentPosition > 0) {
        currentPosition--;
    }
    
    const translateX = -currentPosition * cardWidth;
    slider.style.transform = `translateX(${translateX}px)`;
    
    // Update button visibility
    document.getElementById('prevBtn').style.visibility = currentPosition === 0 ? 'hidden' : 'visible';
    document.getElementById('nextBtn').style.visibility = currentPosition >= maxPosition ? 'hidden' : 'visible';
}

// Initialize button states
window.addEventListener('load', () => {
    document.getElementById('prevBtn').style.visibility = 'hidden';
    document.getElementById('nextBtn').style.visibility = cards.length > visibleCards ? 'visible' : 'hidden';
});

// Debounce resize handler for performance
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        const newVisibleCards = Math.floor(document.querySelector('.trip-container').offsetWidth / cardWidth);
        if (newVisibleCards !== visibleCards) {
            currentPosition = 0;
            slider.style.transform = 'translateX(0)';
            scrollTrips('next');
            scrollTrips('prev');
        }
    }, 250);
});
</script>

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
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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
// Secure connection closure
if (isset($conn) && $conn) {
    $conn->close();
}
includeFooter(); 
?>
