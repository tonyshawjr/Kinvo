# Production CSS Loading Issues - Troubleshooting Guide

## Problem
Styles are not loading on the production site at https://pay.capefearlawn.com/admin/customers.php

## Root Causes Identified

### 1. Content Security Policy (CSP) Blocking Inline Styles
**Issue**: The Content Security Policy was not allowing inline styles, which Tailwind CSS requires to function properly.

**Solution Applied**: Modified `includes/functions.php` to always allow inline styles in the CSP:
```php
// Always allow inline styles since we're using Tailwind CSS which requires it
$styleSources[] = "'unsafe-inline'";
```

### 2. CDN Access Issues
The application relies on external CDNs:
- Tailwind CSS: `https://cdn.tailwindcss.com`
- Font Awesome: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css`

**Potential Issues**:
- Server firewall blocking outgoing HTTPS requests
- SSL/TLS certificate verification failures
- DNS resolution problems on the server

## Diagnostic Tools Created

### 1. Debug Styles Page
**Location**: `/admin/debug-styles.php`
**URL**: https://pay.capefearlawn.com/admin/debug-styles.php

This page will help diagnose:
- Whether headers are being sent correctly
- HTTPS/SSL status
- CDN connectivity from the server
- Whether Tailwind and Font Awesome load correctly
- Browser console errors

### 2. Test Page Without CSP
**Location**: `/admin/customers-test.php`
**URL**: https://pay.capefearlawn.com/admin/customers-test.php

This is a copy of customers.php with CSP disabled to test if CSP was the issue.

## Steps to Resolve

1. **Deploy the Fixed Code**
   - Upload the modified `includes/functions.php` file to production
   - This should resolve the CSP blocking issue

2. **Test the Debug Page**
   - Visit https://pay.capefearlawn.com/admin/debug-styles.php
   - Check all the diagnostic information
   - Look for any red error messages

3. **Check Browser Console**
   - Open Developer Tools (F12)
   - Go to Console tab
   - Look for CSP violations or network errors
   - Common errors:
     - "Refused to load the stylesheet because it violates the Content Security Policy"
     - "Failed to load resource: net::ERR_BLOCKED_BY_RESPONSE"

4. **If CDNs Are Blocked**
   Consider downloading and hosting the CSS files locally:
   ```bash
   # Download Tailwind CSS
   curl https://cdn.tailwindcss.com -o assets/css/tailwind.js
   
   # Download Font Awesome
   curl https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css -o assets/css/font-awesome.min.css
   ```

5. **Check .htaccess Headers**
   The `.htaccess` file has security headers that might conflict. Temporarily comment out the header section to test:
   ```apache
   # <IfModule mod_headers.c>
   #     ... header directives ...
   # </IfModule>
   ```

## Alternative Solutions

### Option 1: Host CSS Locally
Instead of relying on CDNs, download and host the files locally:

1. Create `/assets/css/` directory
2. Download Tailwind and Font Awesome
3. Update all PHP files to reference local files:
   ```html
   <script src="/assets/css/tailwind.js"></script>
   <link href="/assets/css/font-awesome.min.css" rel="stylesheet">
   ```

### Option 2: Use a Build Process
For production, consider using a proper Tailwind CSS build process:
1. Install Node.js and npm
2. Set up Tailwind CSS with PostCSS
3. Build a minified CSS file with only used styles
4. Reference the built CSS file instead of the CDN

### Option 3: Modify Server Configuration
If the server is blocking outgoing HTTPS:
1. Contact hosting provider to whitelist CDN domains
2. Check if mod_security or similar WAF is blocking requests
3. Verify SSL/TLS settings allow TLS 1.2+

## Quick Fix Verification

After deploying the fix:
1. Clear browser cache (Ctrl+Shift+R)
2. Visit https://pay.capefearlawn.com/admin/customers.php
3. Styles should now load correctly
4. If not, check the debug page for more information

## Clean Up

Once the issue is resolved:
1. Delete `/admin/debug-styles.php` (security precaution)
2. Delete `/admin/customers-test.php` (temporary test file)
3. Keep this troubleshooting guide for future reference