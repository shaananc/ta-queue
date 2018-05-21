<?php
// File: go_on_duty.php
// SPDX-License-Identifier: GPL-3.0-or-later

switch( $_SERVER['REQUEST_METHOD'] ){
  case "POST":
    if (!in_array($course, $ta_courses)){
      http_response_code(403);
      echo json_encode( not_authorized() );
      die();
    }
    $res  = enq_ta($username, $course);
    $text = "TA on duty";
    break;
 
  case "DELETE":
    $res  = deq_ta($username, $course);
    $text = "TA off duty";
    break;

  default:
    http_response_code(405);
    echo json_encode( invalid_method("POST or DELETE") );
    die();
}

if($res){
  $return = return_JSON_error($res);
  http_response_code(500);
}else{
  $return = array(
    "authenticated" => True,
    "success" => $text
  );
  http_response_code(200);
}
echo json_encode($return);
?>
