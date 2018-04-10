var dialog;
var form;

$(document).ready(function(){
  //GET parsing snippet from CHRIS COYIER
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
    if(pair[0] == "course"){
      course = decodeURIComponent(pair[1]);
      break;
    }
  }
  if(typeof course === 'undefined'){
    window.location ='./my_courses.php';
  }

  dialog = $( "#dialog-form" ).dialog({
    autoOpen: false,
    height: 400,
    width: 350,
    modal: true,
    buttons: {
      "Enter Queue": function() {
	  lab_location = document.getElementById("location").value;
	  question = document.getElementById("question").value;
	  enqueue_student(course, question, lab_location);
	  dialog.dialog( "close" );
      },
      Cancel: function() {
        dialog.dialog( "close" );
      }
    }
  });
  $("#duty_button").hide();
  $("#state_button").hide();
  $("#freeze_button").hide();
  $("#time_form").hide();
  $("#cooldown_form").hide();
  $("#join_button").hide();
  $("#new_ann").hide();
  $("#ann_button").hide(); 
  start();
});

function start(){
  $("#title").text(course+' Queue');
  my_username = localStorage.username;
  first_name  = localStorage.first_name;
  last_name   = localStorage.last_name;
    
  var url = "../api/user/my_courses.php";
  var get_req = $.get( url);
  var done = function(data){
    var dataString = JSON.stringify(data);
    var dataParsed = JSON.parse(dataString);
    is_TA = false;
    if($.inArray(course, dataParsed["ta_courses"]) != -1){
      is_TA = true;
    }
    get_queue(course);
    setInterval(get_queue, 5000, course);
  }
  get_req.done(done);
}

function get_queue(course) {
  var url = "../api/queue/get_queue.php";
  var posting = $.post( url, { course: course } );
  posting.done(render_view);
}



//This function renders the view from the data
var render_view = function(data) {
  var dataString = JSON.stringify(data);
  var dataParsed = JSON.parse(dataString);
  if(dataParsed.error){
    alert(dataParsed.error);
    return;
  }

  //Render the top stats: state, time, length
  render_stats(dataParsed);

  //Render the announcements box
  render_ann_box(dataParsed.announce);

  render_ta_table(dataParsed.TAs)
  if(is_TA){
    render_queue_table(dataParsed, "ta");
    render_ta_view(dataParsed)
  }else{
    render_queue_table(dataParsed, "student");
    render_student_view(dataParsed)
  }
}

function render_stats(dataParsed){
 var state = dataParsed.state.charAt(0).toUpperCase() + dataParsed.state.slice(1);
  $("#queue_state").text("State: "+state);
  if(dataParsed.time_lim >0){
     $("#time_limit").text("Time Limit: " + dataParsed.time_lim + " Minutes");
  }else{
     $("#time_limit").text("Time Limit: None");
  }
  if(dataParsed.cooldown >0){
     $("#cooldown").text("Cool-down: " + dataParsed.cooldown + " Minutes");
  }else{
     $("#cooldown").text("Cool-down: None");
  }
  $("#in_queue").text("Queue Length: " + dataParsed.queue_length);
}

function render_ann_box(anns){
  $("#anns tr").remove();
  $('#anns').append("<tr> <th class='col-sm-1' align='left' style='padding-left:10px; text-decoration:underline;'>Date</th> <th class='col-sm-6' align='left' style='padding-left:0px; text-decoration:underline;'>Announcement</th>    <th class='col-sm-1' align='left'></th></tr>");
  for(ann in anns){
    var timestamp       = anns[ann]["tmstmp"].split(" ")[0];
    var announcement    = anns[ann]["announcement"];
    var announcement_id = anns[ann]["id"];
    var new_row =  $('<tr>  <td style="padding-left:10px;"><b>'+timestamp+':</b></td>  <td><b>'+announcement+'</b></td>  </tr>');
    if(is_TA){
      // blue X icon:
      var del_ann_button = $('<button class="btn btn-primary"><i class="fa fa-close" title="Delete"></i></button>');
      // red circle X icon:
      //var del_ann_button = $('<button class="btn btn-danger"><i class="glyphicon glyphicon-remove-sign" title="Delete"></i></button>');
      del_ann_button.click(function(event){
        del_announcement(course, announcement_id)
      });
      new_row.append(del_ann_button);
    }
    $('#anns').append(new_row);
  }
  if(is_TA){
    $("#ann_button").unbind("click");
    $("#new_ann").show();
    $("#ann_button").show();
    $("#ann_button").click(function( event ) {
      event.preventDefault();
      var announcement = document.getElementById("new_ann").value;
      document.getElementById("new_ann").value = "";
      add_announcement(course, announcement)
    });
  }
}

