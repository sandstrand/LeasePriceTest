	
  	<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Cairo&display=swap">
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<link rel="stylesheet" href="style.css" >
  	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
  	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script>
  		$( function() {
    		$( "#LeaseStartDatePicker" ).datepicker({
    				dateFormat: "yy-mm-dd",
    				firstDay: 1,
    				minDate: 0,
    				altField: "#LeaseStart",
    				defaultDate: '<?=date('Y-m-d',$LeaseStart);?>'
    			}
        	);
    		$( "#LeaseEndDatePicker" ).datepicker({
    				dateFormat: 'yy-mm-dd',
    				firstDay: 1,
    				minDate: 0,
    				altField: "#LeaseEnd",
     				defaultDate: '<?=date('Y-m-d', $LeaseEnd);?>'
    			}
    		);
			$("#add-interval").click(function(){
				var $div = $('div[id^="another-interval"]:last');

				// Read the Number from that DIV's ID and increment that number by 1
				var num = parseInt( $div.prop("id").match(/\d+/g), 10 ) +1;

				// Clone it and assign the new ID
				var $klon = $div.clone().prop('id', 'another-interval'+num );

				// For each of the inputs inside the dive, clear it's value and increment the number in the 'name' attribute by 1
				$klon.find('input').each(function() {
				    this.value= "";
				    let name_number = this.name.match(/\d+/);
				    name_number++;
				    this.name = this.name.replace(/\[[0-9]\]+/, '['+name_number+']')
				});
				// Insert $klon after the last div
				$div.after( $klon );
			});
		});
  	</script>


