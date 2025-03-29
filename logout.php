<?php
require_once 'common.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
redirectToSite("login.php");
exit();
