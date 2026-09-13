DROP DATABASE IF EXISTS ecosprout_db;
CREATE DATABASE ecosprout_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecosprout_db;

-- Users Table (Role-Based Access Control)

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
    phone VARCHAR(25) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_email (email)
) ENGINE=InnoDB;

-- Plant Categories Table

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Plants, Accessories & Botanical Inventory Table

CREATE TABLE plants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    botanical_name VARCHAR(150) NULL,
    description TEXT NOT NULL,
    care_sunlight ENUM('Full Sun', 'Partial Shade', 'Indirect Light', 'Low Light', 'Not Applicable') NOT NULL DEFAULT 'Partial Shade',
    care_water ENUM('Daily', '2-3 Times a Week', 'Weekly', 'When Soil is Dry', 'Not Applicable') NOT NULL DEFAULT '2-3 Times a Week',
    care_soil VARCHAR(120) DEFAULT 'Well-draining rich potting soil',
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT 'assets/img/default_plant.png',
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_plant_category (category_id),
    INDEX idx_plant_name (name),
    INDEX idx_plant_featured (is_featured)
) ENGINE=InnoDB;

-- Landscaping & Professional Gardening Services Table

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    short_desc TEXT NOT NULL,
    price_estimate VARCHAR(100) NOT NULL,
    icon_class VARCHAR(50) DEFAULT 'fas fa-seedling',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Service Consultation & Landscaping Bookings Table

CREATE TABLE service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    booking_date DATE NOT NULL,
    preferred_time VARCHAR(50) NOT NULL,
    location_address TEXT NOT NULL,
    special_notes TEXT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_booking_user (user_id),
    INDEX idx_booking_status (status)
) ENGINE=InnoDB;

-- Seasonal Workshops & Educational Events Table

CREATE TABLE workshops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    instructor VARCHAR(100) NOT NULL,
    schedule_date DATE NOT NULL,
    schedule_time VARCHAR(50) NOT NULL,
    location VARCHAR(150) DEFAULT 'EcoSprout Training Centre, Kegalle',
    fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    capacity INT NOT NULL DEFAULT 25,
    seats_booked INT NOT NULL DEFAULT 0,
    status ENUM('upcoming', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workshop_date (schedule_date),
    INDEX idx_workshop_status (status)
) ENGINE=InnoDB;

-- Workshop Participant Registrations Table

CREATE TABLE workshop_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workshop_id INT NOT NULL,
    user_id INT NOT NULL,
    participant_name VARCHAR(120) NOT NULL,
    participant_email VARCHAR(150) NOT NULL,
    participant_phone VARCHAR(25) NOT NULL,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workshop_id) REFERENCES workshops(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_reg_workshop (workshop_id),
    INDEX idx_reg_user (user_id)
) ENGINE=InnoDB;

-- Customer Orders Table

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cod', 'card', 'bank_transfer') NOT NULL DEFAULT 'cod',
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    order_status ENUM('processing', 'dispatched', 'delivered', 'cancelled') DEFAULT 'processing',
    shipping_address TEXT NOT NULL,
    contact_phone VARCHAR(25) NOT NULL,
    order_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_order_user (user_id),
    INDEX idx_order_status (order_status)
) ENGINE=InnoDB;

-- Order Items Table

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    plant_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_item_order (order_id),
    INDEX idx_item_plant (plant_id)
) ENGINE=InnoDB;

-- Plant Care Inquiries & Customer Advisory Table

CREATE TABLE inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved') DEFAULT 'open',
    staff_reply TEXT NULL,
    staff_id INT NULL,
    replied_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_inquiry_status (status)
) ENGINE=InnoDB;

-- Educational Resources, Guides & Blog Posts Table

CREATE TABLE blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    category ENUM('Plant Care Tips', 'DIY Projects', 'Seasonal Guides') NOT NULL DEFAULT 'Plant Care Tips',
    content TEXT NOT NULL,
    image_url VARCHAR(255) DEFAULT 'assets/img/default_blog.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_blog_category (category)
) ENGINE=InnoDB;

