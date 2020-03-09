<?php //Init
	
	// Display options
	$Options = array(
		'PriceTableRows' => 60,
		'Debug' => $_GET['debug'] 
	);
	
	// Backwards compability for array_key_first
	if (!function_exists('array_key_first')) {
	    function array_key_first($arr) {
	        foreach($arr as $key => $unused) {
	            return $key;
	        }
	        return NULL;
	    }
	}

	// Build and return Lease Arguments from form
	function GetLeaseArgs($Args){
		if(!isset($Args) || count($Args) <= 0 ){
			return "Error: Missing Args";
		} 
		$NewArgsArray = array();
		foreach ($Args as $Arg => $Value) {
			// Ignore empty records
			if(!empty($Value)) {
				// Transform passed prices
				if($Arg == 'other_interval'){
					// Transform to simplified associative array as $Day => $Price
					$Arg = 'LeasePriceArray';
					ksort( $Value = array_column($Value, 'Price', 'Day' ));
					// Cleanup empty input
					unset($Value[null]);
					if (($del = array_search(null, $Value)) !== false) {
						unset($Value[$del]);
					}
				}
				// Transform dates to Unix time
				if( $Arg == 'LeaseStart' || $Arg == 'LeaseEnd' ) {
					$Value = strtotime($Value); 					
				}
				$NewArgsArray[$Arg] =  $Value;	 
			}
				
		}		
		return $NewArgsArray;
	}
	
	//Build and assign Arguments
	$LeaseArgs = GetLeaseArgs($_GET);

	function GetLeaseResults($Args){
		$Results = array();
		$Results['PickupDate'] = $Args['LeaseStart'] - Unixtime($Args['EarlyPickup']);
		$Results['ReturnDate'] = $Args['LeaseEnd'] + UnixTime($Args['LateReturn']);
		$Results['ReservationEnd'] = $Args['LeaseEnd'] + UnixTime($Args['LateReturn']) + UnixTime($Args['LeaseBuffer']);
		$Results['LeaseDuration'] = Duration($Args['LeaseStart'], $Args['LeaseEnd']);
		$Results['ReservationDuration'] = $Args['EarlyPickup'] + Duration($Args['LeaseStart'], $Args['LeaseEnd'])  +  $Args['LateReturn'] + $Args['LeaseBuffer'] ;		
		return $Results;
	}

	// Build and assign results
	$LeaseResults = GetLeaseResults($LeaseArgs);

	// Takes number of days and returns UNIX time units
	function UnixTime($Days){
		return $Days * (60 * 60 * 24);
	}

	// Returns valid integers with currency symbol
	function AddCur($Amount){
		if(isset($Amount) && $Amount !== ''){
			return $Amount . ":-";
		}
	}
	function Duration($Start, $End){
		return $Duration = round(abs($End - $Start) / ( 60 * 60 * 24 )) ; 
		
	}

	// Return given and calculated data on specific day in sequence
	function GetLeaseRows($Args, $Result){
		$LeaseRows = array();
		$Store['RawSum'] = 0;
		$Store['AppliedSum'] =0;	

		for($i = 0 ; $i <= $Result['ReservationDuration']; $i++ ){		
			$LeaseRow = array();
			$LeaseRow['Tags'] = '';
			$LeaseRow['Comment'] = null;
			// Date of day
			$LeaseRow['Date'] = $Args['LeaseStart'] - UnixTime($Args['EarlyPickup']) + UnixTime($i);
			// Day in sequence
			$LeaseRow['DayS'] = $i;
			// Day in Lease. Used to fetch row price
			$LeaseRow['DayL'] = ( $LeaseRow['DayS'] - $Args['EarlyPickup'] >= 0  && $LeaseRow['DayS'] <= $Args['EarlyPickup'] + $Result['LeaseDuration'] ) ? $i - $Args['EarlyPickup'] : null ;  
			
			// Apply tags for early pickup
			if( $LeaseRow['DayS'] == 0 && $Args['EarlyPickup'] > 0){
				$LeaseRow['Comment'] .= "<li>First possible pickup. Equipment reserved and packed.</li>";
				$LeaseRow['Tags'] .= "pickup first ";
			}
			elseif( $LeaseRow['DayS'] > 0 && $LeaseRow['DayS'] < $Args['EarlyPickup']  ){
				$LeaseRow['Comment'] .= "<li>Possible early possible pickup. Equipment reserved.</li>";
				$LeaseRow['Tags'] .= "pickup ";
			}

			// Apply tags for pickup, return and buffer
			if( $LeaseRow['DayS'] > $Args['EarlyPickup'] + $Result['LeaseDuration'] && $LeaseRow['DayS'] < $Args['EarlyPickup'] + $Result['LeaseDuration'] + $Args['LateReturn'] ){
				$LeaseRow['Comment'] .= "<li>Possible late return. Equipment reserved.</li>";
				$LeaseRow['Tags'] .= "return ";
			}
			if( $Args['LateReturn'] > 0 && ( $LeaseRow['DayS'] == $Args['EarlyPickup'] + $Result['LeaseDuration'] + $Args['LateReturn'] && $Args['LateReturn'] > 0 || $LeaseRow['DayS'] == $Result['LeaseDuration'] && $Args['EarlyPickup'] == 0 ) ){ 
				$LeaseRow['Comment'] .= "<li>Last day to return. Equipment reserved.</li>";
				$LeaseRow['Tags'] .= "return last ";
			}
			if( $LeaseRow['DayS'] == $Result['LeaseDuration'] + $Args['EarlyPickup'] && $Result['LeaseDuration'] > 0){ 
				$LeaseRow['Comment'] .= "<li>Last day of Lease.</li>";
				$LeaseRow['Comment'] .= "<li>Last day to return.</li>";	
				$LeaseRow['Tags'] .= "lease last ";
			}
			if( $LeaseRow['DayS'] > $Args['EarlyPickup'] + $Result['LeaseDuration'] + $Args['LateReturn'] ){
				$LeaseRow['Comment'] .= "<li>Equipment reserved as buffer.</li>";
				$LeaseRow['Tags'] .= "buffer ";
			}

			// Raw and Applied Pricing and sums
			if( count($Args['LeasePriceArray']) > 0 && isset($LeaseRow['DayL']) ) {	
				// First day of lease
				if( $LeaseRow['DayL'] == 0 ) {
					$LeaseRow['RawPrice'] = 0;
					//// Same day return. Find next breakpoint and apply for day.
					if ( $Result['LeaseDuration']  == 0 ) {
					// Find first Breakpoint and apply as applied price
						if( isset($Args['LeasePriceArray'][array_key_first($Args['LeasePriceArray'])]) ) {		
		  						$LeaseRow['AppliedPrice'] = $Args['LeasePriceArray'][array_key_first($Args['LeasePriceArray'])];
		  						$LeaseRow['Tags'] .= "sameday lease first ";
		  						$LeaseRow['Comment'] .= "<li>Returned on the same day, price for 1 day applies.</li>";
		  				} 
		  			}
					else{
						if( isset($Args['LeasePriceArray'][array_key_first($Args['LeasePriceArray'])]) ) {		
		  						$LeaseRow['MinPrice'] = $Args['LeasePriceArray'][array_key_first($Args['LeasePriceArray'])];
		  				}
						$LeaseRow['AppliedPrice'] = 0 ;
						$LeaseRow['Tags'] .= "lease first ";
						$LeaseRow['Comment'] .= "<li>First day of lease.</li>";
						if($Args['EarlyPickup'] <= 0){
							$LeaseRow['Comment'] .= "<li>Earliest possible pickup.</li>";				
						}
					}
				}	
				//// Record matching breakpoint found in price array;
				elseif(array_key_exists($LeaseRow['DayL'], $Args['LeasePriceArray']) ){
					$LeaseRow['RawPrice'] = $Args['LeasePriceArray'][$LeaseRow['DayL']];
					$LeaseRow['AppliedPrice'] = $LeaseRow['RawPrice'];
					$LeaseRow['Tags'] .= 'lease breakpoint ';
					//$LeaseRow['Comment'] .= "<li>Price given by breakpoint</li>";
				


				}
				
				//// No matching breakpoint; find previous breakpoint and apply price. 
				else{
					for ($n = $LeaseRow['DayL'] ; $n >= 0 ; $n-- ){
	  					if( array_key_exists($n, $Args['LeasePriceArray'] ) && !isset($LeaseRow['RawPrice']) ){
	  						$LeaseRow['RawPrice'] = $Args['LeasePriceArray'][$n];
	  						$LeaseRow['AppliedPrice'] = $LeaseRow['RawPrice'];
	  						$LeaseRow['Tags'] .= "lease inherit ";
	  						//$LeaseRow['Comment'] .= "<li>Inherited price.</li>";
				
	  						}
	  				}
	  			}
	  			if(!isset($LeaseRow['RawPrice'])){
					$LeaseRow['Tags'] .= 'free lease';
					$LeaseRow['RawPrice'] = 0 ;
					$LeaseRow['AppliedPrice'] = 0 ;
					$LeaseRow['Comment'] .= "<li>Free day, apparently, since pricing starts later than day 1.</li>";
				}
	  			// Row sum && Applied sum
	  			
	  			$LeaseRow['RawSum'] = $Store['RawSum'] += $LeaseRow['RawPrice'];
				$LeaseRow['AppliedSum'] = $Store['AppliedSum'] += $LeaseRow['AppliedPrice'];
		
				if ($LeaseRow['RawSum'] > $Args['SeasonPrice'] && !$Store['Limit'] ) {
					$LeaseRow['AppliedSum'] = $Args['SeasonPrice'];
					$LeaseRow['Tags'] .= "apply season ";
					if(!$Store['MaxPrice']){
						$LeaseRow['Comment'] .= '<li>The calculated price exceeds the season price. Season Price applied from this day onward.</li>';
						$Store['MaxPrice'] = true;
					}
					else{
						$LeaseRow['MaxPrice'] = $Store['MaxPrice'];
					}
				}
				elseif ( $LeaseRow['DayL'] > $Args['LeaseLimit'] ){
					$LeaseRow['AppliedSum'] = $Args['SeasonPrice'];
					$LeaseRow['Tags'] .= 'apply season ';
					if(!$Store['Limit']){
						$LeaseRow['Comment'] .= '<li>Lease duration is longer than Lease Limit. Season Price applied fomr this day onward.</li>';
						$Store['Limit'] = true;					
					}
					else{
						$LeaseRow['Limit'] = $Store['Limit'];	
					}				
				}
	  			//$LeaseRow['RawSum'] = $LeaseRow['RawPrice'];
	  			//$LeaseRow['Tags'] .= "Sum " . GetLeaseRow($Args, 0)['RawPrice'];
				
			
				//$LeaseRow['LeasePrice'] = abs($Args['LeaseStart'] - $LArgs['LeaseEnd']) / (60 * 60 * 24)
			}
			$LeaseRows[] = $LeaseRow;
			
		}
		if($Sum){
			return $Store;
		}
		else{
			return $LeaseRows;
		}
	}

	//Build and assign Rows
	$LeaseRows = GetLEaseRows($LeaseArgs, $LeaseResults);

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
    		$( document ).tooltip();
  
    		$( "#LeaseStartDatePicker" ).datepicker({
    			dateFormat: "yy-mm-dd",
    			firstDay: 1,
    			minDate: 0,
    			altField: "#LeaseStart",
    			defaultDate: '<?=date('Y-m-d',$LeaseArgs['LeaseStart']);?>'
    		});
    		$( "#LeaseEndDatePicker" ).datepicker({
    			dateFormat: 'yy-mm-dd',
    			firstDay: 1,
   				minDate: 0,
   				altField: "#LeaseEnd",
   				defaultDate: '<?=date('Y-m-d', $LeaseArgs['LeaseEnd']);?>'
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
		<?php 
	if($Options['Debug'] == true) { ?>
	 	<div class="col-md-12">
			<div class="content debug">
				<h2>Debug</h2>
					<div class="row">
						<div class="col-md-4">
							<h3>$_GET</h3>
							<pre>
<?php var_export($_GET);?>
							</pre>
						</div>
						<div class="col-md-4">
							<h3>LeaseArgs </h3>
							<pre>
							
<?php var_export($LeaseArgs) ?>;
							</pre>
						</div>
						<div class="col-md-4">
							<h3>LeaseResults</h3>
							<pre>
<?php var_export($LeaseResults);?>
							</pre>
						</div>
						<div class="col-md-4">
							<h3>Lease rows from $Result</h3>
							<pre>
<?php //var_export($LeaseRows[$LeaseResults['LeaseDuration']]['AppliedSum']);?>

<?php var_export($LeaseRows);?>
							</pre>
						</div>
						
						<div class="col-md-8">
							<h3>Calculation</h3>
							<pre>
								<table class="price_table">
								<thead>  
									<tr>  
										<td>Date</td>
										<td>Day S</td>
										<td>Day L</td>
										<td>Price Raw</td>
										<td>Sum Raw</td>
										<td>Sum applied</td>
										<td>Tags</td>
										<td>MaxPrice</td>
										<td>Limit</td>
										<td>Comment</td>
									</tr>
								</thead> 
								<tbody>
								<?php 
									foreach(GetLeaseRows($LeaseArgs, $LeaseResults) as $key => $value){
										echo "<tr>";
											echo "<td>" . date('D Y-m-d', $value['Date']) . "</td>";
											echo "<td>" . $value['DayS'] . "</td>";
											echo "<td>" . $value['DayL'] . "</td>";
											echo "<td>" . $value['RawPrice'] . "</td>";
											echo "<td>" . $value['RawSum'] . "</td>";
											echo "<td>" . $value['AppliedSum'] . "</td>";
											echo "<td class='comment'>" . $value['Tags'] . "</td>";
											echo "<td>" . $value['MaxPrice'] . "</td>";
											echo "<td>" . $value['Limit'] . "</td>";
											echo "<td class='comment'>" . $value['Comment'] . "</td>";
										echo "</tr>";
									}; 
								?>
								</tbody>
							</table>
						</pre>
					</div> !-->
				</div>
			</div>
		</div>
	<?php } ?>
		<div class="col-md-6">
			<div class="content meta">
				<h1>Proof of concept: Variable pricing</h1>
				<p>This tool aims to demonstrate the suggested method of pricing lease products; which attributes are applied on which level and how they affect the price for the chosen duration, and to some extent stock availability. </p><p> The option to lease equipment the whole season for the season price is still available, but this tool only shows how the price for a lease with a given start and end date would be calculated.</p>
				<h2>Test data presets</h2>
				<ul>
					<li class='test_case'><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=1799&LeaseLimit=60&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=200&other_interval%5B3%5D%5BDay%5D=3&other_interval%5B3%5D%5BPrice%5D=150&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=100&other_interval%5B5%5D%5BDay%5D=5&other_interval%5B5%5D%5BPrice%5D=50&other_interval%5B6%5D%5BDay%5D=6&other_interval%5B6%5D%5BPrice%5D=30&other_interval%5B7%5D%5BDay%5D=7&other_interval%5B7%5D%5BPrice%5D=20&LeaseBuffer=&EarlyPickup=&LateReturn=&LeaseStart=2020-11-20&LeaseEnd=2020-11-30">Suggestion/Example for product 'Senior Medel'</a>
						<p>Applied to a short term lease without any special atrributes for different deadlines.</p></li>
					<li class='test_case'><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=1799&LeaseLimit=30&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=200&other_interval%5B3%5D%5BDay%5D=3&other_interval%5B3%5D%5BPrice%5D=150&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=100&other_interval%5B5%5D%5BDay%5D=5&other_interval%5B5%5D%5BPrice%5D=50&other_interval%5B6%5D%5BDay%5D=6&other_interval%5B6%5D%5BPrice%5D=30&other_interval%5B7%5D%5BDay%5D=7&other_interval%5B7%5D%5BPrice%5D=20&LeaseBuffer=&EarlyPickup=&LateReturn=&LeaseStart=2020-11-20&LeaseEnd=2021-01-31">Senior Medel with lease limit applied.</a><p>The duration of the lease is longer than the supplied <i>Lease limit</i> attribute.</li>
					<li><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=1299&LeaseLimit=60&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=200&other_interval%5B3%5D%5BDay%5D=3&other_interval%5B3%5D%5BPrice%5D=150&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=100&other_interval%5B5%5D%5BDay%5D=5&other_interval%5B5%5D%5BPrice%5D=50&other_interval%5B6%5D%5BDay%5D=6&other_interval%5B6%5D%5BPrice%5D=30&other_interval%5B7%5D%5BDay%5D=7&other_interval%5B7%5D%5BPrice%5D=20&LeaseBuffer=&EarlyPickup=&LateReturn=&LeaseStart=2020-11-20&LeaseEnd=2021-01-31">Senior Medel with max price.</a><p>The calculated sum of the days in the lees exceeds the <i>season price</i> attribute</p></li>
					<li><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=1299&LeaseLimit=60&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=200&other_interval%5B3%5D%5BDay%5D=3&other_interval%5B3%5D%5BPrice%5D=150&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=100&other_interval%5B5%5D%5BDay%5D=5&other_interval%5B5%5D%5BPrice%5D=50&other_interval%5B6%5D%5BDay%5D=6&other_interval%5B6%5D%5BPrice%5D=30&other_interval%5B7%5D%5BDay%5D=7&other_interval%5B7%5D%5BPrice%5D=20&LeaseBuffer=&EarlyPickup=&LateReturn=&LeaseStart=2020-11-20&LeaseEnd=2020-11-20">Senior Medel with zero-day duration.</a><p>The equipment will be returned the same day it is picked up.</p></li>
					<li><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=1299&LeaseLimit=60&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=200&other_interval%5B3%5D%5BDay%5D=3&other_interval%5B3%5D%5BPrice%5D=150&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=100&other_interval%5B5%5D%5BDay%5D=5&other_interval%5B5%5D%5BPrice%5D=50&other_interval%5B6%5D%5BDay%5D=6&other_interval%5B6%5D%5BPrice%5D=30&other_interval%5B7%5D%5BDay%5D=7&other_interval%5B7%5D%5BPrice%5D=20&LeaseBuffer=1&EarlyPickup=3&LateReturn=2&LeaseStart=2020-11-20&LeaseEnd=2020-11-30">Senior Medel short term with reprives</a><p>Different values for pickup date, return date, and lease buffer, illustrates how a store could work to even out the load over a period where many customers would otherwise pickup, and return, equipment on the same day. Also a demonstration of how lease buffer can be used to make up for likely late returns. </p></li>
					<li><a href="https://gist.legofarmen.se/leasepricetest/?SeasonPrice=5000&LeaseLimit=70&other_interval%5B1%5D%5BDay%5D=1&other_interval%5B1%5D%5BPrice%5D=249&other_interval%5B2%5D%5BDay%5D=2&other_interval%5B2%5D%5BPrice%5D=30&other_interval%5B4%5D%5BDay%5D=4&other_interval%5B4%5D%5BPrice%5D=150&other_interval%5B8%5D%5BDay%5D=8&other_interval%5B8%5D%5BPrice%5D=102&other_interval%5B16%5D%5BDay%5D=16&other_interval%5B16%5D%5BPrice%5D=50&other_interval%5B32%5D%5BDay%5D=32&other_interval%5B32%5D%5BPrice%5D=20&LeaseBuffer=3&EarlyPickup=3&LateReturn=2&LeaseStart=2020-11-20&LeaseEnd=2021-01-15">Product with disparate price ranges.</a><p>An example of a more complex array of prices.</p></li>
					<li><a href="https://gist.legofarmen.se/leasepricetest/">Empty forms</a></li>
				</ul><br />
				
				<div class="row">
					<div class="col-md-9"><input name="debug" type="checkbox" <?= ($_GET['debug']) ? 'checked' : null ; ?>/><label for="debug">Show debugging info</label></div>
				</div>
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
						<input type='number' name='SeasonPrice' required value='<?= $LeaseArgs['SeasonPrice'] ?>'/>
					</div>
					<hr />
					<div class="col-lg-9">						
						<label for="LeaseLimit">Lease limit</label>			
						<p>The number of days after which the price in no longer calculated from the Lease price array, and Season lease price is used instead.</p>
					</div>
					<div class="col-lg-3">
						<input type='number' name='LeaseLimit' required value="<?= $LeaseArgs['LeaseLimit'] ?>">	
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
						// No arguments from form submit
						if(!isset($LeaseArgs['LeasePriceArray']) or count($LeaseArgs['LeasePriceArray']) < 0) {
						    echo "<div id='another-interval[0]' class='row'>";
							    echo "<div class='col-6'>";
							    	echo "<input type='number' required name='other_interval[0][Day]' placeholder='Day' value='' />";
							    echo "</div>";
							    echo "<div class='col-6'>";
							    	echo "<input type='number' required name='other_interval[0][Price]' placeholder='Price' value='' />";
							   	echo "</div>";
							echo "</div>";
						}
						// Arguments from form submit
						else{
							foreach ($LeaseArgs['LeasePriceArray'] as $Day => $Price) {
								//var_export($Args['LeasePriceArray']);
								if( !empty($Day) || !empty($Price)){
									echo '<div id="another-interval[' . $Day . ']" class="row">';
							    		echo '<div class="col-6">';
							    			echo '<input type="number" required name="other_interval[' . $Day . '][Day]" placeholder="Day" value="' . $Day .'" />';
							    		echo "</div>";
							    		echo '<div class="col-6">';
							    			echo '<input type="number" required name="other_interval[' . $Day . '][Price]" placeholder="Price" value="' . $Price  .'" />';
							    		echo "</div>";
							    	echo  "</div>";
					    	 	}
							}   	
						}
						?>
						<button type="button" id="add-interval">+ Add breakpoint</button>';			
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
						<input type='number' name='LeaseBuffer' value="<?= $LeaseArgs['LeaseBuffer'];?>">	
					</div>
					<hr />  
					<div class="col-lg-9">						
						<label for="EarlyPickup">Early pickup</label>			
						<p>A number of days before the lease start during which the equipment is reserved and can be picked up, without affecting the lease price.</p>
					</div>
					<div class="col-lg-3">	
						<input type='number' name='EarlyPickup' value="<?= $LeaseArgs['EarlyPickup']; ?>">	
					</div>
					<hr />
					<div class="col-lg-9">						
						<label for="LateReturn">Late return</label>
						<p>A number of days after the lease end during which the equipment is reserved an can be returned, without affecting the lease price. </p>
					</div>	
					<div class="col-lg-3">	
						<input type='number' name='LateReturn' value="<?=$LeaseArgs['LateReturn'];?>">	
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

<?php 

// End of Input
// Start of Result

if(!empty($_GET)){ // Output results ?>
	
 	<div class="row main">
		<div class="col-md-6">	
			<div class="content">
				<h2>Result</h2>
			</pre>
			<div class="row">
				<hr />
				<?php if( $LeaseArgs['EarlyPickup'] > 0 ){ ?>
				<div class="col-md-6">
					<span class="label">Earliest pickup date</span>
				</div>
				<div class="col-md-6">
					<span class="value"><?=date("D Y-m-d", $LeaseResults['PickupDate']);?></span>
				</div>
			 	<div class="col-md-12">
			 		<p>The local store product attribute <i>Early pickup</i> allows for the equipment to be picked up <b><?=$LeaseArgs['EarlyPickup'];?> day(s)</b> in advance, before the actual lease starts. In effect this means the get to have the equipment <b><?=$LeaseArgs['EarlyPickup'];?> day(s)</b> for free.</p><p> The equipment is reserved and packed from this date.</p>
			 	</div>
			 	<hr />
			 	<?php } ?><div class="col-md-6">
					<span class="label">Lease start</span>
				</div>
				<div class="col-md-6">
		 		<span class="value"><?=date("D Y-m-d", $LeaseArgs['LeaseStart']);?></span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>This is the first day of the lease, and first day the equipment can be picked up, unless an eralier pickup date is offered by the local store product attribute <i>'Early Pickup'</i>. Regardless, this is the first day the customer pays for.</p>
			 	</div>
			 	<hr />
			 	<div class="col-md-6">
					<span class="label">Lease end</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?=date("D Y-m-d", $LeaseArgs['LeaseEnd']);?></span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>This is the last day of the lease, and the last the equipment can to be returned, unless the deadline has been pushed by the local store product attribute <i>'Late return'</i>. Regardless, this is the last day the customer pays for.</p>
			 	</div>
			 	<hr />
			 	<?php if( $LeaseArgs['LateReturn'] > 0 ){ ?>
				<div class="col-md-6">
					<span class="label">Latest return date</span>
				</div>
				<div class="col-md-6">
					<span class="value"><?=date("D Y-m-d", $LeaseResults['ReturnDate']);?></span>
				</div>
			 	<div class="col-md-12">
			 		<p>The local store product attribute <i>Late return</i> allows for the equipment to be returned <b><?= $LeaseArgs['LateReturn']?> day(s)</b> later, after the actual lease ends. In effect this means the get to have the equipment <b><?= $LeaseArgs['LateReturn'] ;?> day(s)</b> for free. </p>The equipment is reserved up to and including this date.</p>
			 	</div>
			 	<hr />
			 	<?php } ?>
			 	<div class="col-md-6">
					<span class="label">Total lease price</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= AddCur($LeaseRows[$LeaseResults['LeaseDuration']]['AppliedSum']);
					/* ?= GetLeaseRows( $LeaseArgs, GetLEaseResults( $LeaseArgs ))[GetLEaseResults( $LeaseArgs )]['LeaseDuration']['AppliedSum'] ; ?>:- */ ?> </span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The total cost of the lease calculated from the number of days in the lease and the lease price breakpoints for the product. </p>
			 		<?php
			 		 if($LeaseRows[$LeaseResults['LeaseDuration']]['MaxPrice']){
			 			echo "<p class='info'>The calculated price exceeds the season price and as the season price is the maximum cost of any lease, regardless of lease length, season price is applied.</p>";
			 		}
			 		if( $LeaseRows[$LeaseResults['LeaseDuration']]['Limit']){
			 			echo "<p class='info'>The duration of the lease is longer than the limit attribute for the product, meaning the calculated price is ignored and the season price is applied.</p>";
			 		}
					if( $LeaseResults['LeaseDuration'] == 0 ){
			 			echo "<p class='info'>The Lease duration is less than one day, meaning the equipment is returned the same they it is picked up. Since the cost of leasing one day is the minimum cost for any lease, the cost of one day is applied.</p>";
			 		}
			 		?>
			 	</div>
			 	<hr />
			 	<div class="col-md-6">
					<span class="label">Lease length</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= $LeaseResults['LeaseDuration']; ?> days</span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The number of days in the lease on which the lease price is calculated. A lease can have length of zero, meaning the equipment is returned the same day it is picked up.</p>
			 	</div>
			 	<hr />
			 	<?php if( $LeaseResults['LeaseDuration'] != $LeaseResults['ReservationDuration'] ) { ?>
				<div class="col-md-6">
					<span class="label">Reservation length</span>
				</div>
				<div class="col-md-6">	
			 		<span class="value"><?= $LeaseResults['ReservationDuration']; ?> days</span>
			 	</div>
			 	<div class="col-md-12">
			 		<p>The number of days during which the equipment will be reserved, due to the combination of the Lease start, Lease End, Early pickup, Late return, and Lease buffer attributes. The equipment will be reserved from <b><?= date('D Y-m-d', $LeaseResults['PickupDate']); ?></b> to <b><?= date('D Y-m-d', $LeaseResults['ReservationEnd']); ?></b>.</p>
			 	</div>
			 	<hr />
			 	<?php } ?>
			 </div>
		</div>
	</div>

	<div class="col-md-6">
		<div class="content">
			<h2>Lease calculation</h2>
			<p>This is how the lease and the different dates are calculated. Hover the column head for further details</p>
			<table class="lease_table">
				<thead>  
					<tr>  
						<td>Date</td> 
						<td class='tooltiped' title='The nth day in the total sequence'>Day(S)</td>
						<td class='tooltiped' title='The nth day in the lease'>Day(L)</td>
						<td class='tooltiped' title='The price per day applied to this day in the lease. Corresponding breakpoints are shown as bold, other prices are inherited from earlier breakpoint.'>Price</td>
						<td class='tooltiped' title='Accumulated sum up to this day in the lease. This is the applied sum which means it can be overriden by other values in certain conditons.'>Sum</td>
						<td>Comment</td>
					</tr>
				</thead> 
				<tbody>		
					<?php  
					foreach(GetLeaseRows($LeaseArgs, $LeaseResults) as $Key => $Row){
						echo "<tr class='" . $Row['Tags'] . "'>";
							echo "<td> " . date('D Y-m-d', $Row['Date']) . "</td>";
							echo "<td>" . $Row['DayS'] . "</td>";
							echo "<td>" . $Row['DayL'] . "</td>";
							echo "<td class='" . $Row['Tags'] . "'>" . AddCur($Row['RawPrice']) . "</td>";
							echo "<td>" . AddCur($Row['AppliedSum']) . "</td>";
							echo "<td class='comment'><ul>" . $Row['Comment'] . "</ul></td>";
						echo "</tr>";
						} 
					?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="col-md-6">
		<div class="content">
			<h2>Price table</h2>
			<p>This is the raw cost table for the product, regardless of lease details, with the supplied product attributes applied.</p><p> The table show both raw and applied prices and is limited by both the <i>lease limit</i> and the <i>season (max) price</i> attrributes. 
			<table class="price_table">
				<thead>  
					<tr>  
						<td class='tooltiped' title='The nth day in the lease'>Day(L)</td>
						<td class='tooltiped' title='The price per day applied to this day in the lease. Corresponding breakpoints are shown as bold, other prices are inherited from earlier breakpoint.'>Price</td>
						<td class='tooltiped' title='The accumulated sum up to given day without taking in to account any limits in duration or cost.'>Raw Sum</td>
						<td class='tooltiped' title='Accumulated sum up to given day in the lease. This is the applied sum which means it can be overriden by other values in certain conditons.'>Applied Sum</td>
						<td>Comment</td>
					</tr>
				</thead> 
				<tbody>		
					<?php  
					$TableArgs = array();
					$TableResults = $LeaseResults;
					$TableResults['LeaseDuration'] = $LeaseArgs['LeaseLimit'] + $LeaseArgs['EarlyPickup'] ;
					$TableResults['ReservationDuration'] = $LeaseArgs['LeaseLimit'] + $LeaseArgs['EarlyPickup'] ;
					/*
						'PickupDate' => $TableArgs['LeaseStart'] - Unixtime($TableArgs['EarlyPickup']),
						'ReturnDate' => $TableArgs['LeaseEnd'] + UnixTime($TableArgs['LateReturn']),
						'ReservationEnd' => $TableArgs['LeaseEnd'] + UnixTime($TableArgs['LateReturn']) + UnixTime($TableArgs['LeaseBuffer'])
					);*/	
					foreach(GetLeaseRows($LeaseArgs, $TableResults) as $Key => $Row){
						if ( isset($Row['DayL']) && !$Row['MaxPrice']) {
							
							if( $Row['DayL'] == 0){
								$Row['AppliedSum'] =  $Row['MinPrice'];
								$Row['Comment'] = '</li><li>Applies to sameday returns. Does not add to the sum.</li>';
							}
							else{
								$Row['Comment'] = '';
							}
							if( $Row['AppliedSum'] >= $LeaseArgs['SeasonPrice']){
								$ShowMax = true;
							}
							if( $Row['DayL'] >= $LeaseArgs['LeaseLimit']){
								$ShowLimit = true; 
							}

							echo "<tr class=''>";
								echo "<td>" . $Row['DayL'] . "</td>";
								echo "<td class='" . $Row['Tags'] . "'>" . AddCur($Row['RawPrice']) . "</td>";
								echo "<td>" . AddCur($Row['RawSum']) . "</td>";
								echo "<td>" . AddCur($Row['AppliedSum']) . "</td>";
								echo "<td class='comment'><ul>" . $Row['Comment'] . "</ul></td>";
							echo "</tr>";
						$PriceRows = $Row['DayL'];
						} 
						
					}
					?>
				</tbody>
			</table>
			<?php 
			if( isset($ShowMax) ){
	 			echo "<p class='info'>Since the calculated price exceeds the season price, and as the season price is the maximum cost of any lease, regardless of lease length, season price is applied for any lease longer " . $PriceRows . " days.</p>";
	 		}
	 		if( isset($ShowLimit) ){
	 			echo "<p class='info'>Due to the lease limit attribute, the season price is applied to any lease longer than " . $LeaseArgs['LeaseLimit'] . " days.";
	 		}
	 		?>
		</div>
	</div>
<?php } ?>
</body>
</html>
