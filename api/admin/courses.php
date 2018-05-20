<?php
// File: create_course.php
// SPDX-License-Identifier: GPL-3.0-or-later

if (!isset($_SESSION['username']))
{
  http_response_code(401);
  echo json_encode( not_authenticated() );
  die();
}

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])
{
  http_response_code(403);
  echo json_encode( not_authorized() );
  die();
}

switch( $_SERVER['REQUEST_METHOD'] ){
  case "POST":
    if (!isset($_POST['course_name']) || !isset($_POST['depart_prefix']) || !isset($_POST['course_num']) || 
        !isset($_POST['description']) || !isset($_POST['ldap_group'])    || !isset($_POST['professor']))
    {
      http_response_code(422);
      echo json_encode( missing_info() );
      die();
    }

    $course_name   = $_POST['course_name'];
    $depart_prefix = $_POST['depart_prefix'];
    $course_num    = $_POST['course_num'];
    $description   = $_POST['description'];
    $ldap_group    = $_POST['ldap_group'];
    $professor     = $_POST['professor'];
    if ($_POST['acc_code'])
    {
      $acc_code    = $_POST['acc_code'];
    }else{
      $acc_code    = null;
    }
    $res  = new_course($course_name, $depart_prefix, $course_num, $description, $ldap_group, $professor, $acc_code);
    $text = "Course created/updated"; 
    break;
  case "DELETE":
    if (!isset($_GET['course']))
    {
      http_response_code(422);
      echo json_encode( missing_info() );
      die();
    }
    $course_name = $_GET['course'];
    $res  = del_course($course_name);
    $text = "Course deleted";
    break;
  default:
    http_response_code(405);
    echo json_encode( invalid_method("POST or DELETE") );
    die();
}

if ($res)
{
  $return = array(
    "authenticated" => True,
    "error" => "Unable to create/delete course"
  );
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