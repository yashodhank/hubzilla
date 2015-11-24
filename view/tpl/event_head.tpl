<link rel='stylesheet' type='text/css' href='{{$baseurl}}/library/fullcalendar/fullcalendar.css' />
<script language="javascript" type="text/javascript" src="{{$baseurl}}/library/moment/moment.min.js"></script>
<script language="javascript" type="text/javascript" src="{{$baseurl}}/library/fullcalendar/fullcalendar.min.js"></script>
<script language="javascript" type="text/javascript" src="{{$baseurl}}/library/fullcalendar/lang-all.js"></script>

<script>
	function showEvent(eventid) {
		$.get(
			'{{$baseurl}}/events/?id='+eventid,
			function(data){
				$.colorbox({ scrolling: false, html: data, onComplete: function() { $.colorbox.resize(); }});
			}
		);			
	}
	
	function doEventPreview() {
		$('#event-edit-preview').val(1);
		$.post('events',$('#event-edit-form').serialize(), function(data) {
			$.colorbox({ html: data });
		});
		$('#event-edit-preview').val(0);
	}

	function exportDate() {
		var moment = $('#events-calendar').fullCalendar('getDate');
		var sT = 'events/' + moment.year() + '/' + (moment.month() + 1) + '/export';
		window.location.href=sT;
	}
	
	function changeView(action, viewName) {
		$('#events-calendar').fullCalendar(action, viewName);
		var view = $('#events-calendar').fullCalendar('getView');
		$('#title').text(view.title);
	}


	$(document).ready(function() {
		$('#events-calendar').fullCalendar({
			events: '{{$baseurl}}/events/json',
			header: false,
			lang: '{{$lang}}',
			firstDay: {{$first_day}},

			eventLimit: 3,
			height: 'auto',

			monthNames: aStr['monthNames'],
			monthNamesShort: aStr['monthNamesShort'],
			dayNames: aStr['dayNames'],
			dayNamesShort: aStr['dayNamesShort'],

			allDayText: aStr['allday'],
			timeFormat: 'HH:mm',
			eventClick: function(calEvent, jsEvent, view) {
				showEvent(calEvent.id);
			},
			loading: function(isLoading, view) {
				if(!isLoading) {
					$('td.fc-day').dblclick(function() { window.location.href='/events/new?start='+$(this).data('date'); });
				}
			},

			eventRender: function(event, element, view) {
				//console.log(view.name);
				if (event.item['author']['xchan_name']==null) return;

				switch(view.name){
					case "month":
					element.find(".fc-event-title").html(
						"<img src='{0}' style='height:10px;width:10px'>{1} : {2}".format(
							event.item['author']['xchan_photo_s'],
							event.item['author']['xchan_name'],
							event.title
					));
					break;
					case "agendaWeek":
					element.find(".fc-event-title").html(
						"<img src='{0}' style='height:12px; width:12px'>{1}<p>{2}</p><p>{3}</p>".format(
							event.item['author']['xchan_photo_s'],
							event.item['author']['xchan_name'],
							event.item.desc,
							event.item.location
					));
					break;
					case "agendaDay":
					element.find(".fc-event-title").html(
						"<img src='{0}' style='height:24px;width:24px'>{1}<p>{2}</p><p>{3}</p>".format(
							event.item['author']['xchan_photo_s'],
							event.item['author']['xchan_name'],
							event.item.desc,
							event.item.location
					));
					break;
				}
			}
			
		})
		
		// center on date
		var args=location.href.replace(baseurl,"").split("/");
		if (args.length>=4) {
			$("#events-calendar").fullCalendar('gotoDate',args[2] , args[3]-1);
		} 
		
		// show event popup
		var hash = location.hash.split("-")
		if (hash.length==2 && hash[0]=="#link") showEvent(hash[1]);
		
		// echo the title
		var view = $('#events-calendar').fullCalendar('getView');
		$('#title').text(view.title);

		$('#id_share').change(function() {

			if ($('#id_share').is(':checked')) { 
				$('#dbtn-acl').show();
			}
			else {
				$('#dbtn-acl').hide();
			}
		}).trigger('change');


		$('#contact_allow, #contact_deny, #group_allow, #group_deny').change(function() {
			var selstr;
			$('#contact_allow option:selected, #contact_deny option:selected, #group_allow option:selected, #group_deny option:selected').each( function() {
				selstr = $(this).text();
				$('#jot-public').hide();
			});
			if(selstr == null) {
				$('#jot-public').show();
			}

		}).trigger('change');

	});

</script>

