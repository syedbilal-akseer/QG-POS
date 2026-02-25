# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 11 POS (Point of Sale) and CRM system designed for pharmaceutical/FMCG distribution with deep Oracle EBS integration. The system manages orders, customers, pricing, field sales visits, and multi-location operations (Lahore and Karachi) with role-based access control and WhatsApp Business API integration.

## Technology Stack

- **Backend**: Laravel 11.9, PHP 8.2+
- **Databases**: MySQL (primary), Oracle EBS (via yajra/laravel-oci8)
- **Frontend**: Vite 5.0, Alpine.js 3.4, Tailwind CSS 3.4, Livewire 3
- **Admin UI**: Filament Tables & Widgets
- **Testing**: Pest PHP 2.0
- **Key Libraries**: Maatwebsite/Excel, DOMPDF/TCPDF/FPDF, Laravel Sanctum

## Development Commands

### Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Generate application key
php artisan key:generate

# Run migrations (ensure both MySQL and Oracle connections are configured)
php artisan migrate

# Seed database (if seeders exist)
php artisan db:seed
```

### Running the Application
```bash
# Start development server
php artisan serve

# Build frontend assets (development mode with hot reload)
npm run dev

# Build frontend assets (production)
npm run build
```

### Testing
```bash
# Run all tests with Pest
php artisan test

# Run specific test file
php artisan test tests/Feature/OrderTest.php

# Run tests with coverage
php artisan test --coverage

# Run specific test by name
php artisan test --filter=test_order_creation
```

### Code Quality
```bash
# Format code with Laravel Pint
vendor/bin/pint

# Check code style without fixing
vendor/bin/pint --test
```

### Oracle Sync Commands

**Recommended: Commands with automatic cache clearing**
```bash
# Sync ALL Oracle data at once (customers, products, prices, banks, users, etc.) + auto cache clear
php artisan sync:oracle-all

# Individual syncs with automatic cache clearing
php artisan sync:oracle-customers-clear
php artisan sync:oracle-products-clear
php artisan sync:oracle-item-price-clear
php artisan sync:oracle-banks-clear
php artisan sync:oracle-users-clear
```

**Original commands (manual cache clear needed)**
```bash
# Sync customers from Oracle
php artisan sync:oracle-customers

# Sync products/items from Oracle
php artisan sync:oracle-products

# Sync item prices from Oracle
php artisan sync:oracle-item-price

# Sync order types from Oracle
php artisan sync:oracle-order-types

# Sync banks from Oracle
php artisan sync:oracle-banks

# Sync users from Oracle HRMS
php artisan sync:oracle-users

# Sync warehouses from Oracle
php artisan sync:oracle-warehouses

# Push order to Oracle
php artisan sync:order-with-oracle {order_id}

# List available Oracle views
php artisan oracle:list-views

# IMPORTANT: If using original commands, clear API caches after sync
php artisan cache:clear-api
```

### Other Useful Commands
```bash
# Check WhatsApp configuration
php artisan whatsapp:check-config

# Clear API caches after Oracle sync (IMPORTANT: Run after syncing data)
php artisan cache:clear-api

# Clear all caches
php artisan optimize:clear

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache
```

## Architecture Overview

### Database Architecture

**Dual Database System:**
- **MySQL**: Primary application database with tables for users, orders, customers, items, prices, visits, etc.
- **Oracle**: EBS production system accessed via `yajra/laravel-oci8` for read/write operations

**Sync Pattern**: Oracle → MySQL → Application → Oracle
- Data flows from Oracle to MySQL via sync commands
- Application reads/writes to MySQL for performance
- Orders can be pushed back to Oracle via sync commands

**Key MySQL Tables:**
- `users` - User accounts with role-based access
- `customers` - Denormalized Oracle customer data with `ou_id` for location filtering
- `orders`, `order_items` - Order management with status tracking
- `item_prices` - Price list matrix (Karachi/Lahore × Corporate/Wholesale/Trade)
- `visits`, `day_tour_plans`, `monthly_tour_plans` - Field force CRM tracking
- `invoices`, `customer_receipts` - Financial documents

**Oracle Mirror Models**: Located in `app/Models/Oracle/` namespace
- `OracleCustomer`, `OracleItem`, `OracleItemPrice`
- `OracleOrderHeader`, `OracleOrderLine`
- `OracleBankMaster`, `OracleWarehouse`
- These use the `oracle` database connection defined in `config/database.php`

### Directory Structure

```
app/
├── Actions/              # Action classes for complex operations (OrderExportAction)
├── Console/Commands/     # Artisan commands, primarily Oracle sync operations
├── Enums/               # PHP Enums (OrderStatusEnum, RoleEnum)
├── Exports/             # Excel export classes (OrdersExport, PriceListsExport)
├── Filament/            # Filament admin panel resources and exporters
├── Helpers/             # Global helper functions (helpers.php, transform.php)
├── Http/
│   ├── Controllers/     # Controllers organized by domain (Admin, Api, Auth)
│   ├── Middleware/      # Custom middleware (checkRole for RBAC)
│   └── Requests/        # Form validation requests
├── Imports/             # Excel import logic (PriceListImport)
├── Livewire/            # Real-time reactive components
│   ├── CRM/            # Customer visits, plans, expenses, sales team
│   ├── Sidebar/        # Navigation components
│   └── Widgets/        # Dashboard widgets
├── Models/              # Eloquent models (~40+ models)
│   └── Oracle/         # Oracle database mirror models
├── Services/            # Business logic services (WhatsAppService, BankService)
└── Traits/              # Reusable traits (NotifiesUsers)

