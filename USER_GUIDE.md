# SimpleInvoice User Guide

Welcome to SimpleInvoice - your professional invoice management system. This guide will help you get started and make the most of all features.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Admin Dashboard](#admin-dashboard)
3. [Customer Management](#customer-management)
4. [Invoice Management](#invoice-management)
5. [Payment Tracking](#payment-tracking)
6. [Client Portal](#client-portal)
7. [Business Settings](#business-settings)
8. [Tips & Best Practices](#tips--best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Getting Started

### First Login
1. Navigate to `/admin/login.php`
2. Enter your admin password
3. Check "Keep me logged in" for convenience (optional)
4. Click **Sign In**

### Initial Setup
After your first login, immediately:
1. **Change Default Password** - Go to Business Settings
2. **Configure Business Info** - Add your company details
3. **Set Payment Instructions** - Configure how customers should pay
4. **Create Your First Customer** - Add a customer to test the system

---

## Admin Dashboard

### Overview Cards
The dashboard shows key metrics at a glance:
- **Total Invoices** - Number of invoices created
- **Total Revenue** - Sum of all invoice amounts
- **Outstanding Balance** - Amount still owed
- **This Month's Activity** - Current month's performance

### Quick Actions
- **Create Invoice** - Start a new invoice immediately
- **Add Customer** - Register new customers
- **Record Payment** - Log received payments
- **View Reports** - Access financial summaries

### Recent Activity
Monitor your latest:
- Invoices created
- Payments received
- Customer additions

---

## Customer Management

### Adding Customers

1. Click **Customers** → **Add Customer**
2. Fill in required information:
   - **Customer Name** (required)
   - **Email Address** (for client portal access)
   - **Phone Number**
   - **Billing Address**
3. Click **Save Customer**

### Customer Properties

For service businesses, track multiple locations per customer:

1. Go to **Customers** → Select customer → **Properties**
2. Click **Add Property**
3. Enter:
   - **Property Name** (e.g., "Main Office", "Warehouse")
   - **Address**
   - **Property Type** (Residential, Commercial, etc.)
4. Click **Save Property**

### Setting Up Client Portal Access

To give customers portal access:
1. Edit customer and ensure **email is entered**
2. Click **Generate Client PIN**
3. Share the PIN with your customer
4. They can access their account at `/client/login.php`

---

## Invoice Management

### Creating Invoices

1. Click **Invoices** → **Create Invoice**
2. **Select Customer** (or create new)
3. **Choose Property** (if applicable)
4. **Set Invoice Details:**
   - Invoice Date (auto-filled)
   - Due Date
   - Invoice Number (auto-generated)

### Adding Line Items

For each service or product:
1. Click **Add Line Item**
2. Enter:
   - **Description** (detailed service description)
   - **Quantity** (hours, units, etc.)
   - **Rate** (price per unit)
   - **Amount** (calculated automatically)

### Tax Configuration

- Set **Tax Rate** (as percentage, e.g., 8.5 for 8.5%)
- Tax is calculated automatically on subtotal
- Leave at 0% if no tax applies

### Invoice Notes

Add internal notes that appear on the invoice:
- Payment terms
- Special instructions
- Work details

### Saving Invoices

- **Save Draft** - Save without finalizing
- **Save & Send** - Complete and ready for customer

### Invoice Status

Invoices automatically show status:
- **Unpaid** - No payments received
- **Partial** - Some payment received
- **Paid** - Fully paid

---

## Payment Tracking

### Recording Payments

1. Go to **Payments** → **Record Payment**
2. **Select Invoice** from dropdown
3. **Enter Payment Details:**
   - **Amount** (can be partial payment)
   - **Payment Date**
   - **Payment Method** (Cash, Check, Credit Card, etc.)
   - **Notes** (check number, transaction ID, etc.)
4. Click **Record Payment**

### Payment Methods Available

- Cash
- Check
- Credit Card
- Bank Transfer
- Zelle
- Venmo
- Cash App
- PayPal

### Viewing Payment History

- **By Invoice** - Go to invoice details to see all payments
- **All Payments** - Navigate to Payments section for complete history
- **Customer Payments** - View from customer profile

---

## Client Portal

### What Customers Can Do

When customers log in at `/client/login.php`:

**Dashboard View:**
- See account summary (total billed, paid, balance)
- View recent invoices and payments
- Get alerts for overdue invoices

**Invoice Access:**
- View all invoices online
- Print professional invoices
- See payment history per invoice
- Check current balance

**Quick Payments:**
- Access Cash App links (if configured)
- Access Venmo links (if configured)
- View payment instructions

### Sharing Invoices with Customers

Each invoice has a unique public link:
1. Open any invoice
2. Copy the URL from `/public/view-invoice.php?id=XXXXX`
3. Share this link with customers
4. No login required for customers to view

---

## Business Settings

### Company Information

Configure your business details:
- **Business Name** (appears on invoices)
- **Phone Number**
- **Email Address**
- **EIN/Tax ID** (optional)

### Payment Instructions

Set default payment instructions that appear on all invoices:
```
Example:
Payment due within 30 days of invoice date.
Make checks payable to: Your Business Name
Mail to: Your Business Address
Or pay online using the links below.
```

### Digital Payment Setup

**Cash App Integration:**
1. Enter your Cash App username (without $)
2. Customers will see direct payment links with invoice amount

**Venmo Integration:**
1. Enter your Venmo username (without @)
2. Customers get direct payment links

### Security Settings

**Admin Password:**
- Change regularly
- Use strong passwords (8+ characters, mixed case, numbers)
- Don't share with unauthorized users

**Client Portal:**
- Monitor client access logs
- Reset PINs if compromised
- Review client activity regularly

---

## Tips & Best Practices

### Invoice Organization

**Numbering System:**
- Keep sequential numbering (auto-generated)
- Don't skip numbers for accounting purposes
- Use prefixes if needed (2024-001, 2024-002)

**Timing:**
- Send invoices promptly after work completion
- Set reasonable due dates (typically 15-30 days)
- Follow up on overdue invoices

### Customer Communication

**Professional Presentation:**
- Always use the print-optimized invoice view
- Include detailed descriptions of work performed
- Be clear about payment terms

**Payment Reminders:**
- Check dashboard regularly for overdue invoices
- Contact customers promptly about overdue amounts
- Offer payment plans if needed

### Data Management

**Regular Backups:**
- Backup your database monthly
- Export customer and invoice data regularly
- Keep copies of important records

**Record Keeping:**
- Document all payments immediately
- Keep notes about customer interactions
- Save important email communications

---

## Troubleshooting

### Common Issues

**Can't Log In:**
- Verify password is correct
- Check if you're on the right login page (/admin/login.php)
- Clear browser cache and cookies
- Contact system administrator

**Invoice Not Displaying Correctly:**
- Try refreshing the page
- Clear browser cache
- Check if all required fields are filled
- Verify customer information is complete

**Payment Not Recording:**
- Ensure invoice is selected correctly
- Check that payment amount is valid number
- Verify payment date format
- Make sure payment method is selected

**Client Portal Issues:**
- Verify customer email is entered correctly
- Check that PIN was generated and shared
- Ensure customer is using correct login page (/client/login.php)
- Confirm PIN hasn't expired

### Getting Help

**Error Messages:**
- Note the exact error message
- Check what action caused the error
- Try logging out and back in
- Contact technical support if issue persists

**Data Problems:**
- Never delete records without backup
- Contact administrator before making major changes
- Keep records of any data issues

### Best Security Practices

**Admin Access:**
- Always log out when finished
- Don't share login credentials
- Use strong, unique passwords
- Keep browser updated

**Client Data:**
- Protect customer information
- Don't share invoice links publicly
- Monitor for unauthorized access
- Update client PINs regularly

---

## Support & Updates

### Getting Support
- Check this user guide first
- Document any error messages
- Note steps to reproduce issues
- Contact your system administrator

### System Updates
- Backup before any updates
- Test new features in safe environment
- Review security updates promptly
- Keep system current with latest version

### Feature Requests
- Document desired functionality
- Explain business need for feature
- Provide examples of expected behavior
- Submit through proper channels

---

**SimpleInvoice** - Professional invoice management made simple.

*Last updated: July 2025*