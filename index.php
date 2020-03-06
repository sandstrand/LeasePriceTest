<?php //Init
	
	// Display options
	$Options = array(
		'PriceTableRows' => 60,
		'Debug' => true 
	);

	//Fetch from form
	$SeasonPrice = 	( !empty($_GET['SeasonPrice']) ) ? $_GET['SeasonPrice'] : null ;  
	$LeaseLimit = 	( !empty($_GET['LeaseLimit']) ) ? $_GET['LeaseLimit'] : null ; 
	$LeaseBuffer = 	( !empty($_GET['LeaseBuffer']) ) ? $_GET['LeaseBuffer'] : null ; 
	$EarlyPickup = 	( !empty($_GET['EarlyPickup']) ) ? $_GET['EarlyPickup'] : null ; 
	$LateReturn = 	( !empty($_GET['LateReturn']) ) ? $_GET['LateReturn'] : null ; 
	$LeasePrice = 	( !empty($_GET['other_interval']) ) ? $_GET['other_interval'] : null ;
	$LeaseStart = 	( !empty($_GET['LeaseStart']) )	? strtotime($_GET['LeaseStart']) : null ;
	$LeaseEnd = 	( !empty($_GET['LeaseEnd']) ) ? strtotime($_GET['LeaseEnd']) : null ;

	// Create and populate $Lease with given attributes and arguments
	$Lease = array(
		'SeasonPrice' => $SeasonPrice,
		'LeaseLimit' => $LeaseLimit,
		'LeaseBuffer' => $LeaseBuffer,
		'EarlyPickup' => $EarlyPickup,
		'LateReturn' => $LateReturn,
		'LeasePrice' => $LeasePrice,
		'LeaseStart' => $LeaseStart,
		'LeaseEnd' => $LeaseEnd
	);

