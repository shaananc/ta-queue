var course;

/**
 * Parse the URL for the course information from GET
 **/
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
	window.location ='./my_courses';
    }
});

$(document).on("change", "#chart", function(e){
    get_number(course);
});

function get_number(course) {
    var url = "../api/stats/course_stats";
    var posting = $.post( url, { course: course } );
    posting.done(parse_it);
};

function parse_it(data) {
    var dataString = JSON.stringify(data.usage);
    var dataParsed = JSON.parse(dataString);
    var new_arr = dataParsed.map((element, index) => {
    	return [Date.parse(element.date), element.students_helped]
    });
    average_plot(new_arr);
};

function average_plot(course_data) {
    $('#container').highcharts({
	chart: {
            type: 'scatter',
            zoomType: 'xy'
	},
	title: {
            text: 'Users helped per day'
	},
	xAxis: {
	    tickInterval: (24 * 3600 * 1000), // the number of milliseconds in a day
	    allowDecimals: false,
	    title: {
		text: 'Date',
		scalable: false
	    },
	    type: 'datetime',
	    labels: {
		formatter: function() {
		    return Highcharts.dateFormat('%y-%b-%d', this.value);
		}
	    }
	},
	yAxis: {
            title: {
		text: 'Number of Students Helped'
            }
	},
	plotOptions: {
            scatter: {
		marker: {
                    radius: 5,
                    states: {
			hover: {
                            enabled: true,
                            lineColor: 'rgb(100,100,100)'
			}
                    }
		},
		states: {
                    hover: {
			marker: {
                            enabled: false
			}
                    }
		},
		tooltip: {
                    headerFormat: '<b>{series.name}</b><br>',
                    pointFormat: '{point.x} date, {point.y} number'
		}
            }
	},
	series: [{
            "name": 'Students Helped',
            "color": 'rgba(223, 83, 83, .5)',
            "data": course_data
	}], 
    });
};