resources/
├── css/                 # Tailwind CSS entry point
├── js/                  # Alpine.js and Vite config
└── views/
    ├── admin/          # Admin panel Blade templates
    ├── crm/            # CRM module views
    ├── auth/           # Authentication views
    ├── layouts/        # Layout components
    ├── components/     # Reusable Blade components
    └── livewire/       # Livewire component views

routes/
├── web.php             # Web routes with role-based middleware
├── api.php             # RESTful API endpoints (Sanctum auth)
├── auth.php            # Authentication routes (Breeze)
└── console.php         # Console command routes
```

### Core Features

#### Order Management
- Order creation with line-item details and warehouse assignment
- Auto-incrementing order numbers (starting at 202500) with transaction locking
- Status tracking: `pending` → `processing` → `completed` → `synced` → `entered`
- Location-based filtering (LHR/KHI via `ou_id`)
- Excel export via Filament Tables integration

#### Price Management
- Matrix format Excel import (products × price lists)
- Support for 7 price lists:
  - Karachi: Corporate (7010), Wholesale (7011), Trade (7012)
  - Lahore: Corporate (7007), Wholesale (7008), Trade (7009)
  - QG HBM (1116080)
- Price history tracking with previous_price comparison
- Oracle price sync with comparison reports

#### CRM Module
- Field force management with visit planning hierarchy:
  - Monthly tour plans → Day tour plans → Individual visits
- Visit reporting with findings, competitor analysis, attachments
- Expense tracking per visit
- Sales team hierarchy and reporting
- Tour plan approval workflow

#### WhatsApp Integration
- Invoice delivery via WhatsApp Business API
- Rate limiting (10 msgs/sec, 100 burst limit)
- Retry logic with exponential backoff
- Configuration in `config/whatsapp.php`
- Environment variables: `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_BUSINESS_ACCOUNT_ID`

### Authentication & Authorization

**Role-Based Access Control:**
- Roles: `admin`, `supply-chain`, `sales-head`, `cmd-khi`, `cmd-lhr`, `scm-lhr`, `price-uploads`
- Middleware: `checkRole` for route protection
- Role-specific dashboards and data access
- Location-based filtering (LHR/KHI users see filtered data based on `ou_id`)
  - `cmd-khi`: Access to Karachi receipts (OU IDs: 102, 103, 104, 105, 106)
  - `cmd-lhr`: Access to Lahore receipts (OU IDs: 108, 109)
  - `scm-lhr`: Access to Lahore warehouse orders (OU IDs: 108, 109)

**Auth System:**
- Laravel Breeze for session-based web authentication
- Laravel Sanctum for API token authentication
- Routes protected with `auth` and `checkRole` middleware

### API Endpoints (Sanctum-protected)

All API routes are prefixed with `/api` and require Sanctum token authentication:

**Authentication:**
- `POST /api/auth/login` - Get Sanctum token
- `POST /api/auth/logout` - Revoke token

**Orders:**
- `GET /api/orders` - List orders with filters
- `POST /api/orders/place` - Create new order
- `GET /api/orders/{id}` - Get order details
- `POST /api/orders/{id}/cancel` - Cancel order
- `GET /api/orders/export` - Export orders to Excel
- `GET /api/orders/history` - Order history

**Products:**
- `GET /api/products` - List products with search
- `GET /api/products/{id}` - Product details

**Customers:**
- `GET /api/customers` - List customers
- `GET /api/customers/{id}` - Customer details

**CRM:**
- `GET /api/visits` - List visits
- `POST /api/visits` - Create visit
- `GET /api/monthly-tour-plans` - Monthly plans
- `GET /api/day-tour-plans` - Daily plans
- `POST /api/expenses` - Track expenses
- `POST /api/attendance` - Mark attendance
- `POST /api/leaves` - Submit leave request

## Important Patterns and Conventions

### Order Number Generation
Orders use auto-incrementing numbers starting at 202500. The generation uses database transactions with `lockForUpdate()` to prevent race conditions:
```php
DB::transaction(function () {
    $lastOrder = Order::lockForUpdate()->latest('id')->first();
    $nextNumber = $lastOrder ? $lastOrder->order_number + 1 : 202500;
});
```

### Multi-Tenant Data Access via OU ID
The system supports multi-location operations (Lahore/Karachi) using the `ou_id` field:
- Customers, orders, and prices are filtered by `ou_id`
- Users typically have location-based access restrictions
- When querying data, always consider adding `->where('ou_id', $user->ou_id)` if location filtering is needed

### Oracle Connection Switching
When working with Oracle models:
```php
// Oracle models automatically use the 'oracle' connection
$oracleCustomer = OracleCustomer::where('customer_number', '12345')->first();