if(!empty($_GET)){ // Do some math
 
	// Calculate and return lease length (or sequence length) as int of days
	function GetLength($Start, $End, $Buffer=0){
		$Length = ( isset($Buffer) ) ? abs($Start - $End ) / (60 * 60 * 24) + $Buffer : abs($Start - $End ) / (60 * 60 * 24) ; 
		return $Length; 
	}

	// Calculate and return Early pickup date as unix time 
	function GetPickupDate($LeaseStart, $EarlyPickup){
		if(empty($EarlyPickup)){
			return $LeaseStart;
		}
		return $LeaseStart - (abs($EarlyPickup) * (60 * 60 * 24));
	}
	
	// Calculate and return Late return date as unix time
	function GetReturnDate($LeaseEnd, $LateReturn, $Buffer=null){
		if(empty($LateReturn)){
			return $LeaseEnd;
		}
		$ReturnDate = ( isset($Buffer) ) ? $LeaseEnd + (abs($LateReturn + $Buffer) * (60 * 60 * 24))  : $LeaseEnd + (abs($LateReturn) * (60 * 60 * 24)) ;
		return $ReturnDate;			
	}
	
		// Returns true if Lease length < Lease Limit
	function CheckLeaseLimit(){
		return true;
	}

	// Returns true if Lease price > Season price
	function CheckLeaseRoof(){
		return true;
	}

	// Build JSON object from array
	function GetJsonFromArray($LeasePrice){
		if(Count($LeasePrice) <= 0){
			return 'Error: No intervals in Price array';
		}

		$NewArray = '';
		foreach ($LeasePrice as $key => $value) {
			if(!empty($value['day'])){
				$NewArray[$value['day']] = (INT)$value['price'];
			}
		}
		return $NewArray;
	}

	//Populate $Lease with calculated data and attributes
	$Lease['LeaseLength'] = 	GetLength($Lease['LeaseStart'], $Lease['LeaseEnd']);
	$Lease['EarlyPickupDate'] = GetPickupDate($Lease['LeaseStart'], $Lease['EarlyPickup']);
	$Lease['LateReturnDate'] = 	GetReturnDate($Lease['LeaseEnd'], $Lease['LateReturn']);
	$Lease['LeasePriceJson'] =  GetJsonFromArray($Lease['LeasePrice']);
	$Lease['ReserveLength'] = 	GetLength($Lease['EarlyPickupDate'], $Lease['LateReturnDate'], $Lease['LeaseBuffer']) ;
	$Lease['ReserveEnd'] = 		GetReturnDate($Lease['LeaseEnd'], $Lease['LateReturn'], $Lease['LeaseBuffer']);
	
	// Calculate Lease price as table OR sum
	function GetLeasePrice($Lease, $Condensed=false){ 
		
		// No posts in LeasePrice Array
		if( count($Lease['LeasePrice']) <= 0 ){
			return 'Error: No breakpoints in price ';
		}
		// Negative value for LeaseLength
		if( $Lease['LeaseLength'] < 0 ) {
			return 'Error: LeaseLength < 0 ';
		}
		//Same day return. 0 days
		if( $Lease['LeaseLength'] == 0 ) {
			$n=0;
			while( !isset($Lease['LeasePrice'][0]) && $n < count($Lease['LeasePrice'])) {	
				$n++;
			}
			// Traversed to first post in Price Array, Returning one day price
			return $Lease['LeasePrice'][$n]['price'];
		}
		// Calculate sum by traversing for length of period and adding pirce for each day
		$LeaseSum = null;
		$Sum = null;
		for( $i = 0 ; $i <= $Lease['LeaseLength'] ; $i++){
			$LeaseSum += GetLeaseDayPrice($Lease,$i);
			//$Sum += GetLeaseDayPrice($Lease,$i);	
			//$LeaseSum[$i] = GetLeaseDayPrice($Lease,$i) . " : " . $Sum;
		}
		return $LeaseSum;
	}

	function GetLeaseDayPrice($Lease, $day){
		// No posts in LeasePrice Array
		if( count($Lease['LeasePrice']) <= 0 ){
			return 'Error: No breakpoints in price ';
		}

		// Missing or negative day in lease
		if((!isset($day) && empty($day)) || $day < 0){
			return '';
		}
		// Same day return. Find next breakpoint and apply for day.
		if( $day == 0 && $Lease['LeaseLength'] == 0){
			for ($i = 0 ; $i <= 1000 ; $i++ ){
	  			if(isset($Lease['LeasePrice'][$i]['price']) && $Lease['LeasePrice'][$i]['day'] >= $day){		
	  				return $Lease['LeasePrice'][$i]['price'];
				}
	  		}
		}
		// First day of lease longer tha 0 deays
		if ( $day == 0 && $Lease['LeaseLength'] != 0){
			return 0;
		}

		// No matchig breakpoint; find previous breakpoint and apply price. 
		// Possibly redundant to next search
  		for ($i = $day ; $i >=0 ; $i-- ){
  			if( isset($Lease['LeasePrice'][$i]['price']) && $Lease['LeasePrice'][$i]['day'] <= $day ){
  				return $Lease['LeasePrice'][$i]['price']; 
			}
  		}
  		// If no previous breakpoint can be found, return 0. 
  		//This may be deliberate, giving free days on leases longer than0
  		return 0;   	 		
	}

	// Takes number of days and returns UNIX time units
	function FormatDate($date){
		return $date * (60 * 60 * 24);
	}

	function GetLeaseRows($Lease){

		// Prepare
		$LeaseRows = array();
		$LeaseSum = '';
		$n = '';
		// Traverse ReserveLEngth
		for($i = 0 ; $i <= $Lease['ReserveLength'] ; $i++ ){
			
			//Set common values			
			// Set values depending Sequence date and its relation to other dates
			
			// Sequence date == Sequence start date && Sequence date != Lease start date | Earliest possible pickup
			if( $Lease['EarlyPickupDate'] + FormatDate($i) != $Lease['LeaseStart'] && $Lease['EarlyPickupDate'] + FormatDate($i) == $Lease['EarlyPickupDate'] ){
				$style = 'early first'; 
				$comment = "Earliest possible pickup. Equipment reserved and packed.";
 			}

			// Sequence date > Sequence start date && Sequence date < Lease start date | Early pickup
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) > $Lease['EarlyPickupDate'] && $Lease['EarlyPickupDate'] + FormatDate($i) < $Lease['LeaseStart'] ){
				$style = 'early'; 
				$comment = "Possible early pickup. Equipment reserved and packed.";
			}

			// Sequence date == Lease start date | First day of lease 
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) == $Lease['LeaseStart'] ){
				$style = 'lease last'; 
				$comment = "First day of lease."; 
				$n = 0;
				$LeasePrice = GetLeaseDayPrice($Lease, $n);
				$LeaseSum += $LeasePrice;
			}

			// Sequence date > Lease start date && Sequence date < Lease end date| Ongoing leas 
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) > $Lease['LeaseStart'] && $Lease['EarlyPickupDate'] + FormatDate($i) < $Lease['LeaseEnd'] ){
				$style = 'lease';
				$comment = "Ongoing lease.";
				$n++; 
				$LeasePrice = GetLeaseDayPrice($Lease, $n);
				$LeaseSum += $LeasePrice;
			}

			// Sequence Date == Lease end date | Last day of lease
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) == $Lease['LeaseEnd'] ){
				$style = 'lease last'; 
				$comment = "Last day of lease."; 
				$n++;
				$LeasePrice = GetLeaseDayPrice($Lease, $n);
				$LeaseSum += $LeasePrice;
			}
			
			// Sequence date > Lease end date && Sequence date < Reserved - Buffer | Late return 
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) > $Lease['LeaseEnd'] && $Lease['EarlyPickupDate'] + FormatDate($i) < $Lease['LeaseEnd'] + FormatDate($Lease['LateReturn']) ){
				$style = 'late';
				$comment = "Possible late return. Equipment reserved unless returned."; 
				
				//Clear
				$n = '';
				$LeasePrice = '';
				$LeaseSum = '';
			} 

			// Sequence date > Lease end date && Sequence date < Reserved - Buffer | Late return 
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) > $Lease['LeaseEnd'] && $Lease['EarlyPickupDate'] + FormatDate($i) == $Lease['LeaseEnd'] + FormatDate($Lease['LeaseBuffer']) ){
				$style = 'late last';
				$comment = "Latest day to return. Equipment reserved unless returned."; 
			}

			// Sequence date > Lease end date + LeaseBuffer && Sequence date < Reserved - Buffer | Late return 
			elseif( $Lease['EarlyPickupDate'] + FormatDate($i) < $Lease['ReserveEnd'] && $Lease['EarlyPickupDate'] + FormatDate($i) > $Lease['LeaseEnd'] + FormatDate($Lease['LeaseBuffer']) ){
				$style = 'buffer';
				$comment = "Lease buffer. Equipment reserved unless returned."; 
			}
			
			$LeaseRows[$i] .= "<tr class='" . $style . "''><td>";
				$LeaseRows[$i] .=	date('D', $Lease['EarlyPickupDate'] + ($i * (60 * 60 * 24)));		
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $date = date('Y-m-d', $Lease['EarlyPickupDate'] + ($i * (60 * 60 * 24)));
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $i;
			$LeaseRows[$i] .= "</td><td>";				
				$LeaseRows[$i] .= $n;	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $LeasePrice; 	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $LeaseSum; 	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $comment; 	
			$LeaseRows[$i] .="</td></tr>";
				
		}
		return $LeaseRows;
	}


	function GetPriceRows($Lease, $Limit=40){

		// Prepare
		$LeaseRows = array();
		$LeaseSum = '';
		// Traverse ReserveLEngth
		for($i = 0 ; $i <= $Limit ; $i++ ){
				$LeasePrice = GetLeaseDayPrice($Lease, $i);
				$LeaseSum += $LeasePrice;		
					
			// Set values depending Sequence date and its relation to other dates
			$LeaseRows[$i] .= "<tr><td>";
				$LeaseRows[$i] .= $i;	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $LeasePrice; 	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $LeaseSum; 	
			$LeaseRows[$i] .= "</td><td>";
				$LeaseRows[$i] .= $comment; 	
			$LeaseRows[$i] .="</td></tr>";
				
		}
		return $LeaseRows;
	}
}
?>

