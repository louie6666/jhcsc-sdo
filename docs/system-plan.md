-- 1. ROLES (For Security & Permissions)
CREATE TABLE Roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(20) UNIQUE -- 'Admin', 'Staff', 'Student'
);

-- 2. USERS (Staff/Admins)
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (role_id) REFERENCES Roles(role_id)
);

-- 3. CATEGORIES
CREATE TABLE Categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL
);

-- 4. EQUIPMENT (With Hard Constraints)
CREATE TABLE Equipment (
    equipment_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    image_url VARCHAR(255),
    storage_location VARCHAR(100),
    total_qty INT DEFAULT 0,
    available_qty INT DEFAULT 0,
    damaged_qty INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    FOREIGN KEY (category_id) REFERENCES Categories(category_id),
    -- SAFETY: Prevents the system from ever going below 0
    CONSTRAINT chk_qty_non_negative CHECK (available_qty >= 0 AND total_qty >= 0)
);

-- 5. BORROWERS (No more redundant strings in the Header)
CREATE TABLE Borrowers (
    borrower_id INT PRIMARY KEY AUTO_INCREMENT,
    id_number VARCHAR(50) UNIQUE NOT NULL, -- School ID
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    contact_no VARCHAR(20)
);

-- 6. TRANSACTION HEADERS (The Session)
CREATE TABLE Transaction_Headers (
    header_id INT PRIMARY KEY AUTO_INCREMENT,
    borrower_id INT,
    issued_by_staff_id INT, -- Who gave the item
    borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Open', 'Completed') DEFAULT 'Open',
    FOREIGN KEY (borrower_id) REFERENCES Borrowers(borrower_id),
    FOREIGN KEY (issued_by_staff_id) REFERENCES Users(user_id)
);

-- 7. TRANSACTION ITEMS (The Fine Detail)
CREATE TABLE Transaction_Items (
    item_record_id INT PRIMARY KEY AUTO_INCREMENT,
    header_id INT,
    equipment_id INT,
    due_date DATETIME NOT NULL,
    item_status ENUM('Borrowed', 'Returned', 'Damaged', 'Lost', 'Exchanged') DEFAULT 'Borrowed',
    return_date DATETIME,
    return_condition VARCHAR(50),
    received_by_staff_id INT, -- Who took the item back? (Accountability)
    exchange_note TEXT,
    FOREIGN KEY (header_id) REFERENCES Transaction_Headers(header_id),
    FOREIGN KEY (equipment_id) REFERENCES Equipment(equipment_id),
    FOREIGN KEY (received_by_staff_id) REFERENCES Users(user_id)
);

-- 8. MAINTENANCE (Now supports non-borrower damage)
CREATE TABLE Maintenance (
    maintenance_id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT,
    item_record_id INT NULL, -- Can be NULL if item was found broken on shelf
    date_reported TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issue_description TEXT,
    repair_status ENUM('Pending', 'In-Repair', 'Fixed', 'Scrapped') DEFAULT 'Pending',
    FOREIGN KEY (equipment_id) REFERENCES Equipment(equipment_id),
    FOREIGN KEY (item_record_id) REFERENCES Transaction_Items(item_record_id)
);

Key Upgrades & "Mentor" Critique
Normalization: I extracted Borrowers into its own table. If "John Doe" borrows items 50 times, his name is stored once, not 50 times. This is the difference between a "School Project" and "Software Engineering."

The received_by_staff_id: This is your accountability "Win." If a ball comes back popped and nobody noticed until later, you now know exactly which staff member accepted that return.

ENUMs over VARCHARs: For item_status and repair_status, I used ENUM. This forces the database to only accept specific words (e.g., you can't accidentally type "Returnd" with a typo).

The CHECK Constraint: available_qty >= 0. This is your insurance policy. If your code tries to subtract 1 from 0, the Database will throw an error and stop the transaction. No more "phantom inventory."

 The "Secret Sauce": Database Triggers
To make this truly "Best Version," don't do the math in PHP or JavaScript. Let the database do it.

Example Logic for your Instructor:

"I implemented an After-Insert Trigger on the Transaction_Items table. Every time a row is added (an item is borrowed), the Equipment table’s available_qty automatically decreases by 1. This ensures the inventory is always accurate, even if the application layer lags."

 Final Verdict: Ready to Build?
Yes. This schema is defensible. It handles:

Multiple Items per borrower session.

Staff Accountability for both lending and receiving.

Data Integrity through Foreign Keys and Constraints.

Maintenance Tracking linked directly to the person who broke it.

:root {
        /* Colors & Layout */
        --equipment-bg: #ecefec;
        --equipment-font-color: #000000;
        --equipment-hover: #8faadc;
        --equipment-buttons: #0c1f3f;
        --equipment-border-color: #ffffff;
        --equipment-radius: 8px;

        /* Font Sizes */
        --equipment-fs-label: 12px;
        --equipment-fs-button: 14px;
        --equipment-fs-info: 16px;
        --equipment-fs-title: 20px;

        /* Font Weights */
        --equipment-fw-normal: 400;
        --equipment-fw-bold: 700;
    }

    :root {
    /* Colors */
    --saas-bg: #f8fafc;
    --saas-card: #ffffff;
    --saas-border: #e2e8f0;
    --saas-text: #0f172a;
    --saas-muted: #64748b;
    --saas-primary: #3b82f6;
    --saas-primary-hover: #2563eb;
    --saas-success: #10b981;
    --saas-danger: #ef4444;
    --saas-warning: #f59e0b;
  

    /* Layout */
    --saas-radius: 8px;
    --saas-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --saas-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);

    /* Spacing */
    --saas-spacing: 1rem;

    /* Typography */
    --saas-font-main: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --saas-font-size-label: 12px;
    --saas-font-size-button: 14px;
    --saas-font-size-info: 14px;
    --saas-font-size-title: 20px;
    --saas-font-weight-normal: 400;
    --saas-font-weight-medium: 500;
    --saas-font-weight-semibold: 600;
    --saas-font-weight-bold: 700;

    /* Transitions */
    --saas-transition: all 0.2s ease-in-out;

    /* Apply these classes to every new table module */
.seis-table th { padding: 10px 20px; }
.seis-table td { padding: 8px 20px; }
.seis-btn-sm { padding: 4px 10px; font-size: 11px; width: fit-content; }
}