// Explicitly switch connections if needed
DB::connection('oracle')->table('table_name')->get();
```

### Excel Import/Export Patterns
- Use `Maatwebsite\Excel` for large file processing
- Implement chunking for memory efficiency (50 rows per batch)
- Track statistics: created, updated, errors
- Use `gc_collect_cycles()` after processing large batches

### Livewire Component Pattern
Livewire components in `app/Livewire/` follow these conventions:
- Use `NotifiesUsers` trait for Filament notifications
- Implement `#[Layout()]` attribute for layout selection
- Use `$this->dispatch()` for component communication
- Leverage Filament Tables for data display

### Helper Functions
Global helpers are autoloaded from `app/Helpers/`:
- `notify($message, $type)` - Send Filament notifications
- `formatOrderItems($items)` - Format order line items
- `monthlyTourPlan($data)` - Transform tour plan data

### Testing Patterns
- Use Pest PHP syntax (`it('description', function() {}`)
- Use `RefreshDatabase` trait for database tests
- Tests run on in-memory SQLite (configured in `phpunit.xml`)
- Mock external services (WhatsApp, Oracle) in tests

## Configuration Files

### Environment Variables
Key variables in `.env`:
```
# MySQL Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=qg_pos_tw

# Oracle Database (configure for EBS connection)
DB_ORACLE_HOST=
DB_ORACLE_PORT=1521
DB_ORACLE_DATABASE=
DB_ORACLE_USERNAME=
DB_ORACLE_PASSWORD=
DB_ORACLE_SERVICE_NAME=

# WhatsApp Business API
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=

# Session & Cache (use database for distributed systems)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### Database Configuration
The `config/database.php` includes:
- `mysql` - Primary application database
- `oracle` - Oracle EBS connection (oci8 driver)
- `sqlite` - Testing database (in-memory)

## Working with This Codebase

### When Adding New Features
1. Check if a Livewire component is appropriate for interactive features
2. Use existing Services (`WhatsAppService`, `BankService`) or create new ones for business logic
3. Follow the existing naming conventions (OrderStatusEnum, RoleEnum)
4. Add appropriate role-based middleware to routes
5. Consider `ou_id` filtering for multi-location features
6. Write Pest tests for new functionality

### When Working with Oracle Sync
1. Check existing sync commands in `app/Console/Commands/`
2. Use Oracle models from `app/Models/Oracle/` namespace
3. Wrap sync operations in DB transactions
4. Log sync results and handle errors gracefully
5. Consider performance - use chunking for large datasets
6. Update corresponding MySQL tables after Oracle sync

### When Modifying the API
1. All API routes should use Sanctum authentication
2. Use Form Requests for validation
3. Return consistent JSON responses
4. Consider rate limiting for sensitive endpoints
5. Document new endpoints in this file

### When Working with Prices
1. Price lists are hard-coded (7010-7012 for Karachi, 7007-7009 for Lahore, 1116080 for HBM)
2. Price imports expect matrix format (products × price lists)
3. Always track price history with `previous_price` field
4. Oracle is the source of truth for pricing
5. **IMPORTANT**: The `item_prices` table requires both `price_list_id` AND `price_list_name` to be set
   - Customer product search API uses BOTH fields for lookups (matches by ID OR name as fallback)
   - Oracle sync command (`sync:oracle-items-price`) sets both fields during sync
   - If price lookups return null, verify both fields are populated in the database

### When Handling Files
1. Excel: Use Maatwebsite/Excel with chunking for large files
2. PDF: Multiple libraries available (DOMPDF, TCPDF, FPDF) - choose based on requirements
3. Store uploads in appropriate disk (configured in `config/filesystems.php`)
4. Consider file size limits for WhatsApp (100MB max)

## Common Gotchas

1. **Order Numbers**: Never manually set order numbers - use the locked transaction pattern
2. **Oracle Connection**: Oracle connection may timeout on long operations - handle reconnection
3. **OU Filtering**: Always filter by `ou_id` when displaying location-specific data
4. **Price List IDs**: These are hard-coded and must match Oracle configuration
5. **WhatsApp Rate Limits**: Respect the 10 msg/sec limit to avoid API blocks
6. **Large Excel Files**: Always use chunking to prevent memory exhaustion
7. **Livewire Updates**: Use `wire:key` for dynamic lists to prevent rendering issues
8. **Role Checks**: Test features with different roles to ensure proper access control
