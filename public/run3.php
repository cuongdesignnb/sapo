<?php
$db = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$db->exec("DROP TABLE IF EXISTS return_items");
$db->exec("DROP TABLE IF EXISTS returns");
$db->exec("CREATE TABLE returns (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(255) UNIQUE, invoice_id INTEGER, customer_id INTEGER, branch_id INTEGER, status VARCHAR(255) DEFAULT 'Đã trả', subtotal DECIMAL(15,2) DEFAULT 0, discount DECIMAL(15,2) DEFAULT 0, fee DECIMAL(15,2) DEFAULT 0, total DECIMAL(15,2) DEFAULT 0, paid_to_customer DECIMAL(15,2) DEFAULT 0, note TEXT, created_by_name VARCHAR(255), seller_name VARCHAR(255), sales_channel VARCHAR(255), price_book_name VARCHAR(255), created_at DATETIME, updated_at DATETIME)");
$db->exec("CREATE TABLE return_items (id INTEGER PRIMARY KEY AUTOINCREMENT, return_id INTEGER, product_id INTEGER, quantity INTEGER DEFAULT 1, price DECIMAL(15,2) DEFAULT 0, discount DECIMAL(15,2) DEFAULT 0, import_price DECIMAL(15,2) DEFAULT 0, created_at DATETIME, updated_at DATETIME)");
echo "Done PDO";
echo "Done new SQLite3";
