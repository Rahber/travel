<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php'; // Include common.php for session and security functions
verifyAdminAccess();
includeHeader();

// Handle admin options
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_countries'])) {
            // Logic to update countries
            // This could involve fetching new country data from an API or database
            $_SESSION['success_message'] = "Countries updated successfully";
        } elseif (isset($_POST['update_flights'])) {
            // Logic to update flights
            $_SESSION['success_message'] = "Flights updated successfully";
        } elseif (isset($_POST['update_airports'])) {
            // Logic to update airports
            $_SESSION['success_message'] = "Airports updated successfully";
        } elseif (isset($_POST['update_cities'])) {
            // Logic to update cities
            $_SESSION['success_message'] = "Cities updated successfully";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating data: " . $e->getMessage();
        error_log($e->getMessage());
    }
}
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <h1>Admin Options</h1>
    
    <form method="POST" class="mt-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Update Options</h5>
                
                <button type="submit" name="update_countries" class="btn btn-warning">Update Countries</button>
                <button type="submit" name="update_flights" class="btn btn-info">Update Flights</button>
                <button type="submit" name="update_airports" class="btn btn-primary">Update Airports</button>
                <button type="submit" name="update_cities" class="btn btn-success">Update Cities</button>
            </div>
        </div>
    </form>
</div>

<?php includeFooter(); ?>