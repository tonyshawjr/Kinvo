# Rate Limiting Configuration

This application implements comprehensive rate limiting to prevent brute force attacks and abuse.

## Rate Limiting Settings

### Admin Login (`admin/login.php`)
- **Endpoint**: `admin_login`
- **Max Attempts**: 5 failed attempts
- **Time Window**: 15 minutes
- **Block Duration**: 30 minutes

### Client Login (`client/login.php`)
- **Endpoint**: `client_login`
- **Max Attempts**: 5 failed attempts
- **Time Window**: 15 minutes
- **Block Duration**: 30 minutes

### PIN Reset (`client/forgot-pin.php`)
- **Endpoint**: `pin_reset`
- **Max Attempts**: 3 failed attempts (stricter)
- **Time Window**: 15 minutes
- **Block Duration**: 60 minutes (longer due to sensitivity)

### Public Invoice Access (`public/view-invoice.php`)
- **Endpoint**: `public_invoice`
- **Max Attempts**: 20 failed attempts (more generous for public access)
- **Time Window**: 15 minutes
- **Block Duration**: 60 minutes

## How Rate Limiting Works

1. **IP-based Tracking**: Uses client IP address as identifier
2. **Proxy Support**: Handles X-Forwarded-For headers securely
3. **Database Storage**: Uses `rate_limits` table for persistence
4. **Automatic Cleanup**: Removes entries older than 24 hours
5. **Fail Open**: If rate limiting fails, access is allowed for availability

## Rate Limiting Features

- **Per-endpoint tracking**: Different limits for different functions
- **Secure logging**: All rate limit events are logged
- **Block duration**: Progressive blocking with time-based cooldowns
- **Success reset**: Successful attempts clear rate limit counters
- **HTTP 429 responses**: Proper status codes for rate limit exceeded

## Security Benefits

- Prevents brute force password attacks
- Reduces automated abuse of authentication endpoints
- Protects against invoice enumeration attempts
- Provides detailed logging for security monitoring
- Maintains service availability while blocking attackers

## Monitoring

Rate limiting events are logged to `/logs/app_[date].log` with the following information:
- Timestamp
- User type (Admin/Client/Anonymous)
- IP address
- Endpoint accessed
- Number of attempts
- Block duration

This enables security monitoring and analysis of attack patterns.