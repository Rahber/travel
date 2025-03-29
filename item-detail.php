<?php
require_once 'common.php';
requireLogin();

// Get item ID from URL parameter
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate item exists and belongs to logged in user
$query = "SELECT i.*, t.user_id FROM items i 
          JOIN trips t ON i.trip_id = t.id 
          WHERE i.id = ? AND t.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: trip.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        // Delete item
        $query = "DELETE FROM items WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->close();
        
        header('Location: trip-detail.php?id=' . $item['trip_id']);
        exit();
    } else {
        // Update item
        $query = "UPDATE items SET 
            title = ?,
            booking_reference = ?,
            departure_station = ?,
            arrival_station = ?,
            transport_duration = ?,
            transport_number = ?,
            transport_company = ?,
            transport_details = ?,
            scheduled_departure_time = ?,
            scheduled_arrival_time = ?
            WHERE id = ?";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssssi", 
            $_POST['title'],
            $_POST['booking_reference'],
            $_POST['departure_station'], 
            $_POST['arrival_station'],
            $_POST['transport_duration'],
            $_POST['transport_number'],
            $_POST['transport_company'],
            $_POST['transport_details'],
            $_POST['scheduled_departure_time'],
            $_POST['scheduled_arrival_time'],
            $item_id
        );
        $stmt->execute();
        $stmt->close();

        header('Location: trip-detail.php?id=' . $item['trip_id']);
        exit();
    }
}

// Include header
includeHeader();
?>

<div class="container main-content">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2>Edit Item Details</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Booking Reference</label>
                            <input type="text" class="form-control" name="booking_reference" value="<?php echo htmlspecialchars($item['booking_reference']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Departure Station</label>
                            <input type="text" class="form-control" name="departure_station" value="<?php echo htmlspecialchars($item['departure_station']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Arrival Station</label>
                            <input type="text" class="form-control" name="arrival_station" value="<?php echo htmlspecialchars($item['arrival_station']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transport Duration</label>
                            <input type="text" class="form-control" name="transport_duration" value="<?php echo htmlspecialchars($item['transport_duration']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transport Number</label>
                            <input type="text" class="form-control" name="transport_number" value="<?php echo htmlspecialchars($item['transport_number']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transport Company</label>
                            <input type="text" class="form-control" name="transport_company" value="<?php echo htmlspecialchars($item['transport_company']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transport Details</label>
                            <textarea class="form-control" name="transport_details"><?php echo htmlspecialchars($item['transport_details']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Scheduled Departure Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_departure_time" 
                                value="<?php echo date('Y-m-d\TH:i', strtotime($item['scheduled_departure_time'])); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Scheduled Arrival Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_arrival_time"
                                value="<?php echo date('Y-m-d\TH:i', strtotime($item['scheduled_arrival_time'])); ?>">
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Update Item</button>
                            <button type="submit" name="delete" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to delete this item?')">Delete Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
includeFooter();
?>
