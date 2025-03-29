<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('Access denied.');
}

// Database credentials
define('DB_HOST', 'your_value_here');
define('DB_USERNAME', 'your_value_here');
define('DB_PASSWORD', 'your_value_here');
define('DB_NAME', 'your_value_here');
define('DB_PORT', your_value_here);

// Email credentials
define('EMAIL_HOST', 'your_value_here');
define('EMAIL_USERNAME', 'your_value_here');
define('EMAIL_PASSWORD', 'your_value_here');

// Payment API credentials
define('PAYMENT_API_KEY', 'your_value_here');
define('PAYMENT_API_SECRET', 'your_value_here');

define('DUMMY_USERNAME', 'your_value_here');
define('DUMMY_PASSWORD', 'your_value_here');
define('DUMMY_EMAIL', 'your_value_here');

?>
