<?php
// BACKEND/config.php

// Define a strong, random, and confidential secret key for JWT signing.
// IMPORTANT: REPLACE THIS STRING with a truly random and complex string (e.g., 32 characters or more).
define('JWT_SECRET_KEY','15879432874563201566489523014552'); 

// Define the expiration time for your tokens in seconds.
// For example, 3600 seconds = 1 hour. Adjust as needed.
define('JWT_EXPIRATION_SECONDS', 604800); // Token valid for 3 hour
?>