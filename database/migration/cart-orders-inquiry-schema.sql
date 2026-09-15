-- 1. Restrict payment methods to Cash on Delivery and Secure Card Gateway
-- 2. Add transaction reference identifier for card payment gateway verification
-- 3. Store masked card verification digits for customer receipts and audit
-- 4. Record card brand network indicator
-- 5. Record settlement timestamp for card authorization
-- 6. Optimize customer order history lookup
ALTER TABLE orders 
    MODIFY COLUMN payment_method ENUM('cod', 'card') NOT NULL DEFAULT 'cod' COMMENT 'Authorized payment channels without direct bank slip transfers',
    ADD COLUMN transaction_id VARCHAR(100) NULL COMMENT 'Unique payment gateway transaction reference' AFTER payment_status,
    ADD COLUMN card_last4 VARCHAR(4) NULL COMMENT 'Masked card last 4 digits' AFTER transaction_id,
    ADD COLUMN card_brand VARCHAR(20) NULL COMMENT 'Payment card association' AFTER card_last4,
    ADD COLUMN paid_at DATETIME NULL COMMENT 'Payment gateway settlement timestamp' AFTER card_brand,
    ADD INDEX idx_order_created (created_at);

-- 7. Optimize customer inquiry history lookup
ALTER TABLE inquiries 
    ADD INDEX idx_inquiry_email (customer_email);