-- Users Seed
INSERT INTO users (full_name, email, password_hash, role, phone, address) VALUES
('System Administrator', 'admin@ecosprout.lk', '$2y$10$8sA2N5Sx/1zMQv2yrTDAaOFlbGWECrrgB68axL.hBb78NhQdyAqWm', 'admin', '+94712345678', 'EcoSprout HQ, Colombo-Kandy Rd, Kegalle'),
('Nadeeka Silva (Nursery Agronomist)', 'staff@ecosprout.lk', '$2y$10$8sA2N5Sx/1zMQv2yrTDAaOFlbGWECrrgB68axL.hBb78NhQdyAqWm', 'staff', '+94776543210', 'Nursery Field Station, Ranwala, Kegalle'),
('Kamal Perera', 'customer@gmail.com', '$2y$10$8sA2N5Sx/1zMQv2yrTDAaOFlbGWECrrgB68axL.hBb78NhQdyAqWm', 'customer', '+94789876543', 'No 45, Main Street, Kegalle'),
('Sandun Dissanayake', 'sandun.d@gmail.com', '$2y$10$8sA2N5Sx/1zMQv2yrTDAaOFlbGWECrrgB68axL.hBb78NhQdyAqWm', 'customer', '+94701234567', 'Kandy Road, Mawanella');

-- Categories Seed
INSERT INTO categories (name, slug, description) VALUES
('Indoor Plants', 'indoor', 'Foliage and air-purifying plants ideal for home and office interior spaces.'),
('Outdoor & Garden', 'outdoor', 'Hardy sun-loving varieties for vibrant residential landscaping in Sabaragamuwa.'),
('Edible & Medicinal', 'edible', 'Fruit saplings, native culinary herbs, and traditional Sri Lankan Ayurvedic species.'),
('Ornamental Varieties', 'ornamental', 'Exotic flowering flora, potted Anthuriums, and ornamental patio greenery.'),
('Tools & Accessories', 'tools-accessories', 'Pruning shears, watering cans, pots, organic potting mix, and nursery supplies.');

-- Plants & Products Seed
INSERT INTO plants (category_id, name, botanical_name, description, care_sunlight, care_water, care_soil, price, stock_quantity, is_featured) VALUES
(1, 'Monstera Deliciosa (Swiss Cheese Plant)', 'Monstera deliciosa', 'Iconic split-leaf tropical vine that thrives in modern interior spaces.', 'Indirect Light', 'When Soil is Dry', 'Aerated peat and perlite potting mix', 2850.00, 24, TRUE),
(1, 'Sansevieria Trifasciata (Snake Plant)', 'Dracaena trifasciata', 'Indestructible air purifier with architectural vertical leaves, ideal for low humidity.', 'Low Light', 'When Soil is Dry', 'Well-draining sandy loam', 1650.00, 40, TRUE),
(2, 'Ceylon Cinnamon Sapling', 'Cinnamomum verum', 'Authentic Sri Lankan true cinnamon sapling cultivated for aromatic foliage and spice yield.', 'Full Sun', '2-3 Times a Week', 'Deep loam with rich organic compost', 950.00, 100, TRUE),
(2, 'Bougainvillea Imperial Pink', 'Bougainvillea spectabilis', 'Vibrant drought-resistant climbing shrub with continuous, brilliant magenta-pink bracts.', 'Full Sun', 'When Soil is Dry', 'Gravelly well-draining garden soil', 1400.00, 35, FALSE),
(3, 'Betel Leaf Creeper (Bulath)', 'Piper betle', 'Traditional culinary and cultural climbing evergreen with glossy, heart-shaped leaves.', 'Partial Shade', 'Daily', 'Moist fertile humus soil', 650.00, 60, TRUE),
(3, 'Aloe Vera Barbadensis', 'Aloe barbadensis miller', 'Succulent herb famous for soothing topical gel and restorative medicinal properties.', 'Full Sun', 'Weekly', 'Cactus and succulent potting mix', 800.00, 50, FALSE),
(4, 'Anthurium Red Lady', 'Anthurium andraeanum', 'Showy glossy red spathes with enduring blooms, perfect for veranda potted decor.', 'Indirect Light', '2-3 Times a Week', 'Orchid bark, charcoal, and coconut husk mix', 2200.00, 18, TRUE),
(4, 'Peace Lily (Spathiphyllum)', 'Spathiphyllum wallisii', 'Glossy dark leaves with elegant white blooms that filter indoor airborne toxins.', 'Indirect Light', '2-3 Times a Week', 'Moist organic potting soil', 1850.00, 22, FALSE),
(5, 'Ergonomic Bypass Pruning Shears', NULL, 'High-carbon Japanese steel blades for clean stem cuts on shrubs and bonsai.', 'Not Applicable', 'Not Applicable', 'Not Applicable', 2450.00, 30, FALSE),
(5, 'Organic Vermicompost Potting Mix (5kg)', NULL, 'Microbe-rich organic fertilizer formulated from Kegalle local plant matter and vermiculture.', 'Not Applicable', 'Not Applicable', 'Not Applicable', 750.00, 85, TRUE);

