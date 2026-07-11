# SOFTVOTE - New Features Documentation

## Overview
This document details the newest functionalities added to the SOFTVOTE system, including security enhancements, audit logging, database backup capabilities, and improved user authentication.

---

## 🔐 1. OTP (One-Time Password) Verification System

### Location
- **Main Files**: `student/verify_otp.php`, `student/resend_otp.php`
- **Supporting**: `student/forgot_password.php`, `student/reset_password.php`

### Purpose
Nag-provide ng secure password reset mechanism gamit ang OTP verification para sa mga students na nakalimutan ang kanilang password.

### Key Features

#### A. OTP Generation & Validation
```php
// OTP Token Storage
- 6-digit numeric code
- Stored sa database na may expiration time
- One-time use only (marked as 'used' after verification)
- Database table: otp_tokens
```

#### B. Process Flow
1. **Request Phase** (`forgot_password.php`)
   - Student enters student ID
   - System generates 6-digit OTP
   - OTP sent via email (integrated with PHPMailer)
   - OTP stored sa database na may timestamp

2. **Verification Phase** (`verify_otp.php`)
   - Student enters OTP received sa email
   - System validates:
     - OTP format (exactly 6 digits)
     - OTP existence sa database
     - OTP hasn't been used yet
     - OTP hasn't expired
   - Sets session flag `otp_verified = true`

3. **Reset Phase** (`reset_password.php`)
   - Student sets bagong password
   - Password hashed gamit ang bcrypt
   - Account updated sa database
   - Redirects to login

#### C. Security Mechanisms
- OTP tokens expire after configurable time (default: 15 minutes)
- Each OTP can only be used once (marked as `used = 1`)
- IP address logging para sa security audit
- Session validation sa each step

### Database Schema
```sql
CREATE TABLE otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    token VARCHAR(6) NOT NULL,
    used TINYINT DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);
```

### Usage Example
```
1. Student clicks "Forgot Password" on login page
2. Enters student ID → receives OTP via email
3. Enters OTP sa verify_otp.php → verified
4. Sets bagong password → redirected to login
```

---

## 📊 2. Audit Logging & Activity Tracking

### Location
- **Main Files**: `superadmin/pages/activity_logs.php`
- **Helper Functions**: `superadmin/includes/audit_helpers.php`
- **Actions Logged**: `superadmin/superadmin_actions.php`

### Purpose
Nag-maintain ng complete system activity trail para sa compliance, security auditing, at troubleshooting.

### Key Features

#### A. Audit Log Structure
```php
function writeAuditLog(
    $conn,
    $actor_type,      // 'superadmin', 'admin', 'student'
    $actor_id,        // User ID
    $actor_name,      // User full name
    $action,          // ACTION_NAME (e.g., 'STUDENT_CREATED', 'ELECTION_STARTED')
    $target_type,     // Type ng affected entity ('student', 'candidate', 'admin', etc.)
    $target_id,       // ID ng affected entity
    $detail           // Additional context
): void
```

#### B. Tracked Actions
| Action | Target Type | Details |
|--------|------------|---------|
| `ADMIN_CREATED` | admin | New admin account created |
| `ADMIN_DELETED` | admin | Admin account deleted |
| `ADMIN_UPDATED` | admin | Admin profile updated |
| `STUDENT_IMPORTED` | student | Bulk student import |
| `STUDENT_DELETED` | student | Student account deleted |
| `ELECTION_STARTED` | election | Voting period initiated |
| `ELECTION_ENDED` | election | Voting period closed |
| `RESULTS_VIEWED` | results | Results accessed |
| `DATABASE_BACKUP_JSON` | backup | JSON backup exported |
| `DATABASE_BACKUP_SQL` | backup | SQL dump exported |
| `SETTINGS_CHANGED` | settings | System settings modified |

#### C. Database Schema
```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL,      -- 'superadmin', 'admin', 'student'
    actor_id VARCHAR(20) NOT NULL,        -- User ID
    actor_name VARCHAR(100) NOT NULL,     -- User full name
    action VARCHAR(50) NOT NULL,          -- Action code
    target_type VARCHAR(50),              -- Entity type affected
    target_id VARCHAR(50),                -- Entity ID affected
    detail TEXT,                          -- Additional context
    ip_address VARCHAR(45),               -- IPv4 or IPv6
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id, created_at),
    INDEX idx_action (action, created_at),
    INDEX idx_timestamp (created_at)
);
```

#### D. Activity Logs Page Features
- **Pagination**: 10 logs per page
- **Ordering**: Latest activities first (DESC by created_at)
- **Columns Displayed**:
  - Actor name at type
  - Action performed
  - Target details
  - IP address
  - Timestamp
- **Superadmin-only Access**: Protected by `superadmin_auth_guard.php`

