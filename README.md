# Jewellery ERP Management System

A complete, modern, user-friendly Jewellery ERP and Point of Sale (POS) Management System built with **Core PHP 8+**, **MySQL**, **Bootstrap 5**, and **Chart.js**.

Designed specifically for jewellery retail shops, showrooms, and wholesalers to manage billing, inventory, customers, daily metal rates, employees, and financial statements from one centralized platform.

---

## Key Features & Modules

### 1. Dashboard (`/modules/dashboard/`)
- Real-time KPI statistics: Today's Total Sales, Total Revenue, Total Expenses, Net Profit, Registered Customers, Inventory Pieces, and Low Stock Alerts.
- Live Bullion Rates Ticker Banner showing daily Gold 24K, 22K, 18K, Silver 999, and Platinum rates.
- Dynamic Charts powered by **Chart.js**:
  - Monthly Sales vs. Expenses bar chart for the entire year.
  - Sales Overview by metal type (Gold, Silver, Diamond, Platinum) doughnut chart.
- Recent Invoices table and Low Stock warning cards with quick restock actions.
- Asynchronous notifications bell indicator with auto-refresh.

### 2. Billing / Point of Sale (POS) (`/modules/billing/`)
- Fast, intuitive jewellery POS counter interface.
- Customer management:
  - Instant autocomplete search by name or phone.
  - "+ New Customer" quick modal that registers customer and auto-selects them via AJAX without leaving the billing screen.
  - Live customer credit check showing outstanding balances.
- Product selection:
  - Scan barcode or search by item code / name with live autocomplete.
  - Custom / manual bespoke jewellery item line generator.
- Dynamic weight and charges calculations:
  - Gross Weight, Stone Weight, and Net Weight (`Net = Gross - Stone`).
  - Auto-applied metal rate per gram based on selected metal and purity (24K, 22K, 18K, 14K, 999 Silver, 925 Silver, Platinum).
  - Making charges calculator (per gram, percentage of metal value, or fixed lump sum).
  - Wastage charge percentage calculation.
  - Stone / Diamond charges.
- Real-time billing summary:
  - Metal value, making charges, wastage charges, and stone charges.
  - Discount engine (percentage or fixed amount in ₹).
  - GST tax calculation (standard 3% jewellery tax, supporting intra-state CGST 1.5% + SGST 1.5% or inter-state IGST 3%).
  - Automatic round off.
  - Auto-generated Indian Numbering Amount in Words (e.g. *One Lakh Fifty One Thousand Rupees Only*).
- Payment processing:
  - Multi-method support: Cash, UPI (GPay/PhonePe/Paytm), Debit/Credit Card, Bank Transfer (NEFT/RTGS), Cheque.
  - Full and partial payment support with automatic calculation of outstanding due balance.
  - Transaction reference number tracking.
- **Atomic Database Transaction**:
  - Auto-generates unique sequential invoice number (e.g. `INV-001001`).
  - Automatically deducts physical inventory stock quantity.
  - Records a stock movement entry in `inventory_transactions` linked to the invoice.
  - Updates customer lifetime purchases and credit balance.
  - Writes to immutable `audit_logs`.

### 3. Inventory Management (`/modules/inventory/`)
- Centralized catalogue for all jewellery designs and ornaments.
- Search by SKU code, barcode, or name; filter by Category, Metal Type, and Stock Status (All, In Stock, Low Stock, Out of Stock).
- Add & Edit products with technical specifications:
  - Metal Type, Purity, Category, Supplier / Karigar, Gross/Net/Stone weights, Making charge rules, Wastage %, Cost Price, Selling Price.
  - Low stock notification threshold.
  - Product image upload.
- **Stock Movement Log** (`/modules/inventory/transactions.php`):
  - Complete history of all inventory events (Initial Stock In, POS Sales deductions, Returns, Audit adjustments, Cancel reversals) with timestamp, previous stock, new stock, and user.
- **Stock In / Out / Audit Set** (`/modules/inventory/stock_adjust.php`):
  - Manual stock arrivals, removals, or physical audit adjustments with required notes.
- **Category Manager** (`/modules/inventory/categories.php`):
  - Group ornaments by Gold Necklace, Rings, Bangles, Earrings, Chains, Diamond Sets, Silver Utensils, etc.

### 4. Customer Management (`/modules/customers/`)
- Customer directory with search and credit filters.
- Personal, contact, billing address, PAN, and GSTIN records.
- **Customer 360 Profile** (`/modules/customers/view.php`):
  - Lifetime purchase volume and current outstanding credit balance.
  - Complete historical purchase invoice archive with payment status.
  - Customer payment ledger tracking every payment made.

### 5. Employee & Staff Management (`/modules/employees/`)
- Staff directory with designation, department, contact info, salary, and status.
- Role-based permissions:
  - **Admin**: Full system access, settings, metal rates, financial P&L, employee management, invoice cancellation.
  - **Employee**: Daily shop operations (POS billing, customer lookup, inventory browsing).
- **Attendance Register** (`/modules/employees/attendance.php`):
  - Daily staff check-in / check-out tracker.
  - Mark status: Present, Absent, Half Day, Leave with daily counters and notes.

### 6. Revenue & Expense Management (`/modules/finance/`)
- Accrual-based financial statements avoiding double counting of sales and payments.
- **Profit & Loss Statement** (`/modules/finance/index.php`):
  - Operating Revenue (Invoiced jewellery sales + non-sales income).
  - Operational Expenses breakdown by category.
  - Net Profit / Loss calculation.
  - Cash flow breakdown by payment method (Cash, UPI, Card, Net Banking).
  - Cash realization progress vs. credit receivables.
