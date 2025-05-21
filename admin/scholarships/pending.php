<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Redirect to index.php with PENDING status filter and preserve any search/pagination parameters
header("Location: index.php?status=PENDING" . 
       (isset($_GET['search']) ? "&search=".$_GET['search'] : "") .
       (isset($_GET['page']) ? "&page=".$_GET['page'] : ""));
exit();