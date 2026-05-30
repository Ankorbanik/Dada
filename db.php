<?php
$db_host = 'sql305.infinityfree.com';
$db_name = 'if0_41741438_2';
$db_user = 'if0_41741438';
$db_pass = 'EP90lMZj0yVA';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("সংযোগ ব্যর্থ: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>