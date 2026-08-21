<?php
/**
 * GrocerEase - Shared Layout Header
 */
$basePath = $basePath ?? '/grocery-shop';
$page_title = $page_title ?? 'GrocerEase';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - GrocerEase</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/topbar.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/module.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
