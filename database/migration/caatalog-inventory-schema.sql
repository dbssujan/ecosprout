
-- 1. Soft Deactivation (Hides from catalog, preserves order history)
ALTER TABLE plants 
ADD COLUMN is_active TINYINT(1) DEFAULT 1 COMMENT '1 = Active/Visible, 0 = Deactivated/Hidden';

-- 2. Image Path Storage (Allows PHP unlink() to delete physical files)
ALTER TABLE plants 
ADD COLUMN image_url VARCHAR(255) DEFAULT 'assets/img/default_plant.png' COMMENT 'Relative path to the uploaded image file';

-- 3. Botanical & Care Specifications
ALTER TABLE plants 
ADD COLUMN botanical_name VARCHAR(150) COMMENT 'Scientific name (e.g., Sansevieria Trifasciata)',
ADD COLUMN care_sunlight VARCHAR(50) DEFAULT 'Full Sun' COMMENT 'Sunlight requirement (e.g., Partial Shade)',
ADD COLUMN care_water VARCHAR(50) DEFAULT '2-3 Times a Week' COMMENT 'Watering frequency';

-- 4. Inventory Tracking (Caps cart quantities and triggers UI badges)
ALTER TABLE plants 
ADD COLUMN stock_quantity INT DEFAULT 0 COMMENT 'Current available inventory count';

-- 5. Establish the Foreign Key constraint for categories (if not already set)
-- NOTE: Make sure the 'category_id' column exists first!
ALTER TABLE plants 
ADD CONSTRAINT fk_plant_category 
FOREIGN KEY (category_id) REFERENCES categories(id) 
ON DELETE RESTRICT;