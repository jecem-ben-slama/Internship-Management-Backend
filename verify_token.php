<?php
// BACKEND/verify_token.php
// Function to verify a JWT token sent in the Authorization header.

require_once 'vendor/autoload.php'; // Composer's autoloader
require_once 'config.php';       // Your JWT secret key config

use Firebase\JWT\JWT;
use Firebase\JWT\Key; // Required for symmetric algorithms like HS256
use Firebase\JWT\ExpiredException;      // Exception for expired tokens
use Firebase\JWT\SignatureInvalidException; // Exception for invalid signatures
use Firebase\JWT\BeforeValidException;  // Exception for 'nbf' or 'iat' in the future

/**
 * Verifies the JWT token from the Authorization header.
 * Exits the script with an error response if verification fails.
 *
 * @return array Decoded user data from the token payload.
 */

function verifyJwtToken() {
    error_log("--- verifyJwtToken START ---"); // Debugging

    $headers = apache_request_headers(); 
    error_log("Apache Request Headers: " . print_r($headers, true)); // Debugging

    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
    
    // Fallback if apache_request_headers() doesn't work (useful for non-Apache servers or specific configurations)
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        error_log("Using \$ _SERVER['HTTP_AUTHORIZATION']: " . $authHeader); // Debugging
    }
    
    error_log("Auth Header found: " . ($authHeader ? 'YES' : 'NO')); // Debugging

    if (!$authHeader) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing.']);
        error_log("Error: Authorization header missing."); // Debugging
        exit();
    }

    list($jwt) = sscanf($authHeader, 'Bearer %s'); 
    error_log("Extracted JWT: " . ($jwt ?: 'NULL/Empty')); // Debugging

    if (empty($jwt)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Bearer token missing or malformed in Authorization header.']);
        error_log("Error: Bearer token missing or malformed."); // Debugging
        exit();
    }
    
    // Ensure the secret key is defined and not empty
    if (!defined('JWT_SECRET_KEY') || empty(JWT_SECRET_KEY)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: JWT Secret Key not defined.']);
        error_log("FATAL Error: JWT_SECRET_KEY is not defined or empty!"); // Debugging
        exit();
    }
    error_log("JWT_SECRET_KEY value: " . JWT_SECRET_KEY); // Debugging

    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
        error_log("Token successfully decoded."); // Debugging
        return (array) $decoded->data; 

    } catch (ExpiredException $e) {
        error_log("JWT Error: Token Expired - " . $e->getMessage()); // Debugging
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token has expired. Please log in again.']);
        exit();
    } catch (SignatureInvalidException $e) {
        error_log("JWT Error: Invalid Signature - " . $e->getMessage()); // Debugging
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token signature.']);
        exit();
    } // ... rest of your catch blocks ...
    catch (Exception $e) {
        error_log("JWT Unexpected Error: " . $e->getMessage()); // Debugging
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred during token verification: ' . $e->getMessage()]);
        exit();
    } finally {
        error_log("--- verifyJwtToken END ---"); // Debugging
    }
}
?>