<?php
//Validate that this request came from the correct/authorized IP address
$check_ip = gethostbyname('dbunyard.homeip.net'); //Using DynDNS to ensure this connection comes from the correct IP address
$connect_ip = $_SERVER['REMOTE_ADDR'];
if ($check_ip <> $connect_ip) {
	die ('GTFO!'); //The addresses do not match so we die.
} //end if ($check_ip <> $connect_ip) {

//Connection from valid IP address is detected - continue

$number_sensors = 8; //The number of temperature sensors
$array_size = $number_sensors - 1; //Arrays start at 0
$sensor_array = array(); //For storing of sensor data

//Loop through getting all the sensor data
$i = 0;
while ($i <= $array_size) {
	//Eliminate any invalid data from the reading
	$sensor_array[$i]['reading'] = preg_replace("/[^0-9\-.]/", "", $_GET["t$i"]);
	//Verify that we have a valid reading, die if invalid
	if ((!is_numeric($sensor_array[$i]['reading'])) OR ($sensor_array[$i]['reading']=='')) {
		die ("temp$i is not numeric or is null!");
	} //end if ((!is_numeric($sensor_array[$i]['reading'])) OR ($sensor_array[$i]['reading']=='')) {
	//Convert to farenheit
	$sensor_array[$i]['reading'] = ($sensor_array[$i]['reading'] * 1.8) + 32;
	$i++;
} //end while ($i <= $array_size) {

//Get the power status and verify it's valid
$power = $_GET['p'];
if ((!is_numeric($power)) OR ($power=='')) {
	die ('power status is not numeric or is null!');
} //end if ((!is_numeric($power)) OR ($power=='')) {

//Make the date/time for insertion
$datetime = date("Y-m-d H:i:s");

//Connect to the MySQL server
require_once ('lib/dbconfig.php');
require_once ('lib/dbconnect.php');

//Require the functions file
require_once ('lib/functions.php');

//TEMP HUMIDITY STUFF!!!
//Eliminate any invalid data from the reading
$sensor_array[3]['humidity']['reading'] = preg_replace("/[^0-9\-.]/", "", $_GET["h3"]);
//Verify that we have a valid reading, die if invalid
if ((!is_numeric($sensor_array[3]['humidity']['reading'])) OR ($sensor_array[3]['humidity']['reading']=='')) {
	die ("humidity3 is not numeric or is null!");
} //end if ((!is_numeric($sensor_array[5]['humidity']['reading'])) OR ($sensor_array[5]['humidity']['reading']=='')) {
//Insert all readings into the database
$sql = "INSERT INTO `raw_humidity` (`sensor`, `reading`, `datetime`) VALUES ('3', '".$sensor_array[3]['humidity']['reading']."', '$datetime')";
mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . '<br>Line: '.__LINE__ .'<br>File: '.__FILE__);

//See if we notify on invalid read or outside range
$notify_invalid = get_config('notify_on_invalid', $conn);
$notify_outside_range = get_config('notify_on_outside_range', $conn);

//Insert all readings into the database
$i = 0;
$rrd_string = '';
while ($i <= $array_size) {
	//Ensure that the temperature is valid
	if (($sensor_array[$i]['reading']>-100.00) AND ($sensor_array[$i]['reading']<180.00)) {
		$sql = "INSERT INTO `raw_temperatures` (`sensor`, `reading`, `datetime`) VALUES ('$i', '".$sensor_array[$i]['reading']."', '$datetime')";
		mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . '<br>Line: '.__LINE__ .'<br>File: '.__FILE__);
	
		//See if notifications are enabled
		$temp_alert = get_alert($i, 'temp', $conn);
		if (($notify_outside_range == 1) AND ($temp_alert == 1)) {
			//Check if we are within range and haven't notified during the last interval
			$within_range = check_temps($i, $sensor_array[$i]['reading'], $conn);
			if ($within_range == 0) {
				//Reading was outside specified range, check if we have notified lately
				$renotify_time = check_last_notify_time($i, 'temp', $conn);
				if ($renotify_time == 1) {
					//Get the location of this sensor
					$location = get_sensor_location($i, $conn);
					//We need to notify now that we are outside the range
					$subject = "Reading was outside range for $location!";
					$body = "The reading from $location was outside the range specified.\n".
							"The reading was: ".$sensor_array[$i]['reading']." F\r\n".
							"You should check on this ASAP!\r\n".
							"The datestamp is: ".$datetime.".\r\n";
					email_alert($subject, $body, $conn);
					set_last_notify_time($i, 'temp', $conn);
				} //end if ($renotify_time == 1) {
			} //end if ($within_range == 0) {
		} //end if (($notify_outside_range == 1) AND ($temp_alert == 1)) {
	}else{
		//Notify on invalid read if enabled
		if ($notify_invalid == 1) {
			//Check if invalid alerting is enabled for this sensor
			$notify_invalid_sensor = get_alert($i, 'invalid', $conn);
			if ($notify_invalid_sensor == 1) {
				//Get the location of this sensor
				$location = get_sensor_location($i, $conn);
				$subject = "Invalid reading from $location!";
				$body = "An invalid reading has been detected from $location.\r\n".
						"The reading was: ".$sensor_array[$i]['reading']." F\r\n".
						"You should check on this ASAP!\r\n".
						"The datestamp is: ".$datetime.".\r\n";
				email_alert($subject, $body, $conn);
				$sensor_array[$i]['reading'] = "U"; //Update this to unknown for the RRD data
			} //end if ($notify_invalid_sensor == 1) {
		} //end if ($notify_invalid == 1) {
	} //end if (($sensor_array[$i]['reading']>-100.00) AND ($sensor_array[$i]['reading']<180.00)) {
	$rrd_string = $rrd_string . ':' . $sensor_array[$i]['reading']; //Update the rrd string with the sensor data
	$i++;
} //end while ($i <= $array_size) {

//The SQL satement to insert the power status into the database
$sql = "INSERT INTO `raw_power` (`status`, `datetime`) VALUES ('$power', '$datetime')";
mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . '<br>Line: '.__LINE__ .'<br>File: '.__FILE__);

//Verify that the power state hasn't changed
power_check($power, $conn);

//Close the database connection
require_once ('lib/dbclose.php');

//Update the RRD data
$now = time(); //Current time
$rrd_string = $now . $rrd_string; //Append the current time to the string
$rrd_string = $rrd_string . ":00:00:00:".$sensor_array[3]['humidity']['reading'].":00:00:00:00"; //Append the humidity information
//$rrd_string = $rrd_string . ":00:00:00:00:00:00:00:00"; //No humidity data currently, append all zeros
rrd_update("rrd/data.rrd", array($rrd_string)); //Update the RRD data file

//For troubleshooting
//file_put_contents("rrd/temp.txt", $rrd_string);

//Mainly for testing - ensure the script finished
echo ("Done!");
?>
