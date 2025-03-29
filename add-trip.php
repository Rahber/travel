<?php
require_once 'common.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

try {
    $conn = getDbConnectionMysqli();
    
    // Get and validate the trip data
    $title = sanitizeString($_POST['title']);
    $description = sanitizeString($_POST['description']);
    $trip_type = sanitizeString($_POST['tripType']); // Get and sanitize trip type
    $user_id = $_SESSION['user_id'];

    // Validate input
    if (empty($title) || empty($description) || empty($trip_type)) { // Include trip type in validation
        throw new Exception('Title, description, and trip type are required.');
    }

    // Prepare and execute the SQL statement
    $stmt = $conn->prepare("
        INSERT INTO trips (user_id, title, description, trip_type, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->bind_param("isss", $user_id, $title, $description, $trip_type); // Bind trip type parameter
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $trip_id = $stmt->insert_id;

    // Log the trip creation
    logUserActivity($user_id, 'trip_create', "Created new trip: $title");

    echo json_encode(['success' => true, 'trip_id' => $trip_id]);

} catch (Exception $e) {
    error_log("Error adding trip: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add trip.']);
}
