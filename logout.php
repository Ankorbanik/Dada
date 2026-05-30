<?php
session_start();
session_destroy();
header("Location: manage.php");
exit;
?>