<?php
// Settings main page - redirect to security by default
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
header("Location: security.php");
exit;
