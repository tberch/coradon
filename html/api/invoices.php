<?php
/**
 * Invoice API endpoints for Colorado Radon Invoice System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

try {
    $db = new InvoiceDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Get the action from URL or POST data
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    
    switch ($method) {
        case 'GET':
            handleGet($db, $action);
            break;
            
        case 'POST':
            handlePost($db, $action);
            break;
            
        case 'PUT':
            handlePut($db, $action);
            break;
            
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            $invoices = $db->getInvoices($limit, $offset);
            
            // Convert database format to frontend format
            $formattedInvoices = array_map('formatInvoiceForFrontend', $invoices);
            
            sendResponse([
                'success' => true,
                'invoices' => $formattedInvoices,
                'count' => count($formattedInvoices)
            ]);
            break;
            
        case 'get':
            $invoiceNumber = $_GET['invoice_number'] ?? '';
            if (empty($invoiceNumber)) {
                sendResponse(['error' => 'Invoice number required'], 400);
                return;
            }
            
            $invoice = $db->getInvoiceByNumber($invoiceNumber);
            if (!$invoice) {
                sendResponse(['error' => 'Invoice not found'], 404);
                return;
            }
            
            sendResponse([
                'success' => true,
                'invoice' => formatInvoiceForFrontend($invoice)
            ]);
            break;
            
        case 'next_number':
            $nextNumber = $db->getNextInvoiceNumber();
            sendResponse([
                'success' => true,
                'next_invoice_number' => $nextNumber
            ]);
            break;
            
        case 'stats':
            $stats = $db->getInvoiceStats();
            sendResponse([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        default:
            sendResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePost($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(['error' => 'Invalid JSON'], 400);
        return;
    }
    
    switch ($action) {
        case 'create':
            if (!isset($input['invoice'])) {
                sendResponse(['error' => 'Invoice data required'], 400);
                return;
            }
            
            $invoiceData = $input['invoice'];
            
            // Validate required fields
            $requiredFields = ['number', 'date', 'dueDate', 'customer', 'items', 'total'];
            foreach ($requiredFields as $field) {
                if (!isset($invoiceData[$field])) {
                    sendResponse(['error' => "Field '$field' is required"], 400);
                    return;
                }
            }
            
            try {
                $invoiceId = $db->createInvoice($invoiceData);
                
                sendResponse([
                    'success' => true,
                    'invoice_id' => $invoiceId,
                    'message' => 'Invoice created successfully'
                ]);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                    sendResponse(['error' => 'Invoice number already exists'], 409);
                } else {
                    throw $e;
                }
            }
            break;
            
        case 'payment':
            if (!isset($input['invoice_number']) || !isset($input['payment_data'])) {
                sendResponse(['error' => 'Invoice number and payment data required'], 400);
                return;
            }
            
            $success = $db->updateInvoiceStatus(
                $input['invoice_number'], 
                'paid', 
                $input['payment_data']
            );
            
            if ($success) {
                sendResponse([
                    'success' => true,
                    'message' => 'Payment recorded successfully'
                ]);
            } else {
                sendResponse(['error' => 'Failed to record payment'], 500);
            }
            break;
            
        case 'stripe_webhook':
            // Handle Stripe webhook for payment confirmation
            $payload = @file_get_contents('php://input');
            $event = null;
            
            try {
                $event = json_decode($payload, true);
            } catch (Exception $e) {
                sendResponse(['error' => 'Invalid payload'], 400);
                return;
            }
            
            // Handle the event
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event['data']['object'];
                    
                    // Find invoice by payment intent ID
                    $stmt = $db->getConnection()->prepare("
                        SELECT * FROM invoices 
                        WHERE stripe_payment_intent_id = :payment_intent_id
                    ");
                    $stmt->execute([':payment_intent_id' => $paymentIntent['id']]);
                    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($invoice) {
                        $paymentData = [
                            'amount' => $paymentIntent['amount_received'] / 100, // Convert from cents
                            'stripe_payment_intent_id' => $paymentIntent['id'],
                            'payment_method' => 'stripe'
                        ];
                        
                        $db->updateInvoiceStatus($invoice['invoice_number'], 'paid', $paymentData);
                    }
                    break;
                    
                default:
                    // Unhandled event type
                    break;
            }
            
            sendResponse(['received' => true]);
            break;
            
        default:
            sendResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePut($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'status':
            if (!isset($input['invoice_number']) || !isset($input['status'])) {
                sendResponse(['error' => 'Invoice number and status required'], 400);
                return;
            }
            
            $success = $db->updateInvoiceStatus(
                $input['invoice_number'], 
                $input['status'],
                $input['payment_data'] ?? null
            );
            
            if ($success) {
                sendResponse([
                    'success' => true,
                    'message' => 'Status updated successfully'
                ]);
            } else {
                sendResponse(['error' => 'Failed to update status'], 500);
            }
            break;
            
        default:
            sendResponse(['error' => 'Unknown action'], 400);
    }
}

function formatInvoiceForFrontend($dbInvoice) {
    return [
        'id' => $dbInvoice['invoice_number'],
        'number' => $dbInvoice['invoice_number'],
        'date' => $dbInvoice['invoice_date'],
        'dueDate' => $dbInvoice['due_date'],
        'currency' => $dbInvoice['currency'],
        'company' => [
            'name' => $dbInvoice['company_name'],
            'email' => $dbInvoice['company_email'],
            'address' => $dbInvoice['company_address']
        ],
        'customer' => [
            'name' => $dbInvoice['customer_name'],
            'email' => $dbInvoice['customer_email'],
            'address' => $dbInvoice['customer_address']
        ],
        'items' => array_map(function($item) {
            return [
                'description' => $item['description'],
                'quantity' => floatval($item['quantity']),
                'price' => floatval($item['unit_price']),
                'total' => floatval($item['total_price'])
            ];
        }, $dbInvoice['items'] ?? []),
        'subtotal' => floatval($dbInvoice['subtotal']),
        'tax' => floatval($dbInvoice['tax_amount']),
        'discount' => floatval($dbInvoice['discount_amount']),
        'total' => floatval($dbInvoice['total_amount']),
        'notes' => $dbInvoice['notes'],
        'status' => $dbInvoice['status'],
        'createdAt' => $dbInvoice['created_at'],
        'paidAt' => $dbInvoice['paid_at'],
        'paymentLink' => $dbInvoice['payment_link'],
        'stripePaymentIntentId' => $dbInvoice['stripe_payment_intent_id']
    ];
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}
?>