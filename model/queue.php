<?php
/**
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright (c) 2018 Zane Zakraisek
 *
 * Functions for manipulating the queues
 *
 */

/**
 * Returns the state of a queue
 * TODO: Consider breaking this into four smaller functions
 *
 * @param string $course
 * @return array of queue data on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function get_queue($course_id, $role){
  if(!is_numeric($course_id)){
    return -1; //Eliminates the risk of SQL injection without the overhead of prepared queries
  }

  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1; //SQL error
  }

  #Build return array
  $return = array();
  $return["role"] = $role;

  #Get the state of the queue, if its not here, it must be closed
  $query  = "SELECT IFNULL(state, 'closed') AS state,
                    IFNULL(time_lim, 0) as time_lim,
                    IFNULL(cooldown, 0) as cooldown,
                    IFNULL(quest_public, true) as quest_public,
                    course_name, generic
             FROM queue_state RIGHT JOIN courses ON queue_state.course_id = courses.course_id
             WHERE courses.course_id ='".$course_id."'";
  $result = mysqli_query($sql_conn, $query);
  if(!$result){
    mysqli_close($sql_conn);
    return -1; //SQL Error
  }
  if(!mysqli_num_rows($result)){
    mysqli_close($sql_conn);
    return -2; //Nonexistant Course
  }
  $entry = mysqli_fetch_assoc($result);
  $return["course_name"]  = $entry["course_name"];
  $return["generic"]      = boolval($entry["generic"]);
  $return["state"]        = $entry["state"];
  $return["time_lim"]     = intval($entry["time_lim"]);
  $return["cooldown"]     = intval($entry["cooldown"]);
  $return["quest_public"] = boolval($entry["quest_public"]);


  #Get the announcements
  $return["announcements"] = [];
  $query  = "SELECT announcements.id, announcements.announcement, users.full_name as poster, announcements.tmstmp 
             FROM announcements INNER JOIN users ON announcements.poster=users.username WHERE course_id='".$course_id."' 
             ORDER BY id DESC;";
  $result = mysqli_query($sql_conn, $query);
  if(!$result){
    mysqli_close($sql_conn);
    return -1;
  }
  while($entry = mysqli_fetch_assoc($result)){
    $return["announcements"][] = $entry;
  }


  #Get the state of the TAs
  $return["TAs"] = [];
  $query  = "SELECT ta_status.username, TIMEDIFF(NOW(), ta_status.state_tmstmp) as duration, users.full_name, queue.username as helping 
             FROM ta_status INNER JOIN users on ta_status.username = users.username LEFT JOIN queue on ta_status.helping = queue.position
             WHERE ta_status.course_id='".$course_id."'";
  $result = mysqli_query($sql_conn, $query);
  if(!$result){
    mysqli_close($sql_conn);
    return -1;
  }
  while($entry = mysqli_fetch_assoc($result)){
    $TA = $entry['username'];
    $return["TAs"][$TA] = $entry;
  }

  #Get the actual queue
  $return["queue"] = [];
  $additional_fields = "";
  if($return["quest_public"]){
    $additional_fields = "queue.question,";
  }
  if($role == "ta" || $role == "instructor" || $role == "admin"){
    $additional_fields = "queue.question, users.email,";
  }
  $query  = "SELECT queue.position, queue.username, users.full_name, ".$additional_fields." queue.location
             FROM queue INNER JOIN users on queue.username = users.username
             WHERE course_id ='".$course_id."' ORDER BY position";
  $result = mysqli_query($sql_conn, $query);
  if(!$result){
    mysqli_close($sql_conn);
    return -1;
  }
  while($entry = mysqli_fetch_assoc($result)){
    $student = $entry['username'];
    $return["queue"][$student] = $entry;
  }
  $return["queue_length"] = count($return["queue"]);


  mysqli_close($sql_conn);
  return $return;
}

/**
 * Adds student to queue
 *
 * @param string $username
 * @param int    $course_id
 * @param string $question
 * @param string $location
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 *         int -7 on user on cooldown state
 */
