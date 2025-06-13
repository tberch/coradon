<?php
/**
 * Database connection and initialization for Colorado Radon Invoice System
 */

class InvoiceDatabase {
    private $db;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = __DIR__ . '/invoices.db';
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            invoice_date DATE NOT NULL,
            due_date DATE NOT NULL,
            currency VARCHAR(10) DEFAULT 'USD',
            company_name VARCHAR(255) NOT NULL,
            company_email VARCHAR(255) NOT NULL,
            company_address TEXT,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_address TEXT,
            subtotal DECIMAL(10,2) NOT NULL,
            tax_amount DECIMAL(10,2) DEFAULT 0,
            discount_amount DECIMAL(10,2) DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL,
            notes TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            payment_link TEXT,
            stripe_payment_intent_id VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL
        );
        
        CREATE TABLE IF NOT EXISTS invoice_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id INTEGER NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id INTEGER NOT NULL,
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            customer_address TEXT,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            amount_paid DECIMAL(10,2),
            stripe_payment_intent_id VARCHAR(255),
            payment_method VARCHAR(50),
            FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_invoice_number ON invoices(invoice_number);
        CREATE INDEX IF NOT EXISTS idx_invoice_status ON invoices(status);
        CREATE INDEX IF NOT EXISTS idx_invoice_date ON invoices(invoice_date);
        CREATE INDEX IF NOT EXISTS idx_stripe_payment_intent ON invoices(stripe_payment_intent_id);
        ";
        
        $this->db->exec($sql);
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function getLastInvoiceNumber() {
        $stmt = $this->db->prepare("
            SELECT invoice_number 
            FROM invoices 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Extract number from INV-001 format
            preg_match('/(\d+)$/', $result['invoice_number'], $matches);
            return isset($matches[1]) ? intval($matches[1]) : 0;
        }
        
        return 0;
    }
    
    public function getNextInvoiceNumber() {
        $lastNumber = $this->getLastInvoiceNumber();
        return 'INV-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }
    
    public function createInvoice($data) {
        $this->db->beginTransaction();
        
        try {
            // Insert invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, invoice_date, due_date, currency,
                    company_name, company_email, company_address,
                    customer_name, customer_email, customer_address,
                    subtotal, tax_amount, discount_amount, total_amount,
                    notes, payment_link, stripe_payment_intent_id
                ) VALUES (
                    :invoice_number, :invoice_date, :due_date, :currency,
                    :company_name, :company_email, :company_address,
                    :customer_name, :customer_email, :customer_address,
                    :subtotal, :tax_amount, :discount_amount, :total_amount,
                    :notes, :payment_link, :stripe_payment_intent_id
                )
            ");
            
            $stmt->execute([
                ':invoice_number' => $data['number'],
                ':invoice_date' => $data['date'],
                ':due_date' => $data['dueDate'],
                ':currency' => $data['currency'],
                ':company_name' => $data['company']['name'],
                ':company_email' => $data['company']['email'],
                ':company_address' => $data['company']['address'],
                ':customer_name' => $data['customer']['name'],
                ':customer_email' => $data['customer']['email'],
                ':customer_address' => $data['customer']['address'],
                ':subtotal' => $data['subtotal'],
                ':tax_amount' => $data['tax'],
                ':discount_amount' => $data['discount'],
                ':total_amount' => $data['total'],
                ':notes' => $data['notes'],
                ':payment_link' => $data['paymentLink'],
                ':stripe_payment_intent_id' => $data['stripePaymentIntentId'] ?? null
            ]);
            
            $invoiceId = $this->db->lastInsertId();
            
            // Insert invoice items
            $itemStmt = $this->db->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price)
                VALUES (:invoice_id, :description, :quantity, :unit_price, :total_price)
            ");
            
            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':description' => $item['description'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['price'],
                    ':total_price' => $item['total']
                ]);
            }
            
            $this->db->commit();
            return $invoiceId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getInvoices($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM invoices 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items for each invoice
        foreach ($invoices as &$invoice) {
            $invoice['items'] = $this->getInvoiceItems($invoice['id']);
        }
        
        return $invoices;
    }
    
    public function getInvoiceByNumber($invoiceNumber) {
        $stmt = $this->db->prepare("
            SELECT * FROM invoices 
            WHERE invoice_number = :invoice_number
        ");
        $stmt->execute([':invoice_number' => $invoiceNumber]);
        
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice) {
            $invoice['items'] = $this->getInvoiceItems($invoice['id']);
        }
        
        return $invoice;
    }
    
    public function getInvoiceItems($invoiceId) {
        $stmt = $this->db->prepare("
            SELECT * FROM invoice_items 
            WHERE invoice_id = :invoice_id 
            ORDER BY id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateInvoiceStatus($invoiceNumber, $status, $paymentData = null) {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                UPDATE invoices 
                SET status = :status, 
                    paid_at = :paid_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE invoice_number = :invoice_number
            ");
            
            $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                ':status' => $status,
                ':paid_at' => $paidAt,
                ':invoice_number' => $invoiceNumber
            ]);
            
            // Log payment information if provided
            if ($status === 'paid' && $paymentData) {
                $invoiceId = $this->getInvoiceByNumber($invoiceNumber)['id'];
                
                $paymentStmt = $this->db->prepare("
                    INSERT INTO payment_logs (
                        invoice_id, customer_name, customer_email, 
                        customer_phone, customer_address, amount_paid,
                        stripe_payment_intent_id, payment_method
                    ) VALUES (
                        :invoice_id, :customer_name, :customer_email,
                        :customer_phone, :customer_address, :amount_paid,
                        :stripe_payment_intent_id, :payment_method
                    )
                ");
                
                $paymentStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':customer_name' => $paymentData['name'] ?? null,
                    ':customer_email' => $paymentData['email'] ?? null,
                    ':customer_phone' => $paymentData['phone'] ?? null,
                    ':customer_address' => $paymentData['address'] ?? null,
                    ':amount_paid' => $paymentData['amount'] ?? null,
                    ':stripe_payment_intent_id' => $paymentData['stripe_payment_intent_id'] ?? null,
                    ':payment_method' => $paymentData['payment_method'] ?? 'stripe'
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getInvoiceStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_invoices,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_invoices,
                SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as total_pending,
                SUM(total_amount) as total_amount
            FROM invoices
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>