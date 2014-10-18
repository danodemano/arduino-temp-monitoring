<?php
$conn = mysqli_connect($dbhost, $dbuser, $dbpass) or die('Error connecting to MySQL');
mysqli_select_db($conn, $dbname) or die('Unable to select database.');
?>
