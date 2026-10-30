<?php

define('WHATSAPP_ACCESS_TOKEN', 'YOUR_ACCESS_TOKEN_HERE'); // Replace with your actual access token
define('WHATSAPP_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID_HERE'); // Replace with your actual phone number ID
define('WHATSAPP_API_VERSION', 'v18.0');
define('WHATSAPP_BASE_URL', 'https://graph.facebook.com/' . WHATSAPP_API_VERSION);

// WhatsApp Business Account ID
define('WHATSAPP_BUSINESS_ACCOUNT_ID', 'YOUR_BUSINESS_ACCOUNT_ID_HERE');

// Webhook verification token
define('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'your_webhook_verify_token_here');

// Message templates
define('WHATSAPP_OFFER_TEMPLATE', 'offer_message');
define('WHATSAPP_NEW_ITEM_TEMPLATE', 'new_item_message');

// Rate limiting
define('WHATSAPP_RATE_LIMIT_PER_SECOND', 50);
define('WHATSAPP_RATE_LIMIT_PER_MINUTE', 1000);

// Database table for message logs
define('WHATSAPP_MESSAGE_LOG_TABLE', 'whatsapp_message_logs');
?>
