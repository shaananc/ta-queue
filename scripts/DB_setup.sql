create database ta_queue;

use ta_queue;

--User data;
create table users(
  username    VARCHAR(256),
  first_name  VARCHAR(32) NOT NULL,
  last_name   VARCHAR(32) NOT NULL,
  full_name   VARCHAR(64) NOT NULL,
  email       VARCHAR(256) NOT NULL,
  admin       BOOLEAN DEFAULT false NOT NULL,
  first_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login  TIMESTAMP,
  primary key (username)
);

--Course data;
create table courses(
  course_id int NOT NULL AUTO_INCREMENT,
  depart_pref VARCHAR(16) NOT NULL,
  course_num  VARCHAR(16),
  course_name VARCHAR(128) UNIQUE,
  description TEXT,
  access_code VARCHAR(16),
  enabled     BOOLEAN DEFAULT true NOT NULL,
  generic     BOOLEAN DEFAULT false NOT NULL,
  primary key (course_id)
);

--Students enrolled in course as student or TA;
create table enrolled(
  username    VARCHAR(256),
  course_id   int NOT NULL,
  role        ENUM('student','ta','instructor') NOT NULL,
  primary key (username, course_id),
  foreign key (username) references users(username) ON DELETE CASCADE,
  foreign key (course_id) references courses(course_id) ON DELETE CASCADE
);

--State of each queue;
--Closed queues don't appear here
create table queue_state(
  course_id     int,
  state         ENUM('open','frozen') NOT NULL,
  time_lim      int UNSIGNED DEFAULT 0 NOT NULL,
  cooldown      int UNSIGNED DEFAULT 0 NOT NULL,
  quest_public  BOOLEAN DEFAULT true NOT NULL,
  primary key (course_id),
  foreign key (course_id) references courses(course_id) ON DELETE CASCADE
);

--Master queue for all courses;
--foreign key contraints guarantee student is enrolled in course
--and queue is open
create table queue(
  position   BIGINT AUTO_INCREMENT,
  username   VARCHAR(256) NOT NULL,
  course_id  int NOT NULL,
  question   TEXT,
  location   VARCHAR(256) NOT NULL,
  primary key (position),
  foreign key (username, course_id) references enrolled(username, course_id) ON DELETE CASCADE,
  foreign key (course_id) references queue_state(course_id) ON DELETE CASCADE,
  unique (username, course_id)
);

--State of each TA on duty--
create table ta_status(
  username     VARCHAR(256) NOT NULL,
  course_id    int NOT NULL,
  helping      BIGINT,
  state_tmstmp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  primary key  (username, course_id),
  foreign key  (username) references users(username) ON DELETE CASCADE,
  foreign key  (course_id) references queue_state(course_id) ON DELETE CASCADE,
  foreign key  (helping) references queue(position) ON DELETE SET NULL
);

--Announcements--
create table announcements(
  id             BIGINT AUTO_INCREMENT,
  course_id      int NOT NULL,
  announcement   TEXT,
  poster         VARCHAR(256),
  tmstmp         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  primary key    (id),
  foreign key    (course_id) references courses(course_id) ON DELETE CASCADE,
  foreign key    (poster) references users(username) ON DELETE SET NULL
);

--Student Logs--
create table student_log(
  id             BIGINT AUTO_INCREMENT,
  username       VARCHAR(256) NOT NULL,
  course_id      int,
  question       TEXT,
  location       VARCHAR(256) NOT NULL,
  enter_tmstmp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  help_tmstmp    TIMESTAMP,
  helped_by      VARCHAR(256),
  exit_tmstmp    TIMESTAMP,
  primary key    (id),
  foreign key    (helped_by) references users(username)    ON DELETE SET NULL,
  foreign key    (username)  references users(username)    ON DELETE CASCADE,
  foreign key    (course_id) references courses(course_id) ON DELETE SET NULL  
);

--Trigger for entry into queue--
CREATE TRIGGER log_student_entry AFTER INSERT ON queue FOR EACH ROW 
INSERT INTO student_log (username, course_id, question, location) 
VALUES (NEW.username, NEW.course_id, NEW.question, NEW.location);

--Trigger for question/location update--
CREATE TRIGGER update_question_location AFTER UPDATE ON queue FOR EACH ROW
UPDATE student_log SET question = NEW.question, location = NEW.location
WHERE username=OLD.username AND course_id=OLD.course_id ORDER BY id DESC LIMIT 1;

--Trigger for exit from queue--
CREATE TRIGGER log_student_exit AFTER DELETE ON queue FOR EACH ROW
UPDATE student_log SET exit_tmstmp = CURRENT_TIMESTAMP 
WHERE username=OLD.username AND course_id=OLD.course_id ORDER BY id DESC LIMIT 1;

--Trigger for helped in queue--
CREATE TRIGGER log_student_help AFTER UPDATE ON ta_status FOR EACH ROW
UPDATE student_log SET help_tmstmp = CURRENT_TIMESTAMP, helped_by = NEW.username
WHERE username=(SELECT username FROM queue where position=NEW.helping) AND course_id=NEW.course_id ORDER BY id DESC LIMIT 1;

--TA Logs--
create table ta_log(
  id             BIGINT AUTO_INCREMENT,
  username       VARCHAR(256) NOT NULL,
  course_id      int,
  enter_tmstmp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  exit_tmstmp    TIMESTAMP,
  primary key    (id),
  foreign key    (username)  references users(username)    ON DELETE CASCADE,
  foreign key    (course_id) references courses(course_id) ON DELETE SET NULL
);

--Trigger for TA on duty--
CREATE TRIGGER log_ta_entry AFTER INSERT ON ta_status FOR EACH ROW
INSERT INTO ta_log (username, course_id)
VALUES (NEW.username, NEW.course_id);

--Trigger for TA off duty--
CREATE TRIGGER log_ta_exit AFTER DELETE ON ta_status FOR EACH ROW
UPDATE ta_log SET exit_tmstmp = CURRENT_TIMESTAMP
WHERE username=OLD.username AND course_id=OLD.course_id ORDER BY id DESC LIMIT 1;

--Queue State Logs--
create table queue_state_log(
  id             BIGINT AUTO_INCREMENT,
  course_id      int,
  state          ENUM('open','frozen','closed') NOT NULL,
  tmstmp         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  primary key    (id),
  foreign key    (course_id) references courses(course_id) ON DELETE CASCADE
);

--Triggers for Queue Open/Freeze--
CREATE TRIGGER log_queue_state AFTER INSERT ON queue_state FOR EACH ROW
INSERT INTO queue_state_log (course_id, state)
VALUES (NEW.course_id, NEW.state);

delimiter //
CREATE TRIGGER log_queue_state2 AFTER UPDATE ON queue_state FOR EACH ROW
BEGIN
  IF NEW.state != OLD.state THEN
    INSERT INTO queue_state_log (course_id, state) VALUES (NEW.course_id, NEW.state);
  END IF;
END;
//
delimiter ;

--Trigger for Queue Close--
CREATE TRIGGER log_queue_close AFTER DELETE ON queue_state FOR EACH ROW
INSERT INTO queue_state_log (course_id, state)
VALUES (OLD.course_id, 'closed');
