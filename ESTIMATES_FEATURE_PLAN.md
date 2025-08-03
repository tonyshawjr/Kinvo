# Estimates Feature Implementation Plan

## Overview

This document outlines the complete plan for adding an Estimates feature to Kinvo. The feature will allow creating quotes/estimates that can be converted to invoices when approved.

## Core Principles

1. **Zero Risk to Existing Data** - Completely separate from invoices
2. **Full Rollback Capability** - Complete cleanup function included
3. **Gradual Rollout** - Can be tested with select customers first
4. **Familiar Interface** - Mirrors invoice workflow for easy adoption

## Feature Requirements

### Must-Have Features

1. **Estimate Management**
   - Create new estimates (similar to invoice creation)
   - Edit draft estimates
   - Delete estimates
   - View estimate list
   - Estimate numbering system (EST-YYYYMM## instead of invoice format)
   - Same line item types as invoices: Labor, Mileage, Materials

2. **Line Item Features (Same as Invoices)**
   - **Add Labor** - Hours × Hourly Rate (uses customer's custom rate if set)
   - **Add Mileage** - Miles × Mileage Rate
   - **Add Materials** - Quantity × Price per unit
   - Pre-filled rates from business settings
   - Automatic calculations
   - Same UI/UX as invoice creation

3. **Estimate States**
   - Draft (can be edited)
   - Sent (locked from editing)
   - Approved (ready to convert)
   - Rejected (archived)
   - Expired (past validity date)

4. **Conversion to Invoice**
   - One-click convert approved estimate to invoice
   - Link between estimate and invoice
   - Preserve all line items and amounts
   - Cannot convert rejected/expired estimates
   - Shows "Created from Estimate EST-202501" on invoice

5. **Customer Access & Approval**
   - **Public View Link** - No login required (like invoices)
   - **Approve/Reject Buttons** - Right on the estimate view
   - **Email Notifications** - Optional, controlled by user
   - **Customer Portal Integration** - See all estimates with status
   - **One-Click Actions** - Approve/Reject from email or portal
   - Clear "ESTIMATE" branding (not invoice)

6. **Email Options (User Controlled)**
   - **Send on Creation** - Checkbox "Email estimate to customer"
   - **Manual Only** - Default is NOT to email
   - **Email Contains**:
     - Estimate summary
     - Direct link to view
     - Approve/Reject buttons in email
     - Expiration date warning

7. **Validity Period**
   - Choose expiration when creating (7, 14, 30, 60, 90 days)
   - Default 30 days (configurable in settings)
   - Auto-expire old estimates
   - Warning when nearing expiration

### Customer Portal Enhancements

1. **Estimates Tab** - Next to Invoices tab
2. **Status Display** - Draft, Pending, Approved, Rejected, Expired
3. **Actions Available**:
   - View estimate details
   - Approve (if pending)
   - Reject (if pending)
   - Download/Print
4. **History** - Show who approved/rejected and when

### Nice-to-Have Features (Phase 2)

- Track estimate views/opens
- Multiple versions/revisions
- Comparison between versions
- Estimate templates
- Automated follow-ups
- Convert partial estimates (select line items)

## Database Design

### New Tables (No changes to existing tables)

```sql
-- Estimates main table
CREATE TABLE IF NOT EXISTS estimates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    property_id INT NULL,
    estimate_number VARCHAR(20) UNIQUE,
    date DATE NOT NULL,
    expires_date DATE NOT NULL,
    status ENUM('Draft', 'Sent', 'Approved', 'Rejected', 'Expired') DEFAULT 'Draft',
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5, 2) DEFAULT 0,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    terms TEXT,
    unique_id VARCHAR(32) UNIQUE,
    converted_invoice_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (property_id) REFERENCES customer_properties(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    INDEX idx_estimate_number (estimate_number),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_date)
);

-- Estimate line items
CREATE TABLE IF NOT EXISTS estimate_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estimate_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE,
    INDEX idx_estimate (estimate_id)
);

-- Estimate activity log
CREATE TABLE IF NOT EXISTS estimate_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estimate_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE,
    INDEX idx_estimate (estimate_id),
    INDEX idx_created (created_at)
);
```

## Implementation Plan

### Phase 1: Core Structure (Day 1)
1. Create database tables
2. Add estimates menu item
3. Create basic estimates list page
4. Implement create estimate form

### Phase 2: Management Features (Day 2)
1. Edit/Delete functionality
2. Status management
3. Expiration logic
4. Public view page

### Phase 3: Conversion Feature (Day 3)
1. Convert to invoice function
2. Link estimates to invoices
3. Prevent duplicate conversions
4. Update UI to show connections

### Phase 4: Polish & Settings (Day 4)
1. Settings page for estimate defaults
2. Cleanup/removal functionality
3. Testing and refinement
4. Documentation

## Workflow Examples

### Example 1: Manual Estimate (No Email)

1. User creates estimate for customer
2. User chooses NOT to email (default)
3. User prints/saves PDF
4. User handles delivery manually
5. Customer approves verbally
6. User converts to invoice

### Example 2: Email Estimate Flow

1. User creates estimate
2. User checks "Email to customer"
3. Customer receives email with:
   - Estimate summary
   - "View Estimate" button
   - "Approve" and "Reject" buttons
4. Customer clicks "View Estimate"
5. Sees full estimate with approve/reject buttons
6. Clicks "Approve"
7. User notified and converts to invoice

### Example 3: Portal Approval Flow

1. Customer logs into portal
2. Sees "Estimates" tab with pending estimate
3. Reviews estimate details
4. Approves from portal
5. Estimate status updates
6. User converts to invoice when ready

## Complete Cleanup/Rollback Function

### In Settings Page

```php
// Complete removal of estimates feature
function removeEstimatesFeature($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Drop tables in correct order (dependencies first)
        $pdo->exec("DROP TABLE IF EXISTS estimate_activity");
        $pdo->exec("DROP TABLE IF EXISTS estimate_items");
        $pdo->exec("DROP TABLE IF EXISTS estimates");
        
        // Remove any settings related to estimates
        $pdo->exec("DELETE FROM business_settings WHERE setting_key LIKE 'estimate_%'");
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
```

### Settings UI

- Add "Estimates Feature" section
- Include "Remove Estimates Feature" button
- Require confirmation (type "DELETE ESTIMATES")
- Show count of estimates that will be deleted
- One-click complete removal

### Additional Settings

- **Default Expiration** - 7, 14, 30, 60, 90 days
- **Email by Default** - Yes/No (default: No)
- **Estimate Numbering** - Prefix (EST, QUOTE, etc.)
- **Allow Online Approval** - Yes/No
- **Auto-Convert on Approval** - Yes/No

## File Structure

### New Files

```
/admin/
  estimates.php           (list all estimates)
  create-estimate.php     (new estimate form with labor/mileage/materials)
  edit-estimate.php       (edit existing draft estimates)
  estimate-detail.php     (view single estimate)
  convert-estimate.php    (conversion handler)
  estimate-approve.php    (handle approval/rejection)

/public/
  view-estimate.php       (customer view with approve/reject buttons)
  estimate-action.php     (handle customer approve/reject)

/client/
  estimates.php           (list estimates in portal)

/includes/
  estimate-functions.php  (helper functions)
  estimate-email.php      (email templates)
```

### Modified Files:
- `/admin/dashboard.php` - Add estimates widget
- `/admin/settings.php` - Add estimates settings
- `/includes/header.php` - Add estimates menu
- `/database_schema.sql` - Add estimate tables

## Risk Mitigation

1. **Separate Namespace** - All estimate tables/files clearly named
2. **No Foreign Keys to Estimates** - Invoices don't depend on estimates
3. **Soft Dependencies** - Can remove feature without breaking invoices
4. **Settings Toggle** - Can disable feature without removing
5. **Complete Cleanup** - One button removes all traces

## Success Criteria

1. Can create and manage estimates
2. Customers can view estimates via link
3. Approved estimates convert to invoices
4. Complete removal leaves no trace
5. No impact on existing invoice functionality

## Key Decisions Made

1. **Numbering Format** - EST-YYYYMM## (clearly different from invoices)
2. **Default Expiration** - 30 days (configurable)
3. **Customer Approval** - Yes, via email link or portal
4. **Email Default** - NO (user must opt-in per estimate)
5. **Portal Integration** - Full integration with approve/reject
6. **Line Items** - Exact same as invoices (Labor/Mileage/Materials)

## Questions Still to Consider

1. Should expired estimates auto-notify the user?
2. Should customers be able to request changes to estimates?
3. Should we track how many times an estimate is viewed?
4. Should approved estimates auto-convert or require manual conversion?
5. Should we allow partial estimate conversion?

## Next Steps

1. Review and approve this plan
2. Create backup of current database
3. Implement Phase 1 on test environment
4. Test thoroughly before production
5. Deploy with cleanup option ready

---

## Important Safety Features

1. **Complete Isolation** - Estimates don't modify invoice tables
2. **One-Click Removal** - Settings button removes everything
3. **No Dependencies** - Invoices work fine without estimates
4. **Gradual Testing** - Can test with one customer first
5. **Manual Override** - Can handle everything offline if needed

**Note**: This feature is designed to be completely removable. If it doesn't work out, one click in settings will remove all traces of the estimates feature without affecting any existing invoice data.