### Implementation Example
```php
// Logging student deletion
writeAuditLog(
    $conn,
    'admin',
    $admin_id,
    'John Doe',
    'STUDENT_DELETED',
    'student',
    $student_id,
    'Student removed from election'
);
```

---

## 💾 3. Database Backup System

### Location
- **SQL Backup**: `superadmin/superadmin_backup_sql.php`
- **JSON Backup**: `superadmin/superadmin_backup_json.php`

### Purpose
Nag-provide ng comprehensive database backup options para sa disaster recovery at data preservation.

### A. SQL Backup Feature

#### Functionality
- **Format**: Standard MySQL SQL dump format
- **Contents**: 
  - CREATE TABLE statements para sa lahat ng tables
  - INSERT statements para sa lahat ng data
  - DROP TABLE IF EXISTS para sa safe restoration
- **Configuration**:
  - FOREIGN_KEY_CHECKS disabled during restore
  - Data batched sa 500 rows per INSERT para sa performance
  - UTF-8 encoding support (utf8mb4)

#### File Output
```sql
-- ============================================================
-- SOFTVOTE Database Backup
-- Generated : 2026-05-02 14:30:00
-- Generated by: Super Admin (ID: admin_001)
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- Table: students
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` ( ... );
LOCK TABLES `students` WRITE;
INSERT INTO `students` VALUES
(...),
(...);
UNLOCK TABLES;
```

#### Security
- Only GET requests allowed (idempotent)
- Superadmin authentication required
- IP address recorded sa audit logs
- Automatic download initiated

### B. JSON Backup Feature

#### Functionality
- **Format**: JSON (Pretty-printed)
- **Structure**:
  ```json
  {
    "meta": {
      "generated_at": "2026-05-02 14:30:00",
      "generated_by": "Super Admin (ID: admin_001)",
      "system": "SOFTVOTE"
    },
    "tables": {
      "students": [...],
      "administrators": [...],
      "candidates": [...],
      ...
    }
  }
  ```
- **Advantages**:
  - More portable across systems
  - Easier programmatic access
  - Human-readable format
  - Supports Unicode without special configuration

#### Audit Logging
```php
// Automatic logging sa activity_logs
INSERT INTO activity_logs (
    admin_id,
    action,
    details,
    ip_address,
    created_at
) VALUES (
    superadmin_id,
    'DATABASE_BACKUP_JSON',
    'Super Admin exported a JSON database backup.',
    ip_address,
    NOW()
)
```

#### Restoration Guide
```bash
# SQL Backup
mysql -u user -p database_name < softvote_backup_20260502_143000.sql

# JSON Backup (requires custom script to parse JSON)
# Can be imported via admin panel or custom import utility
```

---

## 👥 4. Session Management & Monitoring

### Location
- **Main Page**: `superadmin/pages/sessions.php`
- **Database Fields**: `last_login`, `last_activity` sa superadmins at administrators tables

### Purpose
Nag-monitor ng active user sessions, online status, at user activity para sa security at troubleshooting.

### Key Features

#### A. Active Sessions Tracking
```php
UNION Query combining:
1. Superadmins na may last_activity
2. Administrators na active at hindi deleted na may last_activity
Order by: latest activity first
```

#### B. Session Information Displayed
| Field | Description | Source |
|-------|-------------|--------|
| User ID | Unique identifier | superadmins/administrators table |
| Name | Full name | Database record |
| Username | Login username | Database record |
| Last Login | Last login timestamp | `last_login` field |
| Last Activity | Last detected activity | `last_activity` field |
| Role | User type | superadmin or admin |
| Online Status | Real-time status | Calculated: if `last_activity` < 5 mins |

#### C. Online Status Calculation
```php
$idle = time() - strtotime($s['last_activity']);
$is_online = ($idle < 300);  // Less than 5 minutes = online
```

#### D. Database Schema
```sql
-- Add to superadmins table
ALTER TABLE superadmins ADD COLUMN last_login DATETIME NULL;
ALTER TABLE superadmins ADD COLUMN last_activity DATETIME NULL;

-- Add to administrators table
ALTER TABLE administrators ADD COLUMN last_login DATETIME NULL;
ALTER TABLE administrators ADD COLUMN last_activity DATETIME NULL;
```

#### E. Session Update Logic
```php
// Update sa every page load or action
$update_query = "UPDATE superadmins SET last_activity = NOW() WHERE id = ?";
// Or for administrators
$update_query = "UPDATE administrators SET last_activity = NOW() WHERE id = ?";
```

### Features
- **Online Count**: Real-time count ng users online (active within 5 minutes)
- **Idle Detection**: Automatically marks inactive users
- **Role-based Display**: Shows both superadmin at admin sessions
- **Superadmin-only Access**: Protected view

---

## 🔑 5. Enhanced Password Management

### Location
- **Change Password**: `student/change_password.php`, `student/process_change_password.php`
- **Forgot Password**: `student/forgot_password.php`
- **Reset Password**: `student/reset_password.php`, `student/resend_otp.php`

### Purpose
Nag-provide ng comprehensive password security features para sa students at admins.

### Key Features

#### A. Force Password Change on First Login
```php
// In login_auth.php
if ((int)$student['is_new_user'] === 1) {
    $_SESSION['pending_student_id'] = $student['id'];
    header("Location: ../student/change_password.php");
    exit();
}
```

#### B. Password Validation Rules
- **Minimum Length**: 8 characters (configurable)
- **Complexity**:
  - At least 1 uppercase letter
  - At least 1 lowercase letter
  - At least 1 digit
  - At least 1 special character (!@#$%^&*)
- **Format Check**: No consecutive repeating characters
- **History**: Cannot reuse last 3 passwords

#### C. Password Hashing
```php
// Using bcrypt algorithm
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

