<?php
require_once 'common.php';
requireLogin();

includeHeader();

$trip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$conn = getDbConnectionMysqli();

// Verify trip belongs to user and fetch trip details
$stmt = $conn->prepare("SELECT id, title, trip_type, status FROM trips WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();
if (!$trip) {
    redirectToSite('trip.php');
    exit();
}
$stmt->close();

// Fetch items for the trip
$stmt = $conn->prepare("
    SELECT 
        i.*,
        c.name AS category_name,
        c.icon,
        c.color
    FROM 
        items AS i
    INNER JOIN 
        categories AS c ON i.category_id = c.id
    WHERE 
        i.trip_id = ?
    ORDER BY
        i.id DESC
");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch categories for the modal
$categories = [];
try {
    $category_stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
    $category_stmt->execute();
    $result = $category_stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    $category_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

$typeClass = $trip['trip_type'] === 'business' ? 'primary' : 'secondary';
$statusClass = 'warning';
$validStatuses = ['active' => 'success', 'completed' => 'info'];
if (isset($validStatuses[$trip['status']])) {
    $statusClass = $validStatuses[$trip['status']];
}
?>

<div class="container mt-3">
    <div class="trip-panel p-2 rounded-lg shadow-lg bg-white" style="border: 1px solid #e0e0e0; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <h2 class="text-center mb-4" style="font-family: 'Arial', sans-serif; color: #2c3e50;">
            <span class="fw-bold d-block mb-2" style="font-size: 1.8rem; background: linear-gradient(120deg, #2c3e50, #3498db); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo htmlspecialchars($trip['title']); ?></span>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge bg-<?php echo $typeClass; ?>" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;"><?php echo htmlspecialchars(ucfirst($trip['trip_type'])); ?></span>
                <span class="badge bg-<?php echo $statusClass; ?>" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;"><?php echo htmlspecialchars(ucfirst($trip['status'])); ?></span>
            </div>
        </h2>
        
        <button class="btn btn-success w-100 mb-4 px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addItemModal" style="background: linear-gradient(120deg, #27ae60, #2ecc71);">
            <i class="fas fa-plus-circle me-2"></i>Add New Item
        </button>
        
        <div class="item-carousel position-relative bg-light p-3 p-md-3 rounded-lg shadow" style="border: 1px solid #e0e0e0; ">
            <div class="item-wrapper overflow-hidden mx-2" style="padding:5px 0px 0px 0px">
                <div class="d-flex gap-3" id="itemsContainer" style="transition: transform 0.3s ease-in-out;">
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <div class="item-panel card flex-shrink-0 transform-hover" style="width: calc(100vw - 4rem); max-width: 300px; background-color: <?php echo htmlspecialchars($item['color'] . '15', ENT_QUOTES, 'UTF-8'); ?>; transition: all 0.3s ease; border-radius: 15px;">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3 justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?> fa-2x me-2" style="color: <?php echo htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                            <h5 class="card-title mb-0 fs-6"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                        </div>
                                        <span title="<?php echo htmlspecialchars(ucfirst($item['item_status']), ENT_QUOTES, 'UTF-8'); ?>" class="badge" style="width: 15px; padding: 0px; height: 15px; border-radius: 50%; background-color: <?php echo htmlspecialchars($item['item_status'] === 'planned' ? 'green' : 'red', ENT_QUOTES, 'UTF-8'); ?>;"> </span>
                                    </div>
                                    <div class="info-section p-2 p-md-3 rounded" style="background: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                        <?php if($item['category_name'] == 'Flight'): ?>
                                            <p class="mb-1"><strong>Flight Number:</strong> <?php echo htmlspecialchars($item['flight_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Airline:</strong> <?php echo htmlspecialchars($item['airline'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($item['departure_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>To:</strong> <?php echo htmlspecialchars($item['arrival_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Departure:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_departure_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Arrival:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_arrival_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Ref:</strong> <?php echo htmlspecialchars($item['booking_reference'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php elseif($item['category_name'] == 'Bus'): ?>
                                            <p class="mb-1"><strong>Bus Number:</strong> <?php echo htmlspecialchars($item['transport_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($item['transport_company'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($item['departure_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>To:</strong> <?php echo htmlspecialchars($item['arrival_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Route:</strong> <?php echo htmlspecialchars($item['route'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($item['transport_duration'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Departure:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_departure_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Arrival:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_arrival_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Ref:</strong> <?php echo htmlspecialchars($item['booking_reference'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php elseif($item['category_name'] == 'Train'): ?>
                                            <p class="mb-1"><strong>Train Number:</strong> <?php echo htmlspecialchars($item['transport_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($item['transport_company'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($item['departure_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>To:</strong> <?php echo htmlspecialchars($item['arrival_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Route:</strong> <?php echo htmlspecialchars($item['route'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($item['transport_duration'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Departure:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_departure_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Arrival:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($item['scheduled_arrival_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Ref:</strong> <?php echo htmlspecialchars($item['booking_reference'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php elseif($item['category_name'] == 'Hotel'): ?>
                                            <p class="mb-1"><strong>Hotel Name:</strong> <?php echo htmlspecialchars($item['hotel_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Room Type:</strong> <?php echo htmlspecialchars($item['room_type'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($item['arrival_station'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Check In:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($item['scheduled_departure_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Check Out:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($item['scheduled_arrival_time'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="mb-1"><strong>Ref:</strong> <?php echo htmlspecialchars($item['booking_reference'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <a href="item-detail.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info w-100 shadow-sm">No items found for this trip.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button class="btn btn-light rounded-circle shadow-lg position-absolute top-50 start-0 translate-middle-y" 
                    onclick="scrollItems('prev')" style="z-index: 1000; margin-left: 0px; width: 40px; height: 40px;">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="btn btn-light rounded-circle shadow-lg position-absolute top-50 end-0 translate-middle-y" 
                    onclick="scrollItems('next')" style="z-index: 1000; margin-right: 0px; width: 40px; height: 40px;">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <style>
        @media (max-width: 768px) {
            .container {
                padding-left: 0px;
                padding-right: 0px;
            }
            .item-panel {
                font-size: 0.9rem;
            }
            .info-section p {
                margin-bottom: 0.3rem;
            }
        }
        .transform-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15)!important;
        }
        .info-section {
            transition: all 0.3s ease;
        }
        .info-section:hover {
            background: rgba(255,255,255,0.9);
        }
    </style>

    <script>
        let currentIndex = 0;
        const container = document.getElementById('itemsContainer');
        const items = container.children;
        const itemWidth = window.innerWidth <= 768 ? (window.innerWidth - 40) : 316; // Responsive width calculation
        const maxIndex = Math.max(0, items.length - 1);

        function scrollItems(direction) {
            if (direction === 'next' && currentIndex < maxIndex) {
                currentIndex++;
            } else if (direction === 'prev' && currentIndex > 0) {
                currentIndex--;
            }
            
            const translateX = -currentIndex * itemWidth;
            container.style.transform = `translateX(${translateX}px)`;
        }

        // Update itemWidth on window resize
        window.addEventListener('resize', () => {
            const newItemWidth = window.innerWidth <= 768 ? (window.innerWidth - 40) : 316;
            const translateX = -currentIndex * newItemWidth;
            container.style.transform = `translateX(${translateX}px)`;
        });
    </script>
</div>

<!-- Modal for adding a new item -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="category-selection mb-4">
                            <h6 class="mb-3">Select Category:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <div class="category-box" data-category-id="<?php echo htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                             data-category-name="<?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                             style="width: 80px; height: 80px; border: 2px solid #ddd; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer;">
                                            <i class="<?php echo htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8'); ?> fa-lg mb-1"></i>
                                            <span class="text-center" style="font-size: 0.8rem;"><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info w-100">No categories found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <form id="addItemForm" style="display: none;">
                            <input type="hidden" id="selectedCategoryId" name="category_id">
                            <input type="hidden" id="tripId" name="trip_id" value="<?php echo htmlspecialchars($trip_id, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="itemTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="itemTitle" name="title" required>
                            </div>
                            <div id="dynamicFields" class="row g-3"></div>
                            <button type="submit" class="btn btn-primary mt-3 w-100">Add Item</button>
                        </form>
                        <div id="addItemResponse" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
   const categoryFields = {
    "Flight": [
        { name: "booking_reference", label: "Booking Reference", type: "text" },
        { name: "departure_station", label: "Departure Airport", type: "text" },
        { name: "arrival_station", label: "Arrival Airport", type: "text" },
        { name: "flight_number", label: "Flight Number", type: "text" },
        { name: "airline", label: "Airline", type: "text" },
        { name: "scheduled_departure_time", label: "Scheduled Departure", type: "datetime-local" },
        { name: "scheduled_arrival_time", label: "Scheduled Arrival", type: "datetime-local" }
    ],
    "Bus": [
        { name: "booking_reference", label: "Booking Reference", type: "text" },
        { name: "departure_station", label: "Departure Station", type: "text" },
        { name: "arrival_station", label: "Arrival Station", type: "text" },
        { name: "transport_number", label: "Bus Number", type: "text" },
        { name: "transport_company", label: "Bus Company", type: "text" },
        { name: "route", label: "Route", type: "text" },
        { name: "scheduled_departure_time", label: "Scheduled Departure", type: "datetime-local" },    
        { name: "scheduled_arrival_time", label: "Scheduled Arrival", type: "datetime-local" }
    ],
    "Train": [
        { name: "booking_reference", label: "Booking Reference", type: "text" },
        { name: "departure_station", label: "Departure Station", type: "text" },
        { name: "arrival_station", label: "Arrival Station", type: "text" },
        { name: "transport_number", label: "Train Number", type: "text" },
        { name: "transport_company", label: "Train Company", type: "text" },
        { name: "route", label: "Route", type: "text" },
        { name: "scheduled_departure_time", label: "Scheduled Departure", type: "datetime-local" },    
        { name: "scheduled_arrival_time", label: "Scheduled Arrival", type: "datetime-local" }
    ],
    "Hotel": [
        { name: "booking_reference", label: "Booking Reference", type: "text" },
        { name: "hotel_name", label: "Hotel Name", type: "text" },
        { name: "arrival_station", label: "Hotel Location", type: "text" },
        { name: "scheduled_departure_time", label: "Check In Time", type: "datetime-local" },    
        { name: "scheduled_arrival_time", label: "Check Out Time", type: "datetime-local" }
    ]
};

    // Add click handlers for category boxes
    document.querySelectorAll('.category-box').forEach(box => {
        box.addEventListener('click', function() {
            // Remove active state from all boxes
            document.querySelectorAll('.category-box').forEach(b => {
                b.style.borderColor = '#ddd';
                b.style.backgroundColor = 'transparent';
            });
            
            // Add active state to selected box
            this.style.borderColor = '#0d6efd';
            this.style.backgroundColor = '#f8f9fa';
            
            const categoryId = this.dataset.categoryId;
            const categoryName = this.dataset.categoryName;
            
            // Show the form and set the category
            document.getElementById('addItemForm').style.display = 'block';
            document.getElementById('selectedCategoryId').value = categoryId;
            
            // Set the title to be a combination of category and today's date
            const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format
            document.getElementById('itemTitle').value = `${categoryName} - ${today}`;
            
            // Load dynamic fields
            const dynamicFields = document.getElementById('dynamicFields');
            dynamicFields.innerHTML = '';
            
            const fields = categoryFields[categoryName];
            if (fields) {
                fields.forEach(field => {
                    const fieldDiv = document.createElement('div');
                    fieldDiv.className = 'col-12 col-md-6';
                    fieldDiv.innerHTML = `
                        <label for="${field.name}" class="form-label">${field.label}</label>
                        <input type="${field.type}" class="form-control" id="${field.name}" name="${field.name}" required>
                    `;
                    dynamicFields.appendChild(fieldDiv);
                });
            }
        });
    });

    // Add form submit handler
    document.getElementById('addItemForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('trip_id', <?php echo $trip_id; ?>);

        try {
            const response = await fetch('add-item.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            const responseDiv = document.getElementById('addItemResponse');
            if (result.success) {
                responseDiv.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                responseDiv.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
            }
        } catch (error) {
            document.getElementById('addItemResponse').innerHTML = 
                '<div class="alert alert-danger">An error occurred while adding the item.</div>';
        }
    });
</script>

<?php includeFooter(); ?>
