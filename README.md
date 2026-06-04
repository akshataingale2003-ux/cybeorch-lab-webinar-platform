# CYBEORCH_LAB — Webinar & Bootcamp Platform
## Complete Technical Documentation

---

## 📁 Project Structure

```
CYBEORCH/
├── index.php                  ← Landing homepage
├── index.php                  ← User Registration & Trainee registration (popup on homepage)
├── login.php                  ← User Registration & Trainee login
├── logout.php                 ← Logout handler
├── dashboard.php              ← User Registration & Trainee dashboard
├── checkout.php               ← Razorpay checkout page
├── payment-success.php        ← Payment success page
├── wallet.php                 ← NxL Wallet & token history
├── contact.php                ← Contact form handler
├── database.sql               ← Full MySQL schema + seed data
├── .htaccess                  ← Apache security rules
│
├── includes/
│   ├── config.php             ← All configuration (DB, Razorpay, NxL)
│   ├── db.php                 ← PDO Database class
│   ├── helpers.php            ← Utility functions
│   ├── auth.php               ← Authentication module
│   └── payment.php            ← Razorpay payment module
│
├── api/
│   └── payment-verify.php    ← Razorpay webhook/verify endpoint
│
└── admin/
    ├── login.php              ← Admin login
    ├── logout.php             ← Admin logout
    ├── dashboard.php          ← Admin dashboard + analytics
    └── webinars.php           ← Webinar CRUD management
```

---

## 🚀 Day-by-Day Deployment Guide (ServerByte)

### DAY 1 — Project Setup & Database

1. **Login to ServerByte cPanel**
2. **Create MySQL Database:**
   - Go to cPanel → MySQL Databases
   - Create database: `CYBEORCH_db`
   - Create user: `CYBEORCH_user`
   - Assign user to DB with ALL PRIVILEGES
3. **Import Schema:**
   - Go to phpMyAdmin → select `CYBEORCH_db`
   - Click Import → upload `database.sql`
4. **Upload Files:**
   - Use File Manager or FTP (FileZilla)
   - Upload all files to `public_html/` or your subdomain folder
5. **Configure `includes/config.php`:**
   ```php
   define('DB_HOST', 'your_db_host');
   define('DB_USER', 'CYBEORCH_user');
   define('DB_PASS', 'your_strong_password');
   define('DB_NAME', 'CYBEORCH_db');
   define('SITE_URL', 'https://yourdomain.com');
   ```

---

### DAY 2 — Authentication Setup

1. **Hash Admin Password:**
   Run this PHP snippet once to generate admin password hash:
   ```php
   <?php echo password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]); ?>
   ```
2. **Update admin record in DB:**
   ```sql
   UPDATE admin SET password = '$2y$12$YOUR_HASH_HERE' WHERE username = 'CYBEORCH_admin';
   ```
3. **Test Login:**
   - User Registration & Trainee: `/index.php?register_required=1` → create account (registration popup)
   - Admin: `/admin/login.php` → username: `CYBEORCH_admin`, password: `Admin@123`

---

### DAY 3 — Webinar Module

1. Login as admin → `/admin/webinars.php`
2. Click "Add Webinar" and fill details
3. User registrations & trainees can register at `/webinars.php`
4. Free webinars: instant registration
5. Paid webinars: redirect to Razorpay checkout

---

### DAY 4 — Razorpay Integration

1. **Get Razorpay Keys:**
   - Login at https://dashboard.razorpay.com
   - Go to Settings → API Keys
   - Generate Key ID and Key Secret
2. **Update `includes/config.php`:**
   ```php
   define('RAZORPAY_KEY_ID',     'rzp_live_XXXXXXXXXXXXXXX');
   define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');
   ```
3. **Test Mode:** Use `rzp_test_` keys during development
4. **Webhook (optional):** Set webhook URL to `https://yourdomain.com/api/payment-verify.php`

---

### DAY 5 — Admin Dashboard

- URL: `/admin/dashboard.php`
- Features: Revenue stats, registrations, webinar management
- User Registration & Trainee management: `/admin/students.php`
- Payment reports: `/admin/payments.php`

---

### DAY 6 — NxL Wallet System

Wallet auto-credits on:
- Signup: 25 NxL tokens
- Webinar attendance: 15 NxL tokens
- Bootcamp enrollment: 100 NxL tokens
- Referral: 50 NxL per referred user

