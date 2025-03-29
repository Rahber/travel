<?php
require_once 'common.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

try {
    $conn = getDbConnectionMysqli();
    
    // Get and sanitize basic item data
    $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT) ?: 0;
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: 0;
    $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
    $item_status = $conn->real_escape_string(trim($_POST['status'] ?? 'planned'));
    $user_id = $_SESSION['user_id'];

    // Validate required fields
    if (!$trip_id || !$category_id || empty($title)) {
        throw new Exception('Required fields are missing.');
    }

    // Verify trip belongs to user
    $stmt = $conn->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $trip_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        throw new Exception('Invalid trip access.');
    }
    $stmt->close();

    // Sanitize category-specific fields
    $fields = [
        'booking_reference' => $conn->real_escape_string(trim($_POST['booking_reference'] ?? '')),
        'departure_station' => $conn->real_escape_string(trim($_POST['departure_station'] ?? '')),
        'arrival_station' => $conn->real_escape_string(trim($_POST['arrival_station'] ?? '')),
        'departure_gps' => $conn->real_escape_string(trim($_POST['departure_gps'] ?? '')),
        'arrival_gps' => $conn->real_escape_string(trim($_POST['arrival_gps'] ?? '')),
        'flight_number' => $conn->real_escape_string(trim($_POST['flight_number'] ?? '')),
        'airline' => $conn->real_escape_string(trim($_POST['airline'] ?? '')),
        'scheduled_departure_time' => isset($_POST['scheduled_departure_time']) ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_departure_time'])) : null,
        'scheduled_arrival_time' => isset($_POST['scheduled_arrival_time']) ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_arrival_time'])) : null,
        'actual_departure_time' => null,
        'actual_arrival_time' => null,
        'hotel_name' => $conn->real_escape_string(trim($_POST['hotel_name'] ?? '')),
        'room_type' => $conn->real_escape_string(trim($_POST['room_type'] ?? '')),
        'transport_number' => $conn->real_escape_string(trim($_POST['transport_number'] ?? '')),
        'transport_company' => $conn->real_escape_string(trim($_POST['transport_company'] ?? '')),
        'transport_duration' => $conn->real_escape_string(trim($_POST['transport_duration'] ?? '')),
        'route' => $conn->real_escape_string(trim($_POST['route'] ?? '')),
        'transport_details' => $conn->real_escape_string(trim($_POST['transport_details'] ?? ''))
    ];

    // Validate required fields based on category
    switch($category_id) {
        case 1: // Flight
            if(empty($fields['flight_number']) || empty($fields['airline'])) {
                throw new Exception('Flight number and airline are required for flight bookings.');
            }
            break;
        case 2: // Bus
            if(empty($fields['transport_number']) || empty($fields['transport_company'])) {
                throw new Exception('Bus number and company are required for bus bookings.');
            }
            break;
        case 3: // Train
            if(empty($fields['transport_number']) || empty($fields['transport_company'])) {
                throw new Exception('Train number and company are required for train bookings.');
            }
            break;
        case 4: // Hotel
            if(empty($fields['hotel_name']) || empty($fields['scheduled_departure_time']) || empty($fields['scheduled_arrival_time'])) {
                //throw new Exception('Hotel name, check-in and check-out times are required for hotel bookings.');
                throw new Exception('Misisng fields are: ' . $fields['hotel_name'] . ', ' . $fields['scheduled_departure_time'] . ', ' . $fields['scheduled_arrival_time']  );
                
            }
            break;
    }

    // Prepare SQL statement
    $stmt = $conn->prepare("INSERT INTO items (
        trip_id, user_id, category_id, title, item_status,
        booking_reference, departure_station, arrival_station, departure_gps, arrival_gps,
        flight_number, airline, scheduled_departure_time, scheduled_arrival_time,
        actual_departure_time, actual_arrival_time,
        hotel_name, room_type, transport_number, transport_company, transport_duration,
        route, transport_details
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?
    )");

    $stmt->bind_param("iiissssssssssssssssssss",
        $trip_id,
        $user_id,
        $category_id,
        $title,
        $item_status,
        $fields['booking_reference'],
        $fields['departure_station'],
        $fields['arrival_station'],
        $fields['departure_gps'],
        $fields['arrival_gps'],
        $fields['flight_number'],
        $fields['airline'],
        $fields['scheduled_departure_time'],
        $fields['scheduled_arrival_time'],
        $fields['actual_departure_time'],
        $fields['actual_arrival_time'],
        $fields['hotel_name'],
        $fields['room_type'],
        $fields['transport_number'],
        $fields['transport_company'],
        $fields['transport_duration'],
        $fields['route'],
        $fields['transport_details']
    );

    $stmt->execute();
    $stmt->close();

    // Log the item creation using the logUserActivity function
    $description = "Added new item to trip $trip_id: $title";
    logUserActivity($user_id, 'item_create', $description);

    echo json_encode(['success' => true, 'message' => 'Item added successfully.', 'trip_id' => $trip_id]);

} catch (Exception $e) {
    error_log("Error adding item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
