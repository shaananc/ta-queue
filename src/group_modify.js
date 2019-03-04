$(document).ready(function(){
  //GET parsing snippet from CHRIS COYIER
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
    if(pair[0] == "type"){
      type = decodeURIComponent(pair[1]);
    }else if(pair[0] == "course_id"){
      course_id = decodeURIComponent(pair[1]);
    }
  }
  if(type=="ta" && course_id !== undefined){
    ta_modify(course_id);
  }else if(type=="admin"){
    admin_modify();
  }
  else{
    window.location = './';
  }
});

function admin_modify() {
  $("#panel_title").text("Admins");
  $("#jsGrid").jsGrid({
    width: "100%",
    height: "auto",

    editing: false,
    sorting: true,
    paging: true,
    pageSize: 15,
    autoload: true,
    inserting: true,

    deleteConfirm: "Are you sure you want to remove this user from the admin group?",

    controller: {
      loadData: function(filter){
        var deferred = $.Deferred();
        $.ajax({
          type: "GET",
          url: "/api/admins",
          dataType: "json",
          data: filter,
          success: function(response) {
            var admin = [];
            var row;
            for(row in response.admin){
              var current = response.admin[row];
              admin.push({"username": current.username, "full_name": current.full_name});
            }
            deferred.resolve(admin);
          },
          error: function(xhr){
            if(xhr.status == 403){
               window.location = '/';
            }
          }
        });
        return deferred.promise();
      },
      insertItem: function(item) {
        var username = item['username']
        $.ajax({
          type: "POST",
          async: false,
          url: "/api/admins/"+username,
          error: function() {
            alert("User does not exist");
          }
        });
        $("#jsGrid").jsGrid("loadData");
      },
      deleteItem: function(item) {
        var username = item['username']
        return $.ajax({
          type: "DELETE",
          async: false,
          url: "/api/admins/"+username
        });
      },
     },

    fields: [
      { type: "text", name: "username", title: "Username", validate: "required" },
      { type: "text", name: "full_name", title: "Full Name", readOnly: true},
      { type: "control"}
    ]

  });
}

function ta_modify(course_id) {
  $("#panel_title").text("TAs");
  $("#jsGrid").jsGrid({
    width: "100%",
    height: "auto",

    editing: false,
    sorting: true,
    paging: true,
    pageSize: 15,
    autoload: true,
    inserting: true,

    deleteConfirm: "Are you sure you want to remove this user as a TA?",

    controller: {
      loadData: function(filter){
        var deferred = $.Deferred();
        $.ajax({
          type: "GET",
          url: "/api/courses/"+course_id+"/ta",
          dataType: "json",
          data: filter,
          success: function(response) {
            var TAs = [];
            var row;
            for(row in response.TAs){
              var current = response.TAs[row];
              TAs.push({"username": current.username, "full_name": current.full_name});
            }
            deferred.resolve(TAs);
          }
        });
        return deferred.promise();
      },
      insertItem: function(item) {
        var username = item['username']
        $.ajax({
          type: "POST",
          async: false,
          url: "/api/user/"+username+"/courses/"+course_id+"/ta",
          error: function() {
            alert("User does not exist");
          }
        });
        $("#jsGrid").jsGrid("loadData");
      },
      deleteItem: function(item) {
        var username = item['username']
        return $.ajax({
          type: "DELETE",
          async: false,
          url: "/api/user/"+username+"/courses/"+course_id+"/ta"
        });
      },
     },

    fields: [
      { type: "text", name: "username", title: "Username", validate: "required" },
      { type: "text", name: "full_name", title: "Full Name", readOnly: true},
      { type: "control"}
    ]

  });
}