//Shows the TAs that are on duty
function render_ta_table(TAs){
  $("#ta_on_duty h4").remove();
  if(TAs.length < 2)
    $("#tas_header").text("TA on Duty");
  else
    $("#tas_header").text("TAs on Duty");
  for(TA in TAs){
    var full_name = TAs[TA]["full_name"];
    $('#ta_on_duty').append("<h4>"+full_name+"</h4>");
  }
}

function render_ta_view(dataParsed){
  $("#state_button").unbind("click");
  $("#duty_button").unbind("click");
  $("#freeze_button").unbind("click");
  $("#time_form").unbind("submit");
  $("#cooldown_form").unbind("submit");

  var queue_state = dataParsed.state;
  if(queue_state == "closed"){
    //document.getElementById("state_button").style.background='ForestGreen';
    document.getElementById("state_button").className="btn btn-success";
    $("#state_button").text("Open Queue");
    $("#state_button").click(function( event ) {
      event.preventDefault();
      open_queue(course);
    });
    $("#duty_button").hide();
    $("#freeze_button").hide();
    $("#time_form").hide();
    $("#cooldown_form").hide();
  }else{ //open or frozen
    //document.getElementById("state_button").style.background='FireBrick';
    document.getElementById("state_button").className="btn btn-danger";
    $("#state_button").text("Close Queue");
    $("#state_button").click(function( event ) {
      event.preventDefault();
      if (dataParsed.queue_length > 0) {
        var res = confirm("Are you sure you want to close the queue? All students will be removed.")
        if (res) {
          close_queue(course);
        }
      }
      else
        close_queue(course);
    });
   
    if(queue_state == "open"){ 
      //$("body").css("background-image", "-webkit-linear-gradient(top, #808080 0%, #FFFFFF 50%");
      //document.getElementById("freeze_button").style.background='#1B4F72';
      document.getElementById("freeze_button").className="btn btn-primary";
      document.getElementById("freeze_button").title="Prevent new entries";
      $("#freeze_button").text("Freeze Queue");
      $("#freeze_button").click(function( event ) {
        event.preventDefault();
        freeze_queue(course);
      });
    }else{ //frozen
      //$("body").css("background-image", "-webkit-linear-gradient(top, #075685 0%, #FFF 50%");
      //document.getElementById("freeze_button").style.background='Orange';
      document.getElementById("freeze_button").className="btn btn-warning";
      document.getElementById("freeze_button").title="Allow new entries";
      $("#freeze_button").text("Resume Queue");
      $("#freeze_button").click(function( event ) {
        event.preventDefault();
        open_queue(course);
      });
    }

    var TAs_on_duty = dataParsed.TAs;
    var on_duty     = false;
    for(var entry = 0; entry < TAs_on_duty.length; entry++){
      if(TAs_on_duty[entry].username == my_username){
        on_duty = true;
        break;
      }
    } 
    
    if(!on_duty) {
      //document.getElementById("duty_button").style.background='ForestGreen';
      document.getElementById("duty_button").className="btn btn-success";
      $("#duty_button").text("Go On Duty");
      $("#duty_button").click(function(event){
        event.preventDefault();
        enqueue_ta(course);
      });
    }
    else{
      //document.getElementById("duty_button").style.background='FireBrick';
      document.getElementById("duty_button").className="btn btn-danger";
      $("#duty_button").text("Go Off Duty");
      $("#duty_button").click(function(event){
	    event.preventDefault();
	    dequeue_ta(course);
      });
    }
    $("#duty_button").show();
    $("#freeze_button").show();

    // Don't refresh while editing
    if (!$("#time_limit_input").is(":focus")) {
      $("#time_limit_input").val(dataParsed.time_lim);
    }
    $("#time_form").show();
    $("#time_form").submit(function(event){
      event.preventDefault();
      var limit = $(this).find( "input[id='time_limit_input']" ).val();
      set_limit(course, limit);
    });

    if (!$("#cooldown_input").is(":focus")) {
      $("#cooldown_input").val(dataParsed.cooldown);
    }
    $("#cooldown_form").show();
    $("#cooldown_form").submit(function(event){
      event.preventDefault();
      var limit = $(this).find( "input[id='cooldown_input']" ).val();
      set_cooldown(course, limit);
    });
  }
  $("#state_button").show();
}

