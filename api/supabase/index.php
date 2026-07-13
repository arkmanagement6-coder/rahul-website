<?php
// api/supabase/index.php
require_once 'config.php';

// Get the requested path from the query string (routed via .htaccess)
$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$pdo = getDbConnection();

// Simple router
switch (true) {
    // ----------------------------------------------------
    // AUTHENTICATION ENDPOINTS
    // ----------------------------------------------------
    
    // 1. Sign Up
    case ($path === 'auth/v1/signup' && $method === 'POST'):
        $body = getRequestBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            sendJsonResponse(['error' => 'Email and password are required'], 400);
        }
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendJsonResponse(['error' => 'User already registered'], 400);
        }
        
        $userId = generateUuid();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("INSERT INTO users (id, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $email, $passwordHash]);
        
        sendJsonResponse([
            'id' => $userId,
            'email' => $email,
            'user' => [
                'id' => $userId,
                'email' => $email
            ]
        ]);
        break;
        
    // 2. Login
    case ($path === 'auth/v1/token' && $method === 'POST'):
        $body = getRequestBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            sendJsonResponse(['error' => 'Email and password are required'], 400);
        }
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendJsonResponse(['error' => 'Invalid email or password'], 400);
        }
        
        $userId = $user['id'];
        $token = generateToken($userId);
        
        sendJsonResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600 * 24 * 30, // 30 days
            'refresh_token' => 'mock_refresh_token_' . bin2hex(random_bytes(8)),
            'user' => [
                'id' => $userId,
                'email' => $email
            ]
        ]);
        break;
        
    // 3. Get User Details
    case ($path === 'auth/v1/user' && $method === 'GET'):
        $userId = verifyToken();
        
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendJsonResponse(['error' => 'User not found'], 404);
        }
        
        sendJsonResponse([
            'id' => $userId,
            'email' => $user['email']
        ]);
        break;
        
    // 4. Logout
    case ($path === 'auth/v1/logout' && $method === 'POST'):
        sendJsonResponse(['message' => 'Logged out successfully']);
        break;
        
    // ----------------------------------------------------
    // REST DATABASE ENDPOINTS
    // ----------------------------------------------------
    
    // 5. Profiles Table (Insert / Select)
    case ($path === 'rest/v1/profiles'):
        if ($method === 'POST') {
            $body = getRequestBody();
            // Handle both object and array of objects
            $dataList = isset($body[0]) ? $body : [$body];
            
            foreach ($dataList as $data) {
                $id = $data['id'] ?? null;
                $fullName = $data['full_name'] ?? null;
                $phone = $data['phone'] ?? null;
                
                if (!$id || !$fullName) {
                    sendJsonResponse(['error' => 'Profile id and full_name are required'], 400);
                }
                
                $stmt = $pdo->prepare("INSERT INTO profiles (id, full_name, phone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE full_name = ?, phone = ?");
                $stmt->execute([$id, $fullName, $phone, $fullName, $phone]);
            }
            sendJsonResponse(['message' => 'Profile updated successfully']);
        }
        break;
        
    // 6. Money Transfers Table (Insert / Select)
    case ($path === 'rest/v1/money_transfers'):
        if ($method === 'POST') {
            $body = getRequestBody();
            $dataList = isset($body[0]) ? $body : [$body];
            
            foreach ($dataList as $data) {
                $userId = $data['user_id'] ?? null;
                $senderCardNumber = $data['sender_card_number'] ?? '';
                $senderCardHolder = $data['sender_card_holder'] ?? '';
                $recipientName = $data['recipient_name'] ?? '';
                $recipientAccountNumber = $data['recipient_account_number'] ?? '';
                $recipientIfsc = $data['recipient_ifsc'] ?? '';
                $recipientBankName = $data['recipient_bank_name'] ?? '';
                $amount = floatval($data['amount'] ?? 0);
                $fee = floatval($data['fee'] ?? 0);
                $transferAmount = floatval($data['transfer_amount'] ?? 0);
                $status = $data['status'] ?? 'pending';
                $razorpayPaymentId = $data['razorpay_payment_id'] ?? null;
                
                $stmt = $pdo->prepare("INSERT INTO money_transfers (user_id, sender_card_number, sender_card_holder, recipient_name, recipient_account_number, recipient_ifsc, recipient_bank_name, amount, fee, transfer_amount, status, razorpay_payment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $senderCardNumber, $senderCardHolder, $recipientName, $recipientAccountNumber, $recipientIfsc, $recipientBankName, $amount, $fee, $transferAmount, $status, $razorpayPaymentId]);
            }
            sendJsonResponse(['message' => 'Money transfer created successfully']);
        }
        elseif ($method === 'GET') {
            // Get user_id filter from query string (e.g. user_id=eq.uuid)
            $userIdFilter = '';
            foreach ($_GET as $key => $val) {
                if (strpos($key, 'user_id') !== false) {
                    // Extract uuid from "eq.uuid"
                    $parts = explode('.', $val);
                    $userIdFilter = $parts[1] ?? '';
                }
            }
            
            if ($userIdFilter === '') {
                // Return empty if not authenticated/filtered
                sendJsonResponse([]);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM money_transfers WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userIdFilter]);
            $transfers = $stmt->fetchAll();
            
            // Supabase client expects database field names
            sendJsonResponse($transfers);
        }
        break;
        
    // 7. Contact Submissions Table (Insert)
    case ($path === 'rest/v1/contact_submissions'):
        if ($method === 'POST') {
            $body = getRequestBody();
            $dataList = isset($body[0]) ? $body : [$body];
            
            foreach ($dataList as $data) {
                $name = $data['name'] ?? '';
                $email = $data['email'] ?? '';
                $phone = $data['phone'] ?? null;
                $subject = $data['subject'] ?? null;
                $message = $data['message'] ?? '';
                
                $stmt = $pdo->prepare("INSERT INTO contact_submissions (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $subject, $message]);
            }
            sendJsonResponse(['message' => 'Message submitted successfully']);
        }
        break;

    // 8. Razorpay Key Endpoint
    case ($path === 'rest/v1/razorpay_key' && $method === 'GET'):
        sendJsonResponse(['key_id' => RAZORPAY_KEY_ID]);
        break;
        
    default:
        sendJsonResponse(['error' => 'Not Found: ' . $path], 404);
        break;
}
?>
