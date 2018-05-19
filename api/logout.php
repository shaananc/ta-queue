<?php
// File: logout.php
// SPDX-License-Identifier: GPL-3.0-or-later

//Clear session variables
$_SESSION = array();

$return = array(
    "authenticated" => False,
    "success" => "User logged out"
);
http_response_code(200);
echo json_encode($return);
session_destroy();
?>