function render_student_view(dataParsed){
  var queue = dataParsed.queue;
  
  var in_queue = false;
  for(session in queue){
    if(my_username == queue[session]["username"]){
      in_queue = true;
      break;
    }
  }
 
  var state = dataParsed.state; 
  if(state == "closed" || (state == "frozen" && !in_queue )){
    $("#join_button").hide();
    return;
  }

  $("#join_button").unbind("click");
  if(!in_queue){//Not in queue
    $("#join_button").text("Enter Queue");
    $("#join_button").show();
    $("#join_button").click(function( event ) {
      event.preventDefault();
      dialog.dialog( "open" );
    });
  }
  else{ //In queue
    $("#join_button").text("Leave Queue");
    $("#join_button").show();
    $("#join_button").click(function( event ) {
      event.preventDefault();
      dequeue_student(course);
    });
  }
}

//Displays the queue table
function render_queue_table(dataParsed, role){
  var queue = dataParsed.queue;
  var TAs   = dataParsed.TAs;
  //$("#queue tr").remove();
  // $('#queue').append("<tr> <th class='col-sm-1' align='left' style='padding-left:10px; text-decoration:underline;'>Pos.</th>"+
  //                         "<th class='col-sm-2' align='left' style='padding-left:0px; text-decoration:underline;'>Student</th>"+
  //                         "<th class='col-sm-1' align='left' style='padding-left:0px; text-decoration:underline;'>Location</th>"+
  //                         "<th class='col-sm-4' align='left' style='padding-left:5px; text-decoration:underline;'>Question</th> </tr>");

  //$("#queue_head").empty();
  $("#queue_body").empty();
  $('#queue_body').append("<tr style='background: none;'> <th class='col-sm-1' align='left'>Pos.</th>"+
                          "<th class='col-sm-2' align='left'>Student</th>"+
                          "<th class='col-sm-1' align='left'>Location</th>"+
                          "<th class='col-sm-4' align='left'>Question</th> </tr>");
 
  var helping = {};
  for(TA in TAs ){
    if(TAs[TA].helping != null){
      helping[TAs[TA].helping] = TAs[TA].duration;  
    }
  }
  
  var time_lim = dataParsed.time_lim;

  var i = 1;
  for(row in queue){
    let username  = queue[row].username;
    let full_name = queue[row].full_name;
    var question  = queue[row].question;
    var Location  = queue[row].location;
    //var new_row = $("<tr> <td style='padding-left: 10px;'>"+ i +"</td> <td>" + full_name + "</td> <td>" + Location + "</td> <td style='padding-left:5px;'>" + question + "</td> </tr>");
    var new_row = $("<tr><td>" + i + "</td><td>" + full_name + "</td><td>" + Location + "</td><td>" + question + "</td></tr>");
    i++;   
 
    if( username in helping ){
      new_row.css("background-color", "#b3ffb3");
      if(time_lim > 0){
        var duration = helping[username];
        var fields = duration.split(':');
        duration = parseInt(fields[0])*3600 + parseInt(fields[1])*60 + parseInt(fields[2]);
        var time_rem = time_lim*60-duration;

        if(time_rem <= 0){
          new_row.css("background-color", "#fe2b40"); //User is over time limit
	      //$("body").css("background-image", "-webkit-linear-gradient(top, #ff9C00 0%, #fFFFBB 50%");
        }
      }
    }

    if(is_TA) {
      // HELP BUTTON
      if( username in helping ){
        var help_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Stop Helping"> <i class="fa fa-undo"></i>  </button></div>');
        help_button.click(function(event){
          release_ta(course);
        });
      }else{
        var help_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Help Student"><i class="glyphicon glyphicon-hand-left"></i></button></div>');
        help_button.click(function(event){//If a TA helps a user, but isn't on duty, put them on duty
          enqueue_ta(course); //Maybe make this cleaner. 
          help_student(course, username);
        });
      }

      // MOVE UP BUTTON
      var increase_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Move Up"> <i class="fa fa-arrow-up"></i>  </button></div>');
      if(row == 0){
        increase_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Move Up" disabled=true> <i class="fa fa-arrow-up"></i>  </button></div>');
      }
      increase_button.click(function(event){
        inc_priority(course, username); 
      });

      // MOVE DOWN BUTTON
      var decrease_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Move Down"> <i class="fa fa-arrow-down"></i>  </button></div>');
      if(row == dataParsed.queue_length -1){
        decrease_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Move Down" disabled=true> <i class="fa fa-arrow-down"></i>  </button></div>');
      }
      decrease_button.click(function(event){
        dec_priority(course, username);
      });

      // REMOVE BUTTON
      // blue X icon:
      var dequeue_button = $('<div class="btn-group" role="group"><button class="btn btn-primary" title="Remove"> <i class="fa fa-close"></i>  </button></div>');
      // red circle X icon:
      //var dequeue_button = $('<div class="btn-group" role="group"><button class="btn btn-danger" title="Remove"> <i class="glyphicon glyphicon-remove-sign"></i>  </button></div>');
      dequeue_button.click(function(event) {
          dequeue_student(course, username);
      });

      // Create TA button group that spans entire td width and append it to the new row
      var td = $("<td></td>");
      var button_group = $("<div class='btn-group btn-group-justified' role='group' aria-label='...'></div>");
      button_group.append(help_button);
      button_group.append(increase_button);
      button_group.append(decrease_button);
      button_group.append(dequeue_button);
      td.append(button_group)
      new_row.append(td);

      // ORIGINAL SEPARATED BUTTONS
      // new_row.append("<td>");
      // new_row.append(help_button);
      // new_row.append("</td>");
      //
      // new_row.append("<td>");
      // new_row.append(increase_button);
      // new_row.append("</td>");
      //
      // new_row.append("<td>");
      // new_row.append(decrease_button);
      // new_row.append("</td>");
      //
      // new_row.append("<td>");
      // new_row.append(dequeue_button);
      // new_row.append("</td>");

    }else{//student
      // OLD SOLUTION. THE PROBLEM WITH THIS IS THAT THE DIVISION LINES BETWEEN STUDENTS DON'T RENDER
      // ACROSS THE ENTIRE WIDTH OF THE TABLE.
      // if(username == my_username){
      //   var decrease_button = $('<button class="btn btn-primary"> <i class="fa fa-arrow-down"></i>  </button>');
      //   if(row == dataParsed.queue_length -1){
      //       decrease_button = $('<button class="btn btn-primary" disabled=true> <i class="fa fa-arrow-down"></i>  </button>');
      //   }
      //   decrease_button.click(function(event){
      //       dec_priority(course, my_username);
      //   });
      //   new_row.append("<td>");
      //   new_row.append(decrease_button);
      //   new_row.append("</td>");
      // }

      // THIS SOLUTION RENDERS NICELY BUT SEEMS HACKY: MOVE DOWN BUTTON IS RENDERED ON *EVERY* ROW THEN
      // HIDDEN IF IT DOESN'T MATCH THE USER.
      var decrease_button = $('<button class="btn btn-primary" title="Move Down"> <i class="fa fa-arrow-down"></i>  </button>');
      if(row == dataParsed.queue_length -1){
        decrease_button = $('<button class="btn btn-primary" disabled=true title="Move Down"> <i class="fa fa-arrow-down"></i>  </button>');
      }
      decrease_button.click(function(event){
        if (confirm("Are you sure you want to move one spot down?")) {
          dec_priority(course, my_username);
        }
      });
      var td = $("<td></td>");
      var button_group = $("<div></div>");
      button_group.append(decrease_button);
      td.append(button_group)
      new_row.append(td);

      if(username !== my_username){ // Hide the button unless it's on the user's row
        decrease_button.hide();
      }
    }

    $('#queue_body').append(new_row);
  }
}

