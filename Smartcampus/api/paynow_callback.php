<?php
/**
 * Pay Now Zimbabwe Payment Callback Handler
 * This endpoint receives payment status updates from Pay Now
 */

include '../includes/config.php';
include '../includes/paynow.php';

// Get callback data
$callback_data = $_POST;

// Log callback for debugging
error_log('Pay Now Callback: ' . json_encode($callback_data));

// Verify callback authenticity
$paynow = new PayNowZimbabwe();

if (!$paynow->verifyCallback($callback_data)) {
    error_log('Pay Now Callback: Invalid hash');
    http_response_code(400);
    echo 'INVALID HASH';
    exit();
}

// Process the callback
$result = $paynow->processCallback($callback_data);

if ($result['success']) {
    error_log('Pay Now Callback: Payment processed successfully - Status: ' . $result['status']);
    echo 'OK';
} else {
    error_log('Pay Now Callback: Payment processing failed - ' . $result['message']);
    http_response_code(400);
    echo 'ERROR';
}
?>