<?php
/**
 * Pay Now Zimbabwe Payment Integration
 * Handles payment initiation and processing
 */

class PayNowZimbabwe {
    private $integration_id;
    private $integration_key;
    private $return_url;
    private $result_url;
    private $sandbox;

    public function __construct() {
        $this->integration_id = PAYNOW_INTEGRATION_ID;
        $this->integration_key = PAYNOW_INTEGRATION_KEY;
        $this->return_url = PAYNOW_RETURN_URL;
        $this->result_url = PAYNOW_RESULT_URL;
        $this->sandbox = PAYNOW_SANDBOX;
    }

    public static function hasConfiguredCredentials() {
        return !self::isPlaceholderCredential(PAYNOW_INTEGRATION_ID)
            && !self::isPlaceholderCredential(PAYNOW_INTEGRATION_KEY);
    }

    private static function isPlaceholderCredential($value) {
        $value = trim((string)$value);
        return $value === '' || stripos($value, 'YOUR_') !== false;
    }

    /**
     * Initiate a payment
     */
    public function initiatePayment($amount, $reference, $additional_info = []) {
        $data = [
            'resulturl' => $this->result_url,
            'returnurl' => $this->appendQuery($this->return_url, ['reference' => $reference]),
            'reference' => $reference,
            'amount' => number_format($amount, 2, '.', ''),
            'id' => $this->integration_id,
            'additionalinfo' => json_encode($additional_info),
            'authemail' => isset($additional_info['email']) ? $additional_info['email'] : '',
            'phone' => isset($additional_info['phone']) ? $additional_info['phone'] : '',
            'method' => isset($additional_info['method']) ? $additional_info['method'] : 'ecocash',
            'status' => 'Message'
        ];

        // Generate hash
        $data['hash'] = $this->generateHash($data);

        return $data;
    }

    private function appendQuery($url, $params) {
        $separator = parse_url($url, PHP_URL_QUERY) ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }

    /**
     * Generate hash for payment verification
     */
    private function generateHash($data) {
        $string = '';

        foreach ($data as $key => $value) {
            if (strtoupper($key) !== 'HASH') {
                $string .= (string)$value;
            }
        }

        return strtoupper(hash('sha512', $string . $this->integration_key));
    }

    private function getHashValue($data) {
        foreach ($data as $key => $value) {
            if (strtoupper($key) === 'HASH') {
                return (string)$value;
            }
        }

        return null;
    }

    /**
     * Verify payment callback
     */
    public function verifyCallback($callback_data) {
        $received_hash = $this->getHashValue($callback_data);

        if ($received_hash === null) {
            return false;
        }

        $expected_hash = $this->generateHash($callback_data);

        return hash_equals($expected_hash, strtoupper($received_hash));
    }

    /**
     * Get payment URL
     */
    public function getPaymentUrl() {
        return $this->sandbox
            ? 'https://www.paynow.co.zw/interface/initiatetransaction'
            : 'https://www.paynow.co.zw/interface/initiatetransaction';
    }

    public function pollTransaction($poll_url, $reference = '') {
        if ($poll_url === '' || !filter_var($poll_url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Invalid payment poll URL'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $poll_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $message = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => 'Payment status check failed: ' . $message];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code < 200 || $http_code >= 300) {
            return ['success' => false, 'message' => 'Payment status check returned HTTP ' . $http_code];
        }

        parse_str($response, $response_data);

        if ($this->getHashValue($response_data) !== null && !$this->verifyCallback($response_data)) {
            return ['success' => false, 'message' => 'Payment status hash was invalid'];
        }

        $response_data = array_change_key_case($response_data, CASE_LOWER);

        if ($reference !== '' && empty($response_data['reference'])) {
            $response_data['reference'] = $reference;
        }

        if (empty($response_data['reference']) || empty($response_data['status'])) {
            return ['success' => false, 'message' => 'Payment status response was incomplete'];
        }

        return $this->processCallback($response_data);
    }

    /**
     * Process payment callback
     */
    public function processCallback($callback_data) {
        $callback_data = array_change_key_case($callback_data, CASE_LOWER);
        $conn = getDBConnection();
        ensureAccommodationPaymentColumns($conn);
        ensureBusPaymentColumns($conn);
        $conn->begin_transaction();

        $reference = $callback_data['reference'] ?? '';
        $stmt = $conn->prepare("
            SELECT p.*, s.user_id, p.booking_type,
                   CASE WHEN p.booking_type = 'bus' THEN bb.payment_status ELSE ab.payment_status END AS payment_status
            FROM payments p
            JOIN students s ON p.student_id = s.id
            LEFT JOIN accommodation_bookings ab ON p.booking_type = 'accommodation' AND p.booking_id = ab.id
            LEFT JOIN bus_bookings bb ON p.booking_type = 'bus' AND p.booking_id = bb.id
            WHERE p.reference_number = ?
            FOR UPDATE
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $payment_result = $stmt->get_result();

        if ($payment_result->num_rows === 0) {
            $stmt->close();
            $conn->rollback();
            $conn->close();
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment = $payment_result->fetch_assoc();
        $booking_table = $payment['booking_type'] === 'bus' ? 'bus_bookings' : 'accommodation_bookings';

        // Update payment status
        $status = strtolower(trim($callback_data['status'] ?? ''));
        $new_status = match($status) {
            'paid', 'completed', 'successful', 'success', 'awaiting delivery', 'delivered' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            'failed', 'declined', 'error' => 'failed',
            default => 'pending'
        };

        $update_stmt = $conn->prepare("
            UPDATE payments
            SET status = ?, callback_data = ?, payment_date = NOW()
            WHERE reference_number = ?
        ");
        $callback_json = json_encode($callback_data);
        $update_stmt->bind_param("sss", $new_status, $callback_json, $reference);
        $update_stmt->execute();

        // Update booking status if payment completed
        if ($new_status === 'completed') {
            $booking_stmt = $conn->prepare("
                UPDATE {$booking_table}
                SET payment_status = 'paid', status = 'confirmed'
                WHERE id = ?
            ");
            $booking_stmt->bind_param("i", $payment['booking_id']);
            $booking_stmt->execute();

            if ($payment['status'] !== 'completed') {
                // Create notification for student once when the payment first completes.
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'Payment Successful', ?, 'success')
                ");
                $item_label = $payment['booking_type'] === 'bus' ? 'bus booking' : 'accommodation booking';
                $message = 'Your payment of $' . number_format((float)$payment['amount'], 2) . ' for ' . $item_label . ' has been processed successfully.';
                $notif_stmt->bind_param("is", $payment['user_id'], $message);
                $notif_stmt->execute();
                $notif_stmt->close();
            }

            $booking_stmt->close();
        } elseif (in_array($new_status, ['failed', 'cancelled'], true) && $payment['payment_status'] !== 'paid') {
            $booking_stmt = $conn->prepare("
                UPDATE {$booking_table}
                SET payment_status = 'failed'
                WHERE id = ?
            ");
            $booking_stmt->bind_param("i", $payment['booking_id']);
            $booking_stmt->execute();
            $booking_stmt->close();
        }

        $stmt->close();
        $update_stmt->close();
        $conn->commit();
        $conn->close();

        return ['success' => true, 'status' => $new_status];
    }
}
?>