<!DOCTYPE html>
<html lang="en-US">
<head>	
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
    		});
    		$( "#LeaseEndDatePicker" ).datepicker({
    			dateFormat: 'yy-mm-dd',
    			firstDay: 1,
   				minDate: 0,
   				altField: "#LeaseEnd",
   				defaultDate: '<?=date('Y-m-d', $LeaseEnd);?>'
    		});
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
</head>

<body>
	<form>

	<div class="row main"> 
		<div class="col-md-6">
			<div class="content">
				<h1>Proof of concept: Variable pricing</h1>
				<p>aa</p>
				<h2>Test data presets</h2>
				<ul>
					<li><a href="https://legofarmen.se/gist/LeasePrice.php">Suggestion/Example for product 'Senior Medel'</a></li>
					<li><a href="https://legofarmen.se/gist/LeasePrice.php">Lease length exceeds the product lease limit</a></li>
					<li><a href="https://legofarmen.se/gist/LeasePrice.php">Lease price exceeds Season price/a></li>
					<li><a href="https://legofarmen.se/gist/LeasePrice.php">Lease length is 0 days</a></li>
					<li><a href="https://legofarmen.se/gist/LeasePrice.php">Added reprieve for pickup and return</a></li>
				</ul>
			</div>
		</div>

		<div class="col-md-6">		
			<div class="content">
				<h2>Global product attributes</h2>
				<p>The attributes for a specific product set by an central administrator.
				<div class="row">
					<hr />
					<div class="col-lg-9">
						<label for="SeasonPrice">Season lease price</label>
						 <p>The price to lease the equipment for the whole season. Also the maximum price for shorter periods.</p>
					</div>
					<div class="col-lg-3">
						<input type='number' name='SeasonPrice' value='<?=$SeasonPrice;?>'/>
					</div>
					<hr />
					<div class="col-lg-9">						
						<label for="LeaseLimit">Lease limit</label>			
						<p>The number of days after which the price in no longer calculated from the Lease price array, and Season lease price is used instead.</p>
					</div>
					<div class="col-lg-3">
						<input type='number' name='LeaseLimit' value="<?=$LeaseLimit;?>">	
					</div>
					<hr />
					<div class="col-lg-6">	
						<label for="other_interval">Lease pricing</label>
						<p>A series of breakpoints; the day in the lease from wich a price is applied per day, until the day in the lease for next breakpoints is reached.</p>
						<p>If a price is not defined for a day in the lease the price is inherited from whichever day had a price before.</p>
						<p>Days must be supplied in falling order.</p>
					</div>
					<div id="other-intervals" class="col-lg-6">
						<?php
				    	if( count($Lease['LeasePrice']) > 0 && !empty($Lease['LeasePrice'][0]['day'])) {
					    	foreach ($LeasePrice as $key => $value) {
				    			if( !empty($value['day']) && !empty($value['price'])){
						    		echo '<div id="another-interval[' . $key . ']" class="row">';
							    		echo '<div class="col-6">';
							    			echo '<input type="number" name="other_interval[' . $key . '][day]" placeholder="Day" value="' . $value["day"] .'" />';
							    		echo "</div>";
							    		echo '<div class="col-6">';
							    			echo '<input type="number" name="other_interval[' . $key . '][price]" placeholder="Price" value="' . $value["price"]  .'" />';
							    		echo "</div>";
							    	echo "</div>";
					    	 	}
				    		}
				    	}
				    	else {
						    echo '<div id="another-interval[0]" class="row">';
							    echo '<div class="col-6">';
							    	echo '<input type="number" name="other_interval[0][day]" placeholder="Day" value="" />';
							    echo "</div>";
							    echo '<div class="col-6">';
							    	echo '<input type="number" name="other_interval[0][price]" placeholder="Price" value="" />';
							   	echo "</div>";
							echo "</div>";

				    	}


						echo '<button type="button" id="add-interval">+ Add breakpoint</button>';	    	
			    		?>		
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-6 flex">
			<div class="content">
				<h2>Local store product attribute overrides</h2>
				<p>The attributes which a store manager can define for a specific product.</p>
				<div class="row">
				<hr />	
					<div class="col-lg-9">						
						<label for="LeaseBuffer">Lease buffer</label>			
						<p>The number of days the equipment is reserved after the last return date. Set by the Shop manager on product level to account for late returns or repairs.</p>
					</div>
					<div class="col-lg-3">	
						<input type='number' name='LeaseBuffer' value="<?=$LeaseBuffer;?>">	
					</div>
					<hr />  
					<div class="col-lg-9">						
						<label for="EarlyPickup">Early pickup</label>			
						<p>A number of days before the lease start during which the equipment is reserved and can be picked up.</p>
					</div>
					<div class="col-lg-3">	
						<input type='number' name='EarlyPickup' value="<?=$EarlyPickup;?>">	
					</div>
					<hr />
					<div class="col-lg-9">						
						<label for="LateReturn">Late return</label>
						<p>A number of days after the lease end during which the equipment is reserved an can be returned. </p>
					</div>	
					<div class="col-lg-3">	
						<input type='number' name='LateReturn' value="<?=$LateReturn;?>">	
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-6">
			<div class="content">
				<h2>Customer order preference</h2>
				<p>The wishes and preferences communicated by the customer to store personell or the public website.</p>
				<div class="row">
					<hr />
					<div class="col-lg-6">
						<label for="LeaseStart">Lease start</label>
						<p>When does the customer say they want to pickup up the equipment? They pay from this day.</p>	
					</div>
					<div class="col-lg-6">
						<label for="LeaseEnd">Lease end</label>
						<p>When does the customer say they will return the equipment? They pay until this day.</p>				
					</div>
				</div>		
				<div class="row">
					<div class="col-lg-6">
						<div id="LeaseStartDatePicker"></div>
						<input id="LeaseStart" type='date' hidden name='LeaseStart' value='<?=$LeaseStart;?>'/>
					</div>
					<div class="col-lg-6">
						<div id="LeaseEndDatePicker"></div>
						<input id="LeaseEnd" type='date' hidden name='LeaseEnd' value='<?=$LeaseEnd;?>'>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row main slim">
		<div class="col-sm-12">
			<div class="content slim">
				<button type="submit">Submit</button>
			</div>	
		</div>	
	</div>
	
	</form>