Configure in `includes/config.php`:
```php
define('NXL_SIGNUP_BONUS',    25);
define('NXL_REFERRAL_BONUS',  50);
define('NXL_WEBINAR_REWARD',  15);
define('NXL_BOOTCAMP_REWARD', 100);
```

---

### DAY 7 — Final Deployment & SSL

1. **Point Domain to ServerByte:**
   - Update nameservers or add A record to your hosting IP
2. **Enable SSL:**
   - cPanel → SSL/TLS → Let's Encrypt (free SSL)
   - Enable "Force HTTPS Redirect"
3. **Update config:**
   ```php
   define('SITE_URL', 'https://yourdomain.com');
   ```
4. **Set error_reporting to 0** in `config.php` for production:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```
5. **Test checklist:**
   - [ ] Homepage loads
   - [ ] User Registration & Trainee registration works
   - [ ] Login/logout works
   - [ ] Free webinar registration works
   - [ ] Razorpay checkout opens
   - [ ] Payment success flow works
   - [ ] NxL wallet credits correctly
   - [ ] Admin dashboard accessible
   - [ ] Admin can add/edit/delete webinars
   - [ ] Contact form submits

---

## 🔐 Security Checklist

- [x] PDO prepared statements (SQL injection prevention)
- [x] CSRF tokens on all forms
- [x] Password hashing with bcrypt (cost 12)
- [x] Session regeneration on login
- [x] HTTPOnly + Secure + SameSite cookies
- [x] Input sanitization via `sanitize()`
- [x] Admin routes protected with `requireAdminLogin()`
- [x] Razorpay signature verification
- [x] `.htaccess` blocks directory listing
- [x] Sensitive files blocked in `.htaccess`
- [x] XSS headers set via Apache

---

## 💳 Default Admin Credentials

```
URL:      /admin/login.php
Username: CYBEORCH_admin
Email:    admin@CYBEORCH.com
Password: Admin@123   ← CHANGE IMMEDIATELY after first login
```

---

## 🗄️ Database Tables Summary

| Table                  | Purpose                          |
|------------------------|----------------------------------|
| users                  | User Registration & Trainee accounts                 |
| admin                  | Admin accounts                   |
| webinars               | Webinar listings                 |
| bootcamps              | Bootcamp programs                |
| webinar_registrations  | Webinar seat bookings            |
| bootcamp_enrollments   | Bootcamp enrollments             |
| payments               | Razorpay payment records         |
| wallet                 | NxL token balances per user      |
| wallet_transactions    | NxL credit/debit history         |
| referrals              | Referral tracking                |
| attendance             | Webinar attendance records       |
| notifications          | In-app notifications             |
| contact_messages       | Contact form submissions         |

---

## 📦 Tech Stack

| Layer       | Technology                     |
|-------------|-------------------------------|
| Frontend    | HTML5, CSS3, Bootstrap 5.3    |
| Backend     | PHP 8+                        |
| Database    | MySQL (PDO)                   |
| Payment     | Razorpay Checkout API         |
| Hosting     | ServerByte Shared/VPS         |
| Security    | Apache .htaccess, CSRF, bcrypt|

---

## 📧 GitHub Daily Upload (10 PM)

```bash
git add .
git commit -m "Day X: [module name] - [brief summary]"
git push origin main
```

Recommended commit schedule:
- Day 1: `Initial setup, DB schema, homepage UI`
- Day 2: `User auth module - registration, login, sessions`
- Day 3: `Webinar management module`
- Day 4: `Razorpay payment integration`
- Day 5: `Admin dashboard with analytics`
- Day 6: `NxL wallet & token system`
- Day 7: `Deployment, SSL, final testing`

---

## 🎯 Evaluation Points Coverage

| Criteria              | Implementation                          |
|-----------------------|-----------------------------------------|
| Frontend Design       | Cyberpunk dark theme, Bootstrap 5, responsive |
| Backend Logic         | PHP 8, OOP classes, PDO, modular       |
| Database Structure    | 13 normalized tables, indexes, FKs     |
| Razorpay Integration  | Order creation, signature verification  |
| Wallet System         | NxL tokens, credits, debits, referrals |
| Security              | CSRF, hashing, prepared statements     |
| Deployment            | ServerByte + .htaccess + SSL ready     |
| Clean Code            | Separated concerns, helper functions   |
| UI/UX Quality         | Dark theme, animations, mobile-first   |
| Documentation         | This README                            |

---

*Built for CYBEORCH_LAB – Internal Technical Evaluation*  
*Powered By: CYBEORCH_LAB Ecosystem*
#   c y b e o r c h  
 #   c y b e o r c h  
 