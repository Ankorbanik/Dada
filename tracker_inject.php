<?php
// tracker_inject.php - সমস্ত HTML পেজে custom-tracker.js যুক্ত করে
// বাইনারি বা API রিকোয়েস্ট এড়িয়ে যাওয়া
$isApi = strpos($_SERVER['SCRIPT_NAME'], 'ajax_') !== false ||
         strpos($_SERVER['SCRIPT_NAME'], 'track.php') !== false ||
         strpos($_SERVER['SCRIPT_NAME'], 'api_') !== false ||
         (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if (!$isApi && !headers_sent()) {
    echo '<script src="/custom-tracker.js" defer></script>';
}