<?php if(!empty($_GET)){ // Output results ?>
	
 	<div class="row main">
	<?php 
	if($Options['Debug'] == true) { ?>
	 	<div class="col-md-6">
			<div class="content">
				<h2>Debug</h2>
				<pre>
		Lease: 
		<?=var_export($Lease); ?>  

		EarlyPickupDate:	<?=date('D Y-m-d', $Lease['EarlyPickupDate']); ?>  
		LeaseStart:     	<?=date('D Y-m-d', $Lease['LeaseStart']); ?>  
		LeaseEnd:  			<?=date('D Y-m-d', $Lease['LeaseEnd']); ?>  
		LateReturnDate: 	<?=date('D Y-m-d', $Lease['LateReturnDate']); ?>  
		ReserveEnd: 		<?=date('D Y-m-d', $Lease['ReserveEnd']); ?>  
		SingleDayPrices:  
		<?php 
		for( $i = 0 ; $i <= $Lease['LeaseLength'] ; $i++){
					$DebugLeaseDayPrice[$i] = GetLeaseDayPrice($Lease,$i);
					//$Sum += GetLeaseDayPrice($Lease,$i);	
					//$LeaseSum[$i] = GetLeaseDayPrice($Lease,$i) . " : " . $Sum;
		}
		var_export($DebugLeaseDayPrice);
		?>		
LeaseSum: <?= var_export(GetLeasePrice($Lease)); ?>  
RowDebug: <?= var_export(GetLeaseRows($Lease)['debug']); ?>  
<?= strtotime('+1 week'); ?>  
<?= strtotime('1 week'); ?>  
<?= FormatDate($Lease['LeaseBuffer']); ?>
				</pre>
			</div>
		</div>
	<?php } ?>

		<div class="col-md-6">	
			<div class="content">
				<h2>Result</h2>
			</pre>
			<div class="row">
				<hr />
				<?php if( $Lease['EarlyPickup'] > 0 ){ ?>
				<div class="col-md-6">
					<span class="label">Earliest pickup date</span>
				</div>
				<div class="col-md-6">
					<span class="value"><?=date("D Y-m-d", $Lease['EarlyPickupDate']);?></span>
				</div>
			 	<div class="col-md-12">
			 		<p>The local store product attribute <i>Early pickup</i> allows for the equipment to be picked up <b><?=$Lease['EarlyPickup'];?> day(s)</b> in advance, before the actual lease starts. In effect this means the get to have the equipment <b><?=$Lease['EarlyPickup'];?> day(s)</b> for free.</p><p> The equipment is reserved and packed from this date.</p>
			 	</div>
			 	<hr />
			 	<?php } ?><div class="col-md-6">
					<span class="label">Lease start</span>
				</div>
				<div class="col-md-6">
		 		<span class="value"><?=date("D Y-m-d", $Lease['LeaseStart']);?></span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>This is the first day of the lease, and first day the equipment can be picked up, unless an eralier pickup date is offered by the local store product attribute <i>'Early Pickup'</i>. Regardless, this is the first day the customer pays for.</p>
			 	</div>
			 	<hr />
			 	<div class="col-md-6">
					<span class="label">Lease end</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?=date("D Y-m-d", $Lease['LeaseEnd']);?></span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>This is the last day of the lease, and the last the equipment can to be returned, unless the deadline has been pushed by the local store product attribute <i>'Late return'</i>. Regardless, this is the last day the customer pays for.</p>
			 	</div>
			 	<hr />
			 	<?php if( $Lease['LateReturn'] > 0 ){ ?>
				<div class="col-md-6">
					<span class="label">Latest return date</span>
				</div>
				<div class="col-md-6">
					<span class="value"><?=date("D Y-m-d", $Lease['LateReturnDate']);?></span>
				</div>
			 	<div class="col-md-12">
			 		<p>The local store product attribute <i>Late return</i> allows for the equipment to be returned <b><?=$Lease['LateReturn']?> day(s)</b> later, after the actual lease ends. In effect this means the get to have the equipment <b><?=$Lease['LateReturn'];?> day(s)</b> for free. </p>The equipment is reserved up to and including this date.</p>
			 	</div>
			 	<hr />
			 	<?php } ?>
			 	<div class="col-md-6">
					<span class="label">Total lease price</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= GetLeasePrice($Lease, true); ?>:-</span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The total cost of the lease calculated from the number of days in the lease and the lease price breakpoints for the product. </p>
			 		<p>If the calculated price exceeds the Season price, the Season price is applied instead, meaning the Season price is the maximum amount a lease can cost, regardelss of lease length.</p>
			 		<p>If the lease length is zero days, the price for one day is applied.</p>
			 		<?php if(CheckLeaseRoof()){ echo "<p class='note'>The calculated price for the lease (<b>" . GetLeasePrice($Lease, true) . ":-</b>) is higher than Season price. Season price is applied.</p>"; } ?>
			 		<?php if(CheckLeaseLimit()){ echo "<p class='note'>The number of days in the lease (<b>" . $Lease['LeaseLength'] . " days</b>) exceeds the local store product attribute <i>'Lease limit'</i> (<b>" . $Lease['LeaseLimit'] . " days</b>). Season price is applied.</p>"; } ?>
			 		<?php if($Lease['LeaseLength'] > 1){ echo "<p class='note'>The number of days in the lease is less than one. The price for one day is applied.</p>"; } ?>
			 	</div>
			 	<hr />
			 	<div class="col-md-6">
					<span class="label">Lease length</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= $Lease['LeaseLength']; ?> days</span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The number of days in the lease on which the lease price is calculated. A lease can have length of zero, meaning the equipment is returned the same day it is picked up.</p>
			 	</div>
			 	<hr />
			 	<?php if( $Lease['LeaseLength'] != $Lease['ReserveLength']) { ?>
				<div class="col-md-6">
					<span class="label">Reservation length</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= $Lease['ReserveLength']; ?> days</span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The number of days during which the equipment will be reserved, due to the combination of the Lease start, Lease End, Early pickup, Late return, and Lease buffer attributes. The equipment will be reserved from <b><?= date('D Y-m-d', $Lease['EarlyPickupDate']); ?></b> to <b><?= date('D Y-m-d', $Lease['ReserveEnd']); ?></b>.</p>
			 	</div>
			 	<hr />
			 	<?php } ?>
			 </div>
		</div>
	</div>

	<div class="col-md-6">
		<div class="content">
			<h2>Lease calculation</h2>
			<p>This is how the lease and the different dates are calculated.</p>
			<table>
				<thead>  
					<tr>  
						<td colspan=2>Date</td> 
						<td>Day S
							<div class="tooltip tooltip-right">? 
				  					<span class="tooltiptext">Day in total sequence.</span>
							</div>
						</td>
						<td>Day L
							<div class="tooltip tooltip-center">Day L
				  					<span class="tooltiptext">Day of the lease period.</span>
							</div>
						</td>
						<td>Cost
							<div class="tooltip tooltip-center">Cost
				  					<span class="tooltiptext">The price of this specific day in sequence.</span>
							</div>
						</td>
						<td>Sum
							<div class="tooltip tooltip-center">Sum
				  					<span class="tooltiptext">The acumulated sum of the cost of all days in the sequence up to this day.</span>
							</div>
						</td>
						<td>Comment</td>
					</tr>
				</thead> 
				<tbody>		
					<?php 
					foreach(GetLeaseRows($Lease) as $key => $value){
						echo $value;} 
					?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="col-md-6">
		<div class="content">
			<h2>Price table</h2>
			<p>a</p>
			<table>
				<thead>  
					<tr>  
						<td>Day L
							<div class="tooltip tooltip-center">Day L
				  				<span class="tooltiptext">Day of the lease period.</span>
							</div>
						</td>
						<td>Cost
							<div class="tooltip tooltip-center">Cost
				  					<span class="tooltiptext">The price of this specific day in sequence.</span>
							</div>
						</td>
						<td>Sum
							<div class="tooltip tooltip-center">Sum
				  					<span class="tooltiptext">The acumulated sum of the cost of all days in the sequence up to this day.</span>
							</div>
						</td>
						<td>Comment</td>
					</tr>
				</thead> 
				<tbody>		
					<?php 
					foreach(GetPriceRows($Lease, $Options['PriceTableRows']) as $key => $value){
						echo $value;} 
					?>
				</tbody>
			</table>
		</div>
	</div>
<?php } ?>
</body>
</html>