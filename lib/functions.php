<?php
//Function to get configs from the database
//********************************************************************************
//Inputs:
//$config - The configuration value to get
//$default - The default to return if the config isn't found
//********************************************************************************
//Returns the value or the default if the config isn't found in the database
function get_config($config, $conn, $default=0) {
	//Define the $conn variable as global
	//global $conn;

	//The query string
	$query = "SELECT `value` FROM `config` WHERE `setting`='$config'";
	
	//Run the query to get the value
	$res = mysqli_query($conn, $query) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
	
	//If the query returned a result, we will use that
	//Otherwise we have to return the default
	if(mysqli_num_rows($res) <> 0) {
		//Use the value from the query
		$array = mysqli_fetch_array($res);
		$return_value = $array[0];
	}else{
		//Use the default
		$return_value = $default;
	} //end if(mysqli_num_rows($result) <> 0) {
	return $return_value;
} //end function get_config($config, $default=0) {

//Function to get alert settings from the temperature table
//********************************************************************************
//Inputs:
//$sensor - The sensor to get the value from
//$type - The type, either temp or invalid
//$default - The default to return if the config isn't found
//********************************************************************************
//Returns the value or the default if the config isn't found in the database
function get_alert($sensor, $type, $conn, $default=0) {
	//The query string
	$query = "SELECT `$type"."_alert` FROM `sensors` WHERE `id`='$sensor'";
	
	//Run the query to get the value
	$res = mysqli_query($conn, $query) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
	
	//If the query returned a result, we will use that
	//Otherwise we have to return the default
	if(mysqli_num_rows($res) <> 0) {
		//Use the value from the query
		$array = mysqli_fetch_array($res);
		$return_value = $array[0];
	}else{
		//Use the default
		$return_value = $default;
	} //end if(mysqli_num_rows($result) <> 0) {
	return $return_value;
}//end function get_alert($sensor, $type, $conn, $default=0) {

//Function to get control data from the database
//********************************************************************************
//Inputs:
//$control - The control value to get
//********************************************************************************
//Returns the value or 'unknown' if the config isn't found in the database
function get_control($control, $conn) {
	//The query string
	$query = "SELECT `value` FROM `control` WHERE `field`='$control'";
	
	//Run the query to get the value
	$res = mysqli_query($conn, $query) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
	
	//If the query returned a result, we will use that
	//Otherwise we have to return unknown
	if(mysqli_num_rows($res) <> 0) {
		//Use the value from the query
		$array = mysqli_fetch_array($res);
		$return_value = $array[0];
	}else{
		//We cannot determine the value, return unknown
		$return_value = 'unknown';
	} //end if(mysqli_num_rows($result) <> 0) {
	return $return_value;
} //end function get_control($control, $conn) {

//Function to check if temp is within range based on the current time
//********************************************************************************
//Inputs:
//$sensor - The sensor we are checking
//$temp - The current temperature for this sensor
//********************************************************************************
//Returns a 1 if within range or a 0 if out of range
function check_temps($sensor, $temp, $conn){
	//The current time to compare to
	$time = date("H:i:s");
	
	//Create and run the SQL statement
	$sql = "SELECT `max`, `min` FROM `times` WHERE `sensor`='$sensor' AND `enabled`=1 AND ('$time'>=`starttime` OR '$time'<=`endtime`)";
	$res = mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
	
	//Make sure we have a result
	if(mysqli_num_rows($res) <> 0) {
		//Get the data from the query
		$array = mysqli_fetch_array($res);
		$max = $array[0];
		$min = $array[1];
		
		//Compare the temps and check if the passed value falls between the min and max
		if(($temp>=$min) and ($temp<=$max)) {
			$return_value = 1;
		}else{
			$return_value = 0;
		} //end if(($temp>=$min) and ($temp<=$max)) {
	}else{
		//If no result we assume true as to not trigger an email alert
		$return_value = 1;
	} // end if(mysqli_num_rows($res) <> 0) {
	
	return $return_value;
} //end function check_temps($sensor, $temp, $conn){

//Function to set control data in the database
//********************************************************************************
//Inputs:
//$field - The control value to set
//$value - The value to set it to
//********************************************************************************
//There is nothing returned
function set_control($field, $value, $conn) {
	//Simple query to set the control value in the database
	//You should be able to figure this one out without any help....
	$sql = "UPDATE `control` SET `value`='$value' WHERE `field`='$field'";
	mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
} //end function set_control($field, $value) {

