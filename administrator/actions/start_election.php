<?php

session_start();
require '../../config_db.php';

$conn->query("UPDATE election_settings SET setting_value='ongoing' WHERE setting_key='election_status'");

header("Location: ../pages/admin_dashboard.php");
exit();