function enq_stu($username, $course_id, $question, $location){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state != "open"){
    mysqli_close($sql_conn);
    return -3;
  }

  //Check cooldown settings for queue
  $cooldown = get_course_cooldown($course_id, $sql_conn);
  if($cooldown < 0){ //error
    mysqli_close($sql_conn);
    return $cooldown;
  }elseif($cooldown){//cooldown period enabled
    $result = check_user_cooldown($username, $cooldown, $course_id, $sql_conn);
    if($result < 0){
      mysqli_close($sql_conn);
      return $result; //error
    }elseif($result){ //user still has time left on cooldown
      mysqli_close($sql_conn);
      return -7;
    }
  }

  $query = "INSERT INTO queue (username, course_id, question, location)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE question=?, location=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "sissss", $username, $course_id, $question, $location, $question, $location);

  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Remove student from queue
 *
 * If a TA is helping this student, SQL will free the TA.
 *
 * @param  string $username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function deq_stu($username, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }
  elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "DELETE FROM queue
            WHERE username=? AND course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "si", $username, $course_id);

  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Put TA on duty.
 * If TA is already on duty, this frees them if they
 * were helping a student, but does NOT dequeue the student.
 *
 * @param  string $username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function enq_ta($username, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }
  elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "INSERT INTO ta_status (username, course_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE helping=NULL";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "si", $username, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Remove TA from queue
 *
 * @param  string $username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function deq_ta($username, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }
  elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "DELETE FROM ta_status
            WHERE username=? AND course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "si", $username, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Gets the status of the TA for the course
 *
 * @param  string $username
 * @param  int    $course_id
 * @return int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 *         int  1 if TA not on duty
 *         int  2 if on duty, but not helping anyone
 *         int  3 if on duty, and helping someone
 */
function get_ta_status($username, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }
  elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query  = "SELECT helping FROM ta_status
             WHERE username=? AND course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "si", $username, $course_id);
  if(!mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_result($stmt, $helping);
  if(mysqli_stmt_fetch($stmt) == NULL){
    mysqli_stmt_close($stmt);
    mysqli_close($sql_conn);
    return -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);

  if($helping == NULL){
    return 2;
  }
  return 3;
}

/**
 * Help particular student in queue
 *
 * @param  string $TA_username
 * @param  string $stud_username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general fail
 *         int -2 on nonexistent course
 *         int -3 on closed course
 *         int -4 on TA not on duty
 */
