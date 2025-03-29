<?php
// Include common.php for shared functionality
include 'common.php';

// Get the 'file' parameter from the URL
$file = isset($_GET['file']) ? $_GET['file'] : 'about'; // Default to 'about' if not set

// Include the header
includeHeader(); // Assuming includeHeader() is the function in common.php that includes the header

// Define content based on the 'file' parameter
switch ($file) {
    case 'about':
        echo '<h1>About Us</h1>';
        echo '<p>We are a travel itinerary management service that helps you organize your travel plans efficiently.</p>';
        break;
    case 'company':
        echo '<h1>Company</h1>';
        echo '<p>Our company is dedicated to providing the best travel planning experience.</p>';
        break;
    case 'terms':
        echo '<h1>Terms & Conditions</h1>';
        echo '<p>These are the terms and conditions for using our service.</p>';
        break;
    case 'privacy':
        echo '<h1>Privacy Policy</h1>';
        echo '<p>Your privacy is important to us. Here is how we handle your data.</p>';
        break;
    default:
        echo '<h1>Page Not Found</h1>';
        echo '<p>The requested page does not exist.</p>';
        break;
}

// Include the footer function
includeFooter();     
?>
