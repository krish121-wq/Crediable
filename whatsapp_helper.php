<?php
require_once 'whatsapp_config.php';
require_once 'config.php';


class WhatsAppHelper {
    private $access_token;
    private $phone_number_id;
    private $base_url;
    
    public function __construct() {
        $this->access_token = WHATSAPP_ACCESS_TOKEN;
        $this->phone_number_id = WHATSAPP_PHONE_NUMBER_ID;
        $this->base_url = WHATSAPP_BASE_URL;
    }
    
    /**
     * Send WhatsApp message to a single recipient
     */
    public function sendMessage($recipient_phone, $message_type, $message_data) {
        $url = $this->base_url . '/' . $this->phone_number_id . '/messages';
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient_phone,
            'type' => $message_type,
        ];
        
        // Add message data based on type
        switch($message_type) {
            case 'template':
                $payload['template'] = $message_data;
                break;
            case 'text':
                $payload['text'] = $message_data;
                break;
            default:
                throw new Exception("Unsupported message type: $message_type");
        }
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log the message
        $this->logMessage($recipient_phone, $message_type, $message_data, $result, $http_code);
        
        return [
            'success' => $http_code == 200,
            'response' => $result,
            'http_code' => $http_code
        ];
    }
    
    /**
     * Send bulk messages to multiple recipients with rate limiting
     */
    public function sendBulkMessages($recipients, $message_type, $message_data) {
        $results = [];
        $delay = 1000000 / WHATSAPP_RATE_LIMIT_PER_SECOND; // microseconds
        
        foreach ($recipients as $recipient) {
            try {
                $result = $this->sendMessage($recipient, $message_type, $message_data);
                $results[] = [
                    'phone' => $recipient,
                    'success' => $result['success'],
                    'response' => $result['response']
                ];
                
                // Rate limiting
                usleep($delay);
                
            } catch (Exception $e) {
                $results[] = [
                    'phone' => $recipient,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Send offer message template
     */
    public function sendOfferMessage($recipient_phone, $offer_details) {
        $template_data = [
            'name' => WHATSAPP_OFFER_TEMPLATE,
            'language' => ['code' => 'en'],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $offer_details['title'] ?? 'Special Offer'],
                        ['type' => 'text', 'text' => $offer_details['description'] ?? 'Check out our amazing offers!'],
                        ['type' => 'text', 'text' => $offer_details['discount'] ?? 'Limited time'],
                        ['type' => 'text', 'text' => $offer_details['validity'] ?? 'Today only']
                    ]
                ]
            ]
        ];
        
        return $this->sendMessage($recipient_phone, 'template', $template_data);
    }
    
    /**
     * Send new item listing message template
     */
    public function sendNewItemMessage($recipient_phone, $item_details) {
        $template_data = [
            'name' => WHATSAPP_NEW_ITEM_TEMPLATE,
            'language' => ['code' => 'en'],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $item_details['name'] ?? 'New Product'],
                        ['type' => 'text', 'text' => $item_details['category'] ?? 'Available now'],
                        ['type' => 'text', 'text' => $item_details['price'] ?? 'Great price'],
                        ['type' => 'text', 'text' => $item_details['description'] ?? 'Check it out']
                    ]
                ]
            ]
        ];
        
        return $this->sendMessage($recipient_phone, 'template', $template_data);
    }
    
    /**
     * Send custom text message
     */
    public function sendTextMessage($recipient_phone, $text_message) {
        $message_data = [
            'body' => $text_message
        ];
        
        return $this->sendMessage($recipient_phone, 'text', $message_data);
    }
    
    /**
     * Log message to database
     */
    private function logMessage($recipient_phone, $message_type, $message_data, $response, $http_code) {
        global $conn;
        
        try {
            // Create message log table if it doesn't exist
            $this->createMessageLogTable();
            
            $stmt = $conn->prepare("
                INSERT INTO " . WHATSAPP_MESSAGE_LOG_TABLE . " 
                (recipient_phone, message_type, message_data, response, http_code, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $message_json = json_encode($message_data);
            $response_json = json_encode($response);
            
            $stmt->bind_param("ssssi", $recipient_phone, $message_type, $message_json, $response_json, $http_code);
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Failed to log WhatsApp message: " . $e->getMessage());
        }
    }
    
    /**
     * Create message log table if it doesn't exist
     */
    private function createMessageLogTable() {
        global $conn;
        
        $sql = "
            CREATE TABLE IF NOT EXISTS " . WHATSAPP_MESSAGE_LOG_TABLE . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_phone VARCHAR(20) NOT NULL,
                message_type VARCHAR(50) NOT NULL,
                message_data TEXT,
                response TEXT,
                http_code INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_recipient (recipient_phone),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $conn->query($sql);
    }
    
    /**
     * Get message logs
     */
    public function getMessageLogs($limit = 100, $offset = 0) {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT * FROM " . WHATSAPP_MESSAGE_LOG_TABLE . " 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Validate phone number format for WhatsApp
     */
    public function validatePhoneNumber($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure it starts with country code
        if (strlen($phone) == 10) {
            // Assume Indian number if 10 digits
            $phone = '91' . $phone;
        } elseif (strlen($phone) == 11 && substr($phone, 0, 1) == '0') {
            // Remove leading 0 and add country code
            $phone = '91' . substr($phone, 1);
        }
        
        // Add + prefix if not present
        if (substr($phone, 0, 1) != '+') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}

/**
 * Utility function to get all customer phone numbers
 */
function getAllCustomerPhones($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT phone FROM customers WHERE phone IS NOT NULL AND phone != ''");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $phones = [];
    $whatsapp = new WhatsAppHelper();
    
    while ($row = $result->fetch_assoc()) {
        $phones[] = $whatsapp->validatePhoneNumber($row['phone']);
    }
    
    return array_unique($phones);
}

/**
 * Utility function to get customer phone numbers by state
 */
function getCustomerPhonesByState($conn, $state) {
    $stmt = $conn->prepare("SELECT DISTINCT phone FROM customers WHERE state = ? AND phone IS NOT NULL AND phone != ''");
    $stmt->bind_param("s", $state);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $phones = [];
    $whatsapp = new WhatsAppHelper();
    
    while ($row = $result->fetch_assoc()) {
        $phones[] = $whatsapp->validatePhoneNumber($row['phone']);
    }
    
    return array_unique($phones);
}
?>
