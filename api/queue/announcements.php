<?php
// File: add_announcement.php
// SPDX-License-Identifier: GPL-3.0-or-later

switch( $_SERVER['REQUEST_METHOD'] ){
  case "POST":
    if (!isset($_POST['announcement']))
    {
      http_response_code(422);
      echo json_encode( missing_announcement() );
      die();
    }
    if (!in_array($course, $ta_courses))
    {
      http_response_code(403);
      echo json_encode( not_authorized() );
      die();
    }
    $announcement = $_POST['announcement'];
    $announcement = filter_var($announcement, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $res  = add_announcement($course, $announcement, $username);
    $text = "Announcement set";
    break;
  case "DELETE":  
    if (!isset($_GET['announcement_id']))
    {
      http_response_code(422);
      echo json_encode( missing_announcement() );
      die();
    }
    if (!in_array($course, $ta_courses))
    {
      http_response_code(403);
      echo json_encode( not_authorized() );
      die();
    }
    $announcement_id = $_GET['announcement_id'];
    $res  = del_announcement($course, $announcement_id);
    $text = "Announcement deleted";
    break; 
  default:
    http_response_code(405);
    echo json_encode( invalid_method("DELETE or POST") );
    die();
}

if($res < 0)
{
  $return = return_JSON_error($res);
  http_response_code(500);
}else
{
  $return = array(
    "authenticated" => True,
    "success" => $text
  );
  http_response_code(200);
}

echo json_encode($return);
?>