//Function to check the last notification time for a sensor and type
//********************************************************************************
//Inputs:
//$sensor - The sensor we are checking
//$type - The type we are checking, either temp or invalid
//********************************************************************************
//Return a 1 if past renotify time or a 0 if not past renotify time
function check_last_notify_time($sensor, $type, $conn) {
	$sql = "SELECT `datetime` FROM `last_alert` WHERE `sensor`='$sensor' AND `type`='$type'";
	$res = mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
	
	//Tracking if result from database
	$check = 0;
	
	//Make sure we have a result
	if(mysqli_num_rows($res) <> 0) {
		$check = 1;
		$array = mysqli_fetch_array($res);
		$datetime = $array[0];
	}else{
		$check = 0;
		set_last_notify_time($sensor, $type, $conn, true);
	} //end if(mysqli_num_rows($res) <> 0) {
	
	//Only continue if we have a database result
	if ($check == 1) {
		//Get the re-notify interval
		$interval = get_config('renotify_interval', $conn);
		
		//Check if current time is greater than re-notify interval
		$check_time = date('Y-m-d H:i:s', strtotime("+$interval minutes", strtotime($datetime)));
		$now = date('Y-m-d H:i:s');
		if ($now >= $check_time) {
			$return_value = 1;
		}else{
			$return_value = 0;
		} //end if ($now >= $check_time) {
	}else{
		$return_value = 0;
	} //end if ($check == 1) {
	
	return $return_value;
} //end function check_last_notify_time($sensor, $type, $conn) {

//Function to set the last notification time
//********************************************************************************
//Inputs:
//$sensor - The sensor we are checking
//$type - The type we are checking, either temp or invalid
//$insert - Defaults to false, used if creating a new entry in the table
//********************************************************************************
//There is nothing returned
function set_last_notify_time($sensor, $type, $conn, $insert=false) {
	//The current datetime for inserting/updating the database
	$now = date('Y-m-d H:i:s');
	
	//The query changes depending on if it's an insert or an update
	if($insert==true) {
		$sql = "INSERT INTO `last_alert` (`sensor`, `type`, `datetime`) VALUES ('$sensor', '$type', '$now')";
	}else{
		$sql = "UPDATE `last_alert` SET `datetime`='$now' WHERE `sensor`='$sensor' AND `type`='$type'";
	} //end if($insert==true) {
	
	//Run the query
	mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);
} //end function set_last_notify_time($sensor, $type, $conn) {

//Function to check if the power state has changed
//********************************************************************************
//Inputs:
//$sensor - The current power state
//********************************************************************************
//There is nothing returned
function power_check($state, $conn) {
	//Get the last power state
	$last_state = get_control('last_power_state', $conn);
	
	//The datetime used for stamping the emails
	$datetime = date("Y-m-d H:i:s");
	
	//Compare the last power state to the current state
	if ($last_state>$state) {
		//Power was lost, send email notification
		$subject = "AC power was lost!";
		$body = "An AC power outage has been detected.\r\n".
				"You should check on this ASAP!\r\n".
				"The datestamp is: ".$datetime.".\r\n";
		email_alert($subject, $body, $conn);
		
		//Set the control value for checking next time
		set_control('last_power_state', 0, $conn);
	} elseif ($state>$last_state) {
		//Power was restored, send email notification
		$subject = "AC power was restored!";
		$body = "AC power has been restored.\r\n".
				"No intervention should be required at this time.\r\n".
				"The datestamp is: ".$datetime.".\r\n";
		email_alert($subject, $body, $conn);
		
		//Set the control value for checking next time
		set_control('last_power_state', 1, $conn);
	} elseif ($state<>$last_state) {
		//Something odd has happend...add a notification option here at a later date
	} //end if ($last_state>$state) {
} //end function power_check($state, $conn) {

function get_sensor_location($sensor, $conn) {
	$sql = "SELECT `short_description` FROM `sensors` WHERE `id`='$sensor'";
	$res = mysqli_query($conn, $sql) or die('Error, query failed. ' . mysqli_error($conn) . "<br>Line: ".__LINE__ ."<br>File: ".__FILE__);

	//Make sure we have a result
	if(mysqli_num_rows($res) <> 0) {
		//Get the location
		$array = mysqli_fetch_array($res);
		$location = $array[0];
	}else{
		//No result means location is unknown
		$location = 'unknown';
	} //end if(mysqli_num_rows($res) <> 0) {

	//Return the location
	return $location;
} //end function get_sensor_location($sensor, $conn) {

//Function to send email alerts
//********************************************************************************
//Inputs:
//$subject - The subject of the email message
//$body - The body of the email message
//$importance - The importance of the message.  Default is 1 (high)
//$email_to - The email address the message is going to.  Default is the admin.
//$email_from - The email address the message is coming from.  Default is arduino.
//********************************************************************************
//There is nothing returned
function email_alert($subject, $body, $conn, $importance=1, $email_to='admin', $email_from='arduino') {
	//Make sure we send to the correct address	
	if ($email_to<>'admin') {
		$to = $email_to;
	}else{
		$to = get_config('admin_email', $conn);
	}//end if ($email_to<>'admin') {

	//Make sure we send from the correct address
	if ($email_from<>'arduino') {
		$from = $email_from;
	}else{
		$from = get_config('from_email', $conn);
	}//end if ($email_from<>'arduino') {

	//Creates the proper headers for the email message
	$headers = "From: $from\nReply-To: $from\nX-Mailer: PHP/" . phpversion() . "\nX-Priority: $importance";

	//Send the email message
	mail($to, $subject, $body, $headers);
} //end function email_alert($subject, $body, $importance=3, $email_to='admin', $email_from='arduino') {
?>
