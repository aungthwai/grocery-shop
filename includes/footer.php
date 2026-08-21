<?php
/*
|--------------------------------------------------------------------------
| GrocerEase Shared Footer
|--------------------------------------------------------------------------
| Loads shared application JavaScript once for every page that includes
| footer.php. sidebar.js contains its own duplicate-load guard so older pages
| that still include it manually remain safe during migration.
|--------------------------------------------------------------------------
*/

$footerBasePath = isset($basePath) && $basePath !== ''
    ? rtrim($basePath, '/')
    : '/grocery-shop';
?>

<script
    src="<?php echo htmlspecialchars(
        $footerBasePath,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/assets/js/sidebar.js"
></script>
