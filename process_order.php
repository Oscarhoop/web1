<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  // Allow all origins (adjust in production)
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once 'config.php';

// Check database connection
if (!$conn) {
    http_response_code(500);  // Internal Server Error
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]));
}

// Get raw POST data
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);  // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'No data received'
    ]);
    exit;
}

// Decode JSON data
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);  // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit;
}

// Validate required fields
if (empty($data['customerName']) || empty($data['email']) || empty($data['phone']) || empty($data['address']) || empty($data['items'])) {
    http_response_code(400);  // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Process the order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        if (!$conn->begin_transaction()) {
            throw new Exception("Could not begin transaction");
        }

        // Calculate total amount
        $totalAmount = 0;
        foreach ($data['items'] as $item) {
            if (!isset($item['price']) || !isset($item['quantity'])) {
                throw new Exception("Invalid item data: missing price or quantity");
            }
            $totalAmount += $item['price'] * $item['quantity'];
        }

        // Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, phone, address, total_amount) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssssd", 
            $data['customerName'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $totalAmount
        );
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $orderId = $conn->insert_id;

        // Insert order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        foreach ($data['items'] as $item) {
            if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                throw new Exception("Invalid item data: missing required fields");
            }
            $stmt->bind_param("issdi",
                $orderId,
                $item['id'],
                $item['name'],
                $item['price'],
                $item['quantity']
            );
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        }

        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Could not commit transaction");
        }

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'orderId' => $orderId
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        http_response_code(500);  // Internal Server Error
        echo json_encode([
            'success' => false,
            'message' => 'Error processing order: ' . $e->getMessage(),
            'debug' => error_get_last()  // Add debug information
        ]);
    }
} else {
    http_response_code(405);  // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>