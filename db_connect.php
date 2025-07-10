<?php
// db_connect.php

// Define database connection parameters
// If you are using XAMPP/WAMP on your local machine, 'localhost' and 'root' are common defaults.
define('DB_SERVER', 'localhost'); // Your database server address (e.g., 'localhost' or an IP)
define('DB_USERNAME', 'root');   // Your database username
define('DB_PASSWORD', '');       // Your database password (often empty for 'root' on local setups)
define('DB_NAME', 'pfe'); // The name of your database (as per your SQL schema)

// Attempt to establish a database connection using MySQLi
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check if the connection was successful
if ($mysqli->connect_errno) {
    // If connection fails, output an error and terminate the script
    // In a production environment, you would log this error and return a generic error message.
    die("ERROR: Could not connect to the database. " . $mysqli->connect_error);
}

// Set character set to UTF-8 for proper handling of special characters
$mysqli->set_charset("utf8mb4");

?>