# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Kinvo is a PHP-based invoice management system with separate admin and client portals. The application uses vanilla PHP with MySQL database, featuring a responsive design without any build tools or package managers.

## Development Commands

This is a vanilla PHP application without build tools. Common development tasks:

- **Run locally**: Use a local PHP server (e.g., XAMPP, MAMP, or `php -S localhost:8000`)
- **Database setup**: Import `database_schema.sql` into MySQL
- **Configuration**: Copy `includes/example_config.php` to `includes/config.php` and update database credentials
- **Installation**: Navigate to `/install.php` for initial setup

## Architecture

### Directory Structure
- `/admin/` - Admin portal for business management
- `/client/` - Customer-facing portal for invoice viewing
- `/includes/` - Shared PHP files (functions, database, config)
- `/assets/` - Static resources (CSS, JS, images)
- `/logs/` - Application and error logs

### Key Components

1. **Configuration Loading** (`includes/config_loader.php`):
   - Attempts to load config from secure location outside web root first
   - Falls back to local config if secure location unavailable
   - Use `SECURE_CONFIG_LOADER` constant before including

2. **Authentication**:
   - Admin: Session-based with remember token support
   - Client: PIN-based authentication for customers
   - Functions in `includes/functions.php`: `isAdmin()`, `requireAdmin()`

3. **Database Access** (`includes/db.php`):
   - PDO-based database connection
   - All queries use prepared statements for security

4. **Invoice System**:
   - Invoice numbers: YYYYMM## format (e.g., 20250801)
   - Unique IDs for public sharing via `unique_id` field
   - Status tracking: Unpaid, Partial, Paid

### Security Considerations

- CSRF protection on all forms
- SQL injection prevention via prepared statements
- XSS protection through output encoding
- Session security with secure cookies
- Rate limiting on login attempts
- Content Security Policy headers

### Entry Points

- `/index.php` - Redirects to admin login or installer
- `/admin/login.php` - Admin authentication
- `/client/login.php` - Customer portal access
- `/public/view-invoice.php` - Public invoice viewing (via unique_id)

## Important Notes

- No automated tests exist - manual testing required
- No linting or code formatting tools configured
- PHP 7.4+ required, 8.0+ recommended
- Always check `includes/.installed` file exists before running
- Configuration can be stored outside web root for security

## Estimates Feature

The estimates feature provides a complete quote/proposal system with customer approval workflow:

### Key Components
1. **Estimate Management** (`/admin/estimates.php`, `/admin/create-estimate.php`, `/admin/edit-estimate.php`)
   - Create professional estimates with line items
   - Track status: Draft → Sent → Approved/Rejected → Expired
   - Automatic estimate numbering (EST-YYYYMM##)
   - Expiration date tracking

2. **Customer Approval** (`/public/view-estimate.php`, `/public/estimate-action.php`)
   - Public view links for customer approval
   - One-click approve/reject functionality
   - Activity tracking for all actions

3. **Conversion to Invoice** (`/admin/convert-estimate.php`)
   - Convert approved estimates to invoices
   - Preserves all line items and details
   - Links estimate to invoice for tracking

4. **Client Portal Integration** (`/client/estimates.php`)
   - Customers can view their estimates
   - Approve/reject from their portal
   - Tracks approval history

5. **Settings & Cleanup** (`/admin/settings.php`)
   - Configure expiration defaults
   - Auto-cleanup old estimates
   - Customizable estimate numbering

### Database Tables
- `estimates` - Main estimate records
- `estimate_items` - Line items for each estimate
- `estimate_activity` - Activity tracking
- `estimate_settings` - Feature configuration

### Helper Functions (`/includes/estimate-functions.php`)
- `generateEstimateNumber()` - Creates sequential estimate numbers
- `getEstimate()` - Retrieves estimate with customer details
- `getEstimateItems()` - Gets line items for an estimate
- `logEstimateActivity()` - Tracks all estimate actions
- `getEstimateSettings()` - Retrieves configuration settings