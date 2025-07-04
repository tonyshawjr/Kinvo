# Kinvo - Professional Invoice Management System

A simple, professional invoice management system designed for small businesses and freelancers. Built with PHP and MySQL, perfect for shared hosting environments.

## Features

- **Invoice Management**: Create, edit, and track invoices with professional layouts
- **Customer Management**: Store customer information and track their properties/locations
- **Payment Tracking**: Record and manage payments with multiple payment methods
- **Professional Design**: Clean, modern interface with print-optimized invoice layouts
- **Property Support**: Track multiple properties per customer (great for service businesses)
- **Dashboard Analytics**: Overview of recent activity and key metrics
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices

## Quick Installation (Shared Hosting)

1. **Upload Files**: Upload all files to your web hosting account
2. **Create Database**: Create a new MySQL database in your hosting control panel
3. **Run Installer**: Visit `http://yourdomain.com/install.php` in your browser
4. **Follow Wizard**: Complete the 4-step installation process
5. **Login**: Access your dashboard at `/admin/login.php`

### Default Login
- **Password**: `admin123` (change this immediately after installation)

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher  
- **Web Server**: Apache with mod_rewrite (or Nginx)
- **Storage**: Minimum 50MB disk space

## Installation Steps (Detailed)

### Step 1: Database Setup
1. In your hosting control panel, create a new MySQL database
2. Note down the database name, username, and password
3. The installer will create all necessary tables automatically

### Step 2: File Upload
1. Download/extract all Kinvo files
2. Upload to your web hosting account via FTP or file manager
3. Ensure all files maintain their directory structure

### Step 3: Run Installation Wizard
1. Navigate to `http://yourdomain.com/install.php`
2. Follow the 4-step installation process:
   - Welcome screen
   - Database configuration
   - Business information setup
   - Installation completion

### Step 4: Security
1. Delete `install.php` after successful installation
2. Change the default admin password immediately
3. Update your business settings in the admin panel

## Configuration

### Business Settings
Configure your business information in the admin panel:
- Business name, phone, and email
- Payment instructions
- Default hourly rates
- Tax settings

### Payment Methods
Supported payment methods:
- Cash
- Check  
- Credit Card
- Bank Transfer
- Zelle
- Venmo
- Cash App
- PayPal

## File Structure

```
kinvo/
├── admin/              # Admin dashboard and management
├── public/             # Public-facing pages (invoices)
├── includes/           # Core functions and configuration
├── database_schema.sql # Database structure
├── install.php         # Installation wizard
├── .htaccess          # Security and configuration
└── INSTALL.md         # Installation instructions
```

## Security Features

- Password hashing with PHP's `password_hash()`
- Session-based authentication
- SQL injection protection with prepared statements
- XSS protection with output escaping
- File access restrictions via `.htaccess`
- Admin-only access controls

## Backup & Maintenance

### Database Backup
Regularly backup your MySQL database through your hosting control panel or phpMyAdmin.

### File Backup
Backup the entire application directory, especially the `includes/config.php` file.

### Updates
Check for updates and security patches regularly. Always backup before updating.

## Support & Customization

This is a self-hosted solution designed for technical users comfortable with PHP/MySQL. 

### Customization
The system is built with modern PHP and uses Tailwind CSS for styling, making it easy to customize:
- Update colors and branding in the CSS classes
- Modify invoice layouts in `/public/view-invoice.php`
- Add custom fields to the database schema

### Common Issues
1. **500 Error**: Check file permissions (755 for directories, 644 for files)
2. **Database Connection**: Verify credentials in `includes/config.php`
3. **Email Issues**: Configure PHP mail settings with your hosting provider

## License

This software is provided as-is for personal and commercial use. Modify and distribute freely.

## Version History

- **v1.0**: Initial release with core invoice management features
- Professional invoice layouts with print support
- Customer and payment management
- Property/location tracking
- Modern responsive design

---

**Kinvo** - Simple, professional invoice management for small businesses.