-- Services Seed
INSERT INTO services (title, short_desc, price_estimate, icon_class) VALUES
('Residential Landscape Planning', 'Custom architectural garden layouts, plant positioning, and sod installations tailored for Sabaragamuwa climate.', 'Starting from LKR 25,000', 'fas fa-tree'),
('Automated Irrigation Setup', 'Drip micro-irrigation and smart moisture controller installation for domestic and commercial gardens.', 'Starting from LKR 15,000', 'fas fa-faucet-drip'),
('Organic Plant Health & Pest Audit', 'On-site health inspection by nursery agronomists with biological pest solutions.', 'LKR 4,500 per visit', 'fas fa-heart-pulse');

-- Workshops Seed
INSERT INTO workshops (title, instructor, schedule_date, schedule_time, location, fee, capacity, seats_booked, status) VALUES
('Organic Composting & Vermiculture', 'Dr. Ruwan Wickramasinghe', '2026-09-20', '09:00 AM - 12:30 PM', 'EcoSprout Training Centre, Kegalle', 2500.00, 25, 8, 'upcoming'),
('Bonsai Art & Pruning Mastery', 'Master K. G. Senaratne', '2026-09-27', '01:30 PM - 05:00 PM', 'Botanical Workshop Hall, Kegalle', 3500.00, 20, 14, 'upcoming'),
('Hydroponic Herb Gardening at Home', 'Eng. Chathura Weerasinghe', '2026-10-04', '09:30 AM - 01:00 PM', 'Greenhouse Alpha, EcoSprout Kegalle', 3000.00, 30, 5, 'upcoming');

-- Sample Inquiries Seed
INSERT INTO inquiries (user_id, customer_name, customer_email, subject, message, status, staff_reply, staff_id, replied_at) VALUES
(3, 'Kamal Perera', 'customer@gmail.com', 'Yellowing leaves on newly purchased Anthurium', 'Hi team, the lower leaves of my Anthurium purchased last week are turning pale yellow. What should I adjust in watering?', 'resolved', 'This is typical over-watering symptoms in humid Kegalle weather. Ensure the pot has free drainage holes and allow the top 2 inches of compost to dry before re-watering.', 2, '2026-09-08 10:30:00'),
(4, 'Sandun Dissanayake', 'sandun.d@gmail.com', 'Organic pest repellent for Cinnamon saplings', 'I noticed small leaf mites on my Ceylon Cinnamon sapling. Do you recommend a chemical spray or organic neem oil solution?', 'open', NULL, NULL, NULL);

-- Educational Blog Posts Seed
INSERT INTO blog_posts (author_id, title, slug, category, content) VALUES
(2, 'Monsoon Gardening in Kegalle: Drainage & Fungus Control', 'monsoon-gardening-kegalle', 'Seasonal Guides', 'During heavy seasonal rains in the Sabaragamuwa region, ensure potting soil contains perlite and coarse river sand to prevent root rot in potted indoor flora. Elevated stands prevent waterlogging.'),
(2, '5 Essential Indoor Plants for Natural Air Purification', 'indoor-plants-air-purification', 'Plant Care Tips', 'Snake Plants and Peace Lilies absorb benzene and formaldehyde from newly painted rooms and indoor spaces with minimal sunlight demands.');