function help_student($TA_username, $stud_username, $course_id){
 $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  #TA has to be on duty to help student
  #NOTE: This ensures that the TA has a row in the ta_status table 
  if(get_ta_status($TA_username, $course_id) < 2){
    mysqli_close($sql_conn);
    return -4;
  }

  $query = "UPDATE ta_status
            SET helping = (SELECT position FROM queue WHERE username=? AND course_id=?)
            WHERE username=? AND course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "sisi", $stud_username, $course_id, $TA_username, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt) || !mysqli_stmt_affected_rows($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Set the time limit for the queue or 0 for no limit.
 *
 * @param  string $time_lim in minutes
 * @param  int    $course_id
 * @return int 0  on success,
 *         int -1 on general fail
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function set_time_lim($time_lim, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "UPDATE queue_state SET time_lim = ?
            WHERE course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "ii", $time_lim, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Set the cool down time for the queue or 0 for no limit.
 * This is the number of minutes a student must wait before
 * reentering the queue.
 *
 * @param  string $cooldown in minutes
 * @param  int    $course_id
 * @return int 0  on success,
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function set_cooldown($time_lim, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "UPDATE queue_state SET cooldown = ?
            WHERE course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "ii", $time_lim, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Set whether or not questions are visible to students.
 *
 * @param  boolean $quest_public
 * @param  int    $course_id
 * @return int 0  on success,
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function set_quest_vis($quest_public, $course_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "UPDATE queue_state SET quest_public = ?
            WHERE course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "ii", $quest_public, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Moves a student up one position in the queue
 *
 * @param  string $stud_username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function increase_stud_priority($stud_username, $course_id){
  return change_stud_priority($stud_username, $course_id, "increase");
}

/**
 * Moves a student down one position in the queue
 *
 * @param  string $stud_username
 * @param  int    $course_id
 * @return int 0  on success
 *         int -1 on general
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function decrease_stud_priority($stud_username, $course_id){
  return change_stud_priority($stud_username, $course_id, "decrease");
}

/**
 * Get the state of the queue
 *
 * @param  int    $course_id
 * @return string $state of queue
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function get_queue_state($course_id){
  return change_queue_state($course_id, NULL);
}

/**
 * Open the queue
 *
 * @param  int  $course_id
 * @return int  0 on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function open_queue($course_id){
  $ret = change_queue_state($course_id, "open");
  if($ret == "open"){
    return 0;
  }
  return $ret;
}

/**
 * Close the queue
 *
 * @param  int $course_id
 * @return int  0 on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function close_queue($course_id){
  $ret = change_queue_state($course_id, "closed");
  if($ret == "closed"){
    return 0;
  }
  return $ret;
}

/**
 * Freeze the queue
 *
 * @param  string $course_id
 * @return int  0 on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function freeze_queue($course_id){
  $ret = change_queue_state($course_id, "frozen");
  if($ret == "frozen"){
    return 0;
  }
  return $ret;
}

/**
 * Post announcement to the course
 *
 * @param int    $course_id
 * @param string $announcement
 * @param string $poster
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function add_announcement($course_id, $announcement, $poster){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $res = check_course_id($course_id, $sql_conn);
  if($res == -1){
    mysqli_close($sql_conn);
    return -1; //SQL error
  }elseif($res == 0){
    mysqli_close($sql_conn);
    return -2; //Nonexistant course
  }

  $query = "INSERT INTO announcements (course_id, announcement, poster) VALUES (?, ?, ?)";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "iss", $course_id, $announcement, $poster);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt) || !mysqli_stmt_affected_rows($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Delete announcement for course
 *
 * @param  int $course_id
 * @param  int $announcement_id
 * @return int  0 on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 */
function del_announcement($course_id, $announcement_id){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $res = check_course_id($course_id, $sql_conn);
  if($res == -1){
    mysqli_close($sql_conn);
    return -1; //SQL error
  }elseif($res == 0){
    mysqli_close($sql_conn);
    return -2; //Nonexistant course
  }

  $query = "DELETE FROM announcements
            WHERE id = ? AND course_id = ?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "ii", $announcement_id, $course_id);
  $ret = 0;
  if(!mysqli_stmt_execute($stmt)){
    $ret = -1;
  }

  mysqli_stmt_close($stmt);
  mysqli_close($sql_conn);
  return $ret;
}

//HELPER FUNCTIONS
/**
 * Changes the state of the course queue
 *
 * I'd like to move the input and output states
 * from strings to ints
 *
 * TODO: This function should be rewritten. It's not clean.
 *
 * @param int    $course_id
 * @param string $state
 * @return string $state of queue
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -9 on disabled course
 */
function change_queue_state($course_id, $state){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $res = check_course_id($course_id, $sql_conn);
  if($res == -1){
    mysqli_close($sql_conn);
    return -1; //SQL error
  }elseif($res == 0){
    mysqli_close($sql_conn);
    return -2; //Nonexistant course
  }

  if(is_null($state)){ //Just querying the state of the queue if $state==NULL
    $query  = "SELECT state FROM queue_state WHERE course_id ='".$course_id."'";
    $result = mysqli_query($sql_conn, $query);
    if(!$result){
      mysqli_close($sql_conn);
      return -1;
    }
    if(!mysqli_num_rows($result)){
      mysqli_close($sql_conn);
      return "closed";
    }
    $entry = mysqli_fetch_assoc($result);
    mysqli_close($sql_conn);
    return $entry["state"];
  }elseif($state == "closed"){ //By deleting the entry in queue_state, we cascade the other entries
    //The above comment is correct, but cascading the other entries DOES NOT execute the triggers
    //We need to manually delete the TAs/students in the queue to fire the delete triggers.
    $query = "DELETE FROM ta_status WHERE course_id = '".$course_id."'";
    mysqli_query($sql_conn, $query);
    $query = "DELETE FROM queue WHERE course_id = '".$course_id."'";
    mysqli_query($sql_conn, $query);
    $query = "DELETE FROM queue_state WHERE course_id = '".$course_id."'";
  }elseif($state == 'frozen' || $state == 'open'){ //Since REPLACE calls DELETE then INSERT, calling REPLACE would CASCADE all other tables, we use ON DUPLICATE KEY UPDATE instead
    //Check if the course is disabled
    $course_state = get_course_state($course_id);
    if(is_null($course_state)){
      mysqli_close($sql_conn);
      return -1;
    }elseif(!$course_state){
      mysqli_close($sql_conn);
      return -9;
    }
    $query = "INSERT INTO queue_state (course_id, state) VALUES ('".$course_id."','".$state."') ON DUPLICATE KEY UPDATE state='".$state."'";
  }else{
    mysqli_close($sql_conn);
    return -1;
  }

  if(!mysqli_query($sql_conn, $query)){
    mysqli_close($sql_conn);
    return -1;
  }

  mysqli_close($sql_conn);
  return $state;
}

/**
 * Changes a students position in the queue
 *
 * @param  string $stud_username
 * @param  int    $course_id
 * @param  string $operation {increase, decrease}
 * @return int 0  on success
 *         int -1 on general error
 *         int -2 on nonexistent course
 *         int -3 on closed course
 */
function change_stud_priority($stud_username, $course_id, $operation){
  $sql_conn = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASSWD, DATABASE);
  if(!$sql_conn){
    return -1;
  }

  $queue_state = get_queue_state($course_id);
  if($queue_state < 0){
    mysqli_close($sql_conn);
    return $queue_state;
  }elseif($queue_state == "closed"){
    mysqli_close($sql_conn);
    return -3;
  }

  $query = "SELECT position FROM queue
            WHERE username=?
            AND course_id=?
            AND position NOT IN (SELECT helping FROM ta_status WHERE helping IS NOT NULL AND course_id=?)";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "sii", $stud_username, $course_id, $course_id);
  if(!mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    mysqli_close($sql_conn);
    return -1;
  }
  mysqli_stmt_bind_result($stmt, $position1);
  if(is_null(mysqli_stmt_fetch($stmt))){
    mysqli_stmt_close($stmt);
    mysqli_close($sql_conn);
    return 0; //User not in queue, or is currently being helped, so don't move anyone
  }
  mysqli_stmt_close($stmt);

  if($operation == "increase"){
    $query = "SELECT position FROM queue
              WHERE position<'".$position1."' AND course_id='".$course_id."' AND position NOT IN (SELECT helping FROM ta_status WHERE helping IS NOT NULL AND course_id='".$course_id."')
              ORDER BY position DESC LIMIT 1";
  }elseif($operation == "decrease"){
    $query = "SELECT position FROM queue
              WHERE position>'".$position1."' AND course_id='".$course_id."' AND position NOT IN (SELECT helping FROM ta_status WHERE helping IS NOT NULL AND course_id='".$course_id."')
              ORDER BY position ASC LIMIT 1";
  }else{
    mysqli_close($sql_conn);
    return -1;
  }

  $result = mysqli_query($sql_conn, $query);
  if(!$result){
    mysqli_close($sql_conn);
    return -1;
  }

  $entry = mysqli_fetch_assoc($result);
  if(!$entry){
    mysqli_close($sql_conn);
    return 0;//Nobody to switch with
  }

  $position2 = $entry['position'];

  #####SQL TRANSACTION#####
  mysqli_autocommit($sql_conn, false);

  $query = "UPDATE queue set position='-1' WHERE position='".$position1."'";
  $res = mysqli_query($sql_conn, $query);
  $query = "UPDATE queue set position='".$position1."' WHERE position='".$position2."'";
  $res = mysqli_query($sql_conn, $query) && $res;
  $query = "UPDATE queue set position='".$position2."' WHERE position='-1'";
  $res = mysqli_query($sql_conn, $query) && $res;

  $ret = 0;
  if($res){
    mysqli_commit($sql_conn);
  }else{
    mysqli_rollback($sql_conn);
    $ret = -1;
  }
  #########################

  mysqli_close($sql_conn);
  return $ret;
}

/**
 * Retrieves the cooldown setting for a course
 *
 * @param string $course_id
 * @param string $sql_conn
 * @return int  0 if no cooldown set
 *         int -1 on general error
 *         int >0 in cooldown minutes
 */
function get_course_cooldown($course_id, $sql_conn){
  if(!$sql_conn){
    return -1;
  }

  $query = "SELECT cooldown FROM queue_state WHERE course_id=?";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "s", $course_id);
  if(!mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    return -1;
  }

  $cooldown = 0;
  mysqli_stmt_bind_result($stmt, $cooldown);
  mysqli_stmt_fetch($stmt);

  mysqli_stmt_close($stmt);
  return $cooldown;
}

/**
 * Checks if a user may join the queue based on the given cooldown time.
 *
 * @param string $stud_username
 * @param string $course_cooldown in minutes
 * @param string $course_id
 * @param string $sql_conn
 * @return int  0 if able to join
 *         int -1 on general error
 *         int >0 in seconds until able to join
 */
function check_user_cooldown($stud_username, $course_cooldown, $course_id, $sql_conn){
  if(!$sql_conn){
    return -1;
  }

  $query = "SELECT TIME_TO_SEC(TIMEDIFF(NOW(), exit_tmstmp)) as user_cooldown FROM student_log WHERE username = ? AND course_id = ? AND help_tmstmp != 0 ORDER BY help_tmstmp DESC LIMIT 1";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "si", $stud_username, $course_id);
  if(!mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    return -1;
  }

  mysqli_stmt_bind_result($stmt, $user_cooldown);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);

  $course_cooldown_min = $course_cooldown * 60;
  if(is_null($user_cooldown)){
    return 0; //Good to go
  }elseif($course_cooldown_min > $user_cooldown){
    return $course_cooldown_min - $user_cooldown;
  }else{
    return 0;
  }
}

/**
 * Checks if the provided course_id is valid
 *
 * @param string $course_id
 * @param string $sql_conn
 * @return int -1 on general error
 *         int  0 if not valid
 *         int  1 if valid
 */
function check_course_id($course_id, $sql_conn){
  if(!$sql_conn){
    return -1;
  }

  $query = "SELECT EXISTS( SELECT 1 FROM courses WHERE course_id = ?)";
  $stmt  = mysqli_prepare($sql_conn, $query);
  if(!$stmt){
    return -1;
  }
  mysqli_stmt_bind_param($stmt, "i", $course_id);
  if(!mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    return -1;
  }

  mysqli_stmt_bind_result($stmt, $course_exists);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
  return $course_exists;
}
?>
