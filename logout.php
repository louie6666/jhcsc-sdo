<?php
session_start();

// Destroy session
session_destroy();

// Clear session variables
$_SESSION = [];

// Redirect to index
header('Location: index.php');
exit;
?>
