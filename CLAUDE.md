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