// Verification
if (password_verify($input_password, $stored_hash)) {
    // Correct
}
```

#### D. Forgot Password Flow
1. Student enters student ID
2. System sends OTP via email
3. Student verifies OTP
4. Student sets bagong password
5. Redirects to login

---

## 🛡️ 6. Security Enhancements

### A. Session Security
- **Session Regeneration**: ID regenerated after successful login
- **IP Binding**: Optional IP address verification
- **Timeout**: Auto-logout after 30 minutes of inactivity
- **CSRF Protection**: Token validation sa forms

### B. Authentication Guards
```php
// File-level guard
require_once 'superadmin_auth_guard.php';
require_once 'admin_auth_guard.php';

// Checks:
- Session existence
- User ID validation
- Role verification
- Redirect to login if failed
```

### C. Input Validation
- **Prepared Statements**: Para sa lahat ng database queries
- **Input Sanitization**: Removal ng harmful characters
- **Type Casting**: Integer at string type checks
- **Email Validation**: Format at domain checking

### D. Audit Trail
- **IP Logging**: All actions logged na may IP address
- **Timestamp**: Complete audit trail na may timestamps
- **Actor Information**: Full user details recorded
- **Action Details**: Context at affected entities

---

## 📈 Integration Summary

### Table Dependencies
```
students
  ↓ (OTP verification)
  → otp_tokens
  ↓ (Password reset)
  → activity_logs (audit trail)

superadmins / administrators
  ↓ (Session tracking)
  → last_login, last_activity columns
  ↓ (Actions performed)
  → audit_logs

All tables
  ↓ (Backup system)
  → JSON/SQL exports
```

---

## 🚀 Usage Guidelines

### For Superadmins

#### Viewing Audit Logs
1. Navigate to Superadmin Dashboard
2. Click "Audit Logs" sa sidebar
3. View paginated activity trail
4. Filter by date, actor, or action as needed

#### Creating Database Backup
1. Go to Settings → Database Backup
2. Choose format:
   - **SQL**: Standard MySQL dump (recommended para sa restoration)
   - **JSON**: Portable format (recommended para sa analysis)
3. Click "Download Backup"
4. File automatically downloads

#### Monitoring Sessions
1. Navigate to "Active Sessions" sa dashboard
2. View online users at idle times
3. Monitor suspicious activity patterns
4. Check last activity timestamps

### For Students

#### Changing Password on First Login
1. System automatically redirects sa `change_password.php`
2. Enter bagong password na sumusunod sa requirements
3. Confirm password
4. Click "Update Password"
5. Redirects to student dashboard

#### Forgot Password Recovery
1. Click "Forgot Password" sa login page
2. Enter student ID
3. System sends 6-digit OTP sa email
4. Enter OTP sa verification page
5. Set bagong password
6. Login na may bagong password

---

## 🔍 Troubleshooting

### OTP Issues
- **OTP not received**: Check email spam folder, verify email sa system
- **OTP expired**: Request new OTP gamit ang "Resend OTP" button
- **OTP invalid**: Ensure 6-digit format, try again

### Audit Log Issues
- **No logs appearing**: Check `audit_logs` table existence, verify write permissions
- **Missing actions**: Ensure `writeAuditLog()` called sa relevant action files
- **Slow query performance**: Add indexes on `actor_id`, `action`, `created_at` columns

### Backup Issues
- **Export fails**: Check file system permissions, verify database connectivity
- **File too large**: Consider splitting backup o archiving into compressed format
- **Restore errors**: Ensure target database empty, check SQL syntax

---

## 📋 Implementation Checklist

- [x] OTP system implemented at tested
- [x] Audit logging integrated sa key actions
- [x] Database backup system (SQL at JSON)
- [x] Session monitoring at tracking
- [x] Enhanced password management
- [x] Security guards sa admin/superadmin pages
- [x] IP address logging sa audit trail
- [x] Email integration para sa OTP delivery

---

## 📝 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | May 2026 | Initial release ng new features documentation |

---

## 👤 Support

Para sa technical support o bug reports, contact ang development team.

**Last Updated**: May 2, 2026
