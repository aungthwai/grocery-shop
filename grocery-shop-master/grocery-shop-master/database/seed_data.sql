-- Seed data for Grocery Management System

INSERT INTO suppliers (supplier_name, company, phone, email, address) VALUES
('Abul Khair Group', 'Abul Khair Enterprise', '01711000111', 'info@abulkhair.com', 'Dhaka, Bangladesh'),
('ACI Foods Ltd', 'ACI Limited', '01822000222', 'sales@aci.com', 'Tejgaon, Dhaka'),
('Pran Foods Ltd', 'PRAN-RFL Group', '01933000333', 'contact@pran.com', 'Badda, Dhaka'),
('Fresh Consumer Care', 'Meghna Group', '01644000444', 'support@fresh.com', 'Narayanganj, Dhaka');

INSERT INTO customers (customer_name, phone, email, address, total_due) VALUES
('Rahim Uddin', '01712345678', 'rahim@gmail.com', 'Mirpur-10, Dhaka', 250.00),
('Karim Chowdhury', '01898765432', 'karim@yahoo.com', 'Dhanmondi, Dhaka', 0.00),
('Tanvir Ahmed', '01911223344', 'tanvir@gmail.com', 'Uttara, Dhaka', 500.00),
('Sultana Razia', '01655667788', 'sultana@hotmail.com', 'Gulshan, Dhaka', 0.00);

-- Make sure products exist
INSERT INTO products (category_id, supplier_id, product_name, barcode, unit, purchase_price, selling_price, stock, minimum_stock, status) VALUES
(1, 1, 'Miniket Rice Premium 5kg', '8901234567891', 'kg', 320.00, 370.00, 45, 10, 'Active'),
(1, 2, 'ACI Pure Salt 1kg', '8901234567892', 'pcs', 38.00, 45.00, 100, 20, 'Active'),
(2, 3, 'Pran Mustard Oil 1L', '8901234567893', 'L', 210.00, 240.00, 30, 5, 'Active'),
(3, 4, 'Fresh Sugar 1kg', '8901234567894', 'kg', 125.00, 140.00, 8, 10, 'Active'),
(2, 2, 'ACI Pure Soyabean Oil 5L', '8901234567895', 'L', 780.00, 840.00, 15, 5, 'Active');

-- Add sample purchases
INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, payment_status, remarks) VALUES
(1, 'PUR-1001', CURDATE(), 14400.00, 'Paid', 'Initial stock purchase'),
(2, 'PUR-1002', CURDATE(), 3800.00, 'Paid', 'Salt & Oil stock refill');

-- Add sample sales
INSERT INTO sales (customer_id, invoice_no, sale_date, total_amount, paid_amount, due_amount, payment_method) VALUES
(1, 'INV-2001', CURDATE(), 620.00, 370.00, 250.00, 'Cash'),
(2, 'INV-2002', CURDATE(), 45.00, 45.00, 0.00, 'Cash'),
(3, 'INV-2003', CURDATE(), 1080.00, 580.00, 500.00, 'Mobile Banking');

-- Add sale items
INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES
(1, 1, 1, 370.00, 370.00),
(1, 4, 1, 140.00, 140.00),
(2, 2, 1, 45.00, 45.00),
(3, 3, 2, 240.00, 480.00),
(3, 1, 1, 370.00, 370.00);
