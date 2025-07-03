# Kinvo - Simple PHP Invoice System

A self-hosted, mobile-friendly invoice system built with vanilla PHP, Tailwind CSS, and MySQL. Perfect for small businesses and freelancers.

## Features

✅ **Complete Invoice Management**
- Create invoices with dynamic line items
- Auto-generate invoice numbers (INV-0001, INV-0002, etc.)
- Professional invoice templates with print support
- Public invoice links for customers

✅ **Payment Tracking**
- Log payments from multiple methods (Zelle, Venmo, Cash App, Cash, Check)
- Automatic status updates (Unpaid → Partial → Paid)
- Payment history tracking

✅ **Admin Dashboard**
- Overview of unpaid invoices and monthly payments
- Recent activity and quick actions
- Payment method breakdown

✅ **Customer Management**
- Add new customers or select from existing
- Store contact information (name, email, phone)

✅ **Business Settings**
- Customize business information
- Set default payment instructions
- Easy configuration management

## Installation

### 1. Upload Files
Upload all files to your web hosting account (works great with shared hosting like Hostinger).

### 2. Database Setup
1. Create a MySQL database
2. Import the `database_schema.sql` file:
   ```bash
   mysql -u your_username -p your_database < database_schema.sql
   ```

### 3. Configuration
Edit `includes/config.php` and update:
- Database connection details
- Admin password (change from 'changeme123')
- Site URL

```php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Admin password (change this!)
define('ADMIN_PASSWORD', 'your_secure_password');

// Site settings
define('SITE_URL', 'https://yourdomain.com');
```

### 4. File Permissions
Ensure your web server can read all files:
```bash
chmod -R 644 *
chmod -R 755 admin/ public/ includes/
```

## Usage

### Admin Access
1. Visit `https://yourdomain.com/admin/login.php`
2. Enter your admin password
3. Start creating invoices!

### Creating Invoices
1. Go to **Dashboard** → **Create Invoice**
2. Add customer information (new or existing)
3. Add line items with descriptions, quantities, and prices
4. Set payment instructions and due date
5. Click **Create Invoice**

### Sharing Invoices
- Each invoice gets a unique public URL
- Share the link with customers for viewing/printing
- No login required for customers

### Logging Payments
1. Go to **Payments** page
2. Select the invoice
3. Choose payment method and amount
4. Payment automatically updates invoice status

## File Structure

```
Kinvo/
├── admin/
│   ├── dashboard.php      # Admin dashboard
│   ├── create-invoice.php # Create new invoices
│   ├── invoices.php       # List all invoices
│   ├── payments.php       # Payment management
│   ├── settings.php       # Business settings
│   ├── login.php          # Admin login
│   └── logout.php         # Admin logout
├── public/
│   └── view-invoice.php   # Public invoice view
├── includes/
│   ├── config.php         # Configuration
│   ├── db.php            # Database connection
│   └── functions.php      # Utility functions
├── database_schema.sql    # Database setup
└── index.php             # Redirects to admin
```

## Database Tables

- **customers** - Customer information
- **invoices** - Invoice details and totals
- **invoice_items** - Line items for each invoice
- **payments** - Payment records
- **business_settings** - Business configuration

## Tech Stack

- **PHP** (vanilla, no frameworks)
- **MySQL** database
- **Tailwind CSS** (via CDN)
- **Minimal JavaScript** (for dynamic forms)

## Design System

The application follows a clean, minimal design system inspired by the login page:

### Color Scheme
- **Primary Background**: `bg-gray-50` (light gray background)
- **Card Backgrounds**: `bg-white` with `border-gray-200` borders
- **Primary Buttons**: `bg-gray-900` with `hover:bg-gray-800`
- **Secondary Buttons**: `bg-gray-700` with `hover:bg-gray-600`
- **Text Colors**: `text-gray-900` (primary), `text-gray-600` (secondary)
- **Icons**: `text-gray-600` for consistency

### Component Styling
- **Border Radius**: `rounded-lg` for all cards and buttons
- **Shadows**: `shadow-sm` for subtle depth
- **Borders**: `border-gray-200` for all containers
- **Typography**: `font-semibold` for buttons and labels
- **Spacing**: Consistent padding and margins using Tailwind's spacing scale

### Interactive Elements
- **Hover States**: Gray tone variations (`hover:bg-gray-100`, `hover:text-gray-700`)
- **Focus States**: Standard gray focus rings
- **Transitions**: `transition-colors` for smooth interactions

This design system ensures consistency across all pages while maintaining a professional, clean appearance.

## Security Notes

1. **Change the admin password** in `config.php`
2. **Set proper file permissions** on your server
3. **Use HTTPS** for your domain
4. **Regular backups** of your database

## Browser Support

- Works on all modern browsers
- Mobile-responsive design
- Print-friendly invoice layout

## Support

This system is designed to be simple and self-contained. For customization:

1. Edit the CSS classes (Tailwind)
2. Modify database schema if needed
3. Update business logic in PHP files

## License

Open source - modify as needed for your business!

---

**Perfect for**: Handyman services, lawn care, freelancers, small service businesses
**Hosting**: Works on any shared hosting with PHP and MySQL