//API Endpoint calls
done = function(data){
  get_queue(course); //reloads the content on the page
}
fail = function(data){
  var httpStatus = data.status;
  var dataString = JSON.stringify(data.responseJSON);
  var dataParsed = JSON.parse(dataString);
  alert(dataParsed["error"]);
}

function open_queue(course){
  var url = "../api/queue/open.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function close_queue(course){
  var url = "../api/queue/close.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function freeze_queue(course){
  var url = "../api/queue/freeze.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}


function enqueue_student(course, question, Location){
  var url = "../api/queue/enqueue_student.php";
  var posting = $.post( url, { course: course, question: question, location: Location } );
  posting.done(done);
  posting.fail(fail);
}

/*
 *Students call dequeue_student(course, null) to dequeue themselves
 *TAs call dequeue_student(course, username) to dequeue student
 */
function dequeue_student(course, student){
  var url = "../api/queue/dequeue_student.php";
  if(student == null){
    posting = $.post( url, { course: course } );
  }
  else{
    posting = $.post( url, { course: course, student: student } );
  }
  posting.done(done);
  posting.fail(fail);
}

function release_ta(course){
  var url = "../api/queue/release_ta.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function enqueue_ta(course){
  var url = "../api/queue/go_on_duty.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function dequeue_ta(course){
  var url = "../api/queue/go_off_duty.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function inc_priority(course, student){
  var url = "../api/queue/move_up.php";
  var posting = $.post( url, { course: course, student: student } );
  posting.done(done);
  posting.fail(fail);
}

function dec_priority(course, student){
  var url = "../api/queue/move_down.php";
  var posting = $.post( url, { course: course, student: student } );
  posting.done(done);
  posting.fail(fail);
}

function next_student(course){
  var url = "../api/queue/next_student.php";
  var posting = $.post( url, { course: course } );
  posting.done(done);
  posting.fail(fail);
}

function help_student(course, username){
  var url = "../api/queue/help_student.php";
  var posting = $.post( url, { course: course, student: username } );
  posting.done(done);
  posting.fail(fail);
}

function set_limit(course, limit){
  var url = "../api/queue/set_limit.php";
  var posting = $.post( url, { course: course, time_lim: limit.toString() } );
  posting.done(done);
  posting.fail(fail);
}

function set_cooldown(course, limit){
  var url = "../api/queue/set_cooldown.php";
  var posting = $.post( url, { course: course, time_lim: limit.toString() } );
  posting.done(done);
  posting.fail(fail);
}

function add_announcement(course, announcement){
  var url = "../api/queue/add_announcement.php";
  var posting = $.post( url, { course: course, announcement: announcement } );
  posting.done(done);
  posting.fail(fail);
}

function del_announcement(course, announcement_id){
  var url = "../api/queue/del_announcement.php";
  var posting = $.post( url, { course: course, announcement_id: announcement_id } );
  posting.done(done);
  posting.fail(fail);
}