- **Daily Expense Tracker** (`/modules/finance/expenses.php`):
  - Record showroom rent, staff payroll, electricity, workshop tools, marketing, packaging.
  - Filter by category and date range.
- **Other Income / Advances** (`/modules/finance/revenue.php`):
  - Record order booking advances, jewellery repair fees, old gold melting margins, and appraisal charges.

### 7. Bill Printing & Invoices (`/modules/bill-history/`)
- **A4 Tax Invoice Layout**:
  - Full-page format with showroom logo, contact details, and GSTIN.
  - Customer billing address and tax credentials.
  - Gold/silver rates applied at the time of sale.
  - Itemized table with HSN codes, weights, purity, rate, making charges, and line totals.
  - GST breakdown (CGST, SGST, IGST).
  - Amount in words in Indian currency.
  - Standard hallmarking terms, return policies, buyback disclaimers, and authorized signature.
- **80mm Thermal Receipt Layout**:
  - Compact POS receipt format for thermal receipt printers.
- **Invoice Cancellation & Reversal** (`/modules/bill-history/cancel.php`):
  - Cancels finalized bills with mandatory reason.
  - **Automatic Stock Restoration**: Restores item quantities back into available inventory.
  - Logs `cancel_reversal` in the inventory transaction ledger.
  - Adjusts customer lifetime purchase and outstanding balance.
  - Invoices are never permanently deleted, preserving legal tax audit trails.
- **Due Payment Collection** (`/modules/bill-history/add_payment.php`):
  - Record subsequent full or partial payments against credit sales.

### 8. Settings & Daily Rates (`/modules/settings/`)
- **Metal Rates Manager** (`/modules/settings/metal_rates.php`):
  - Update daily bullion market rates: Gold 24K, 22K, 18K, Silver 999, 925, Platinum.
  - Historical rate modification log with date and user attribution.
- **Shop & Billing Settings** (`/modules/settings/index.php`):
  - Shop name, address, GSTIN, invoice prefix, default tax rate, low stock thresholds.
- **Security Audit Logs** (`/modules/settings/audit_logs.php`):
  - Immutable trail recording user actions, IP address, payload data, and timestamps.
- **User Profile & Security** (`/modules/settings/profile.php`):
  - Profile details and password change with `password_hash()`.

---

## Technology Stack

- **Backend**: Core PHP 8+ (No external frameworks, clean modular architecture, prepared PDO statements)
- **Database**: Single centralized MySQL database (`jewellery_erp`)
- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons
- **Data Visualization**: Chart.js 4.4
- **AJAX**: Vanilla JavaScript asynchronous operations for live search, customer quick-add, and notifications

---

## Directory Structure

```
POS- Jewellery/
├── assets/
│   ├── css/
│   │   ├── style.css             # Main responsive dashboard stylesheet
│   │   └── invoice.css           # A4 & 80mm thermal print layouts
│   ├── js/
│   │   └── app.js                # Shared AJAX helpers, notifications, toast
│   └── images/                   # Logos and icons
├── config/
│   └── database.php              # PDO connection singleton, helpers, settings
├── database/
│   └── jewellery_erp.sql         # Database schema (16 tables) and sample demo data
├── includes/
│   ├── auth.php                  # Session authentication & role-based access
│   ├── header.php                # Reusable top navigation, search, notifications
│   ├── sidebar.php               # Fixed left navigation bar with badge counters
│   └── footer.php                # Reusable footer & script includes
├── modules/
│   ├── dashboard/                # Analytics, KPI metrics, Chart.js feeds
│   ├── billing/                  # POS billing screen, calculations, atomic processor
│   ├── inventory/                # Products, stock in/out, movement history, categories
│   ├── customers/                # Customer directory, 360 profile, credit ledger
│   ├── employees/                # Staff directory, login roles, daily attendance register
│   ├── finance/                  # P&L statements, daily expenses, other revenue
│   ├── bill-history/             # Invoice history, A4/Thermal printing, cancellation
│   └── settings/                 # Shop config, daily metal rates, audit logs, profile
├── uploads/                      # Product images and expense receipts
├── index.php                     # Gateway redirect
├── login.php                     # Secure session login
├── logout.php                    # Logout & audit log
├── setup.php                     # Installation & database migration wizard
└── README.md                     # Documentation
```

---

## Default Login Credentials

| Role | Username | Password | Access Level |
|---|---|---|---|
| **Admin** | `admin` | `admin123` | Full access (Billing, Inventory, Staff, Finance, Settings, Audit) |
| **Sales Staff** | `employee` | `password123` | Daily operations (Billing POS, Inventory view, Customers) |

---

## How to Run & Install

### Option A: Using PHP Built-in Web Server
1. Ensure MySQL server is running (e.g. via XAMPP, MAMP, or `brew services start mysql`).
2. Open terminal in this folder and start PHP server:
   ```bash
   php -S localhost:8000
   ```
3. Open your browser and navigate to:
   ```
   http://localhost:8000/setup.php
   ```
4. Click **Initialize Database & Demo Records** to automatically create the `jewellery_erp` database and populate tables.
5. Log in with `admin` / `admin123`.

### Option B: Using XAMPP / WAMP / Apache
1. Copy the folder `POS- Jewellery` to your web server root (e.g. `htdocs/POS-Jewellery`).
2. Start Apache and MySQL in XAMPP control panel.
3. Open `http://localhost/POS-Jewellery/setup.php` in your browser.
4. Follow the setup wizard or import `database/jewellery_erp.sql` via phpMyAdmin.
5. Log in at `http://localhost/POS-Jewellery/login.php`.

