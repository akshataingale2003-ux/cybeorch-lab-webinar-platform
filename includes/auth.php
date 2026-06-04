<?php
// ============================================
// CYBEORCH LAB - Authentication Module
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Auth {

    // Register new user registration & trainee
    public static function register(array $data): array {
        $name    = sanitize($data['full_name']);
        $email   = strtolower(trim($data['email']));
        $phone   = trim($data['phone'] ?? '');
        $pass    = $data['password'];
        $refCode = trim($data['referral_code'] ?? '');

        // Validate
        if (empty($name) || strlen($name) < 3)
            return ['success' => false, 'message' => 'Full name must be at least 3 characters.'];
        if (!isValidEmail($email))
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass))
            return ['success' => false, 'message' => 'Password must be 8+ chars with at least one uppercase and one number.'];
        if ($phone && !isValidPhone($phone))
            return ['success' => false, 'message' => 'Please enter a valid 10-digit Indian phone number.'];

        // Check email exists
        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing)
            return ['success' => false, 'message' => 'This email is already registered. Please login.'];

        // Optional referral code — credits referrer with NXL_REFERRAL_BONUS on successful signup
        $referrerId = null;
        $referredByCode = null;
        if ($refCode !== '') {
            $refLookup = strtoupper(preg_replace('/\s+/', '', $refCode));
            $referrer = db()->fetchOne(
                'SELECT id, email, referral_code FROM users WHERE UPPER(referral_code) = ? LIMIT 1',
                [$refLookup]
            );
            if (!$referrer || strtolower((string) $referrer['email']) === $email) {
                return ['success' => false, 'message' => 'Invalid Referral Code'];
            }
            $referrerId = (int) $referrer['id'];
            $referredByCode = (string) $referrer['referral_code'];
        }

        $hashedPass = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $myRefCode = generateReferralCode($name);
        $verifyToken = generateToken();

        require_once __DIR__ . '/admin-users.php';
        ensureAdminUsersSchema();
        $hasPlainCol = function_exists('adminTableHasColumn') && adminTableHasColumn('users', 'password_plain');

        if ($hasPlainCol) {
            $userId = db()->insert(
                'INSERT INTO users (full_name, email, phone, password, password_plain, referral_code, referred_by, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $email, $phone, $hashedPass, $pass, $myRefCode, $referredByCode, $verifyToken]
            );
        } else {
            $userId = db()->insert(
                'INSERT INTO users (full_name, email, phone, password, referral_code, referred_by, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$name, $email, $phone, $hashedPass, $myRefCode, $referredByCode, $verifyToken]
            );
        }

        // Create wallet
        db()->execute("INSERT INTO wallet (user_id, balance) VALUES (?, 0)", [$userId]);

        require_once __DIR__ . '/nxl-wallet.php';
        grantNxlReward($userId, 'signup_bonus', null, 'Welcome bonus NxL tokens!');

        if ($referrerId) {
            db()->execute('INSERT INTO referrals (referrer_id, referred_id) VALUES (?, ?)', [$referrerId, $userId]);
            processReferralRewardForReferredUser($userId, true);
        }

        sendNotification($userId, 'system', 'Welcome to CYBEORCH LAB! 🎉',
            "Hi {$name}, your account is created. You've received " . NXL_SIGNUP_BONUS . " NxL tokens as a welcome bonus!");

        require_once __DIR__ . '/form-submissions.php';
        recordFormSubmission([
            'form_key'          => 'platform-signup',
            'form_label'        => 'Platform signup',
            'source_page'       => 'login.php',
            'full_name'         => $name,
            'email'             => $email,
            'phone'             => $phone,
            'summary'           => 'New user account — ' . $myRefCode,
            'storage_table'     => 'users',
            'storage_record_id' => $userId,
        ]);

        return ['success' => true, 'message' => 'Account created successfully! Welcome to CYBEORCH LAB.', 'user_id' => $userId];
    }

    // Register freelancer profile (creates user account if not logged in)
    public static function registerFreelancer(array $data, ?int $loggedInUserId = null): array {
        $name     = sanitize($data['full_name'] ?? '');
        $email    = strtolower(trim($data['email'] ?? ''));
        $phone    = trim($data['phone'] ?? '');
        $role     = sanitize($data['primary_role'] ?? '');
        $expLevel = $data['experience_level'] ?? '';
        $skills   = trim($data['skills'] ?? '');
        $about    = trim($data['about'] ?? '');
        $location = sanitize($data['location'] ?? '');
        $portfolio = trim($data['portfolio_url'] ?? '');
        $github   = trim($data['github_url'] ?? '');
        $linkedin = trim($data['linkedin_url'] ?? '');
        $availability = $data['availability'] ?? '';
        $pass     = $data['password'] ?? '';

        $validRoles = ['developer', 'designer', 'cybersecurity', 'devops', 'qa', 'data', 'mobile', 'other'];
        $validExp   = ['fresher', '1-2', '3-5', '5+'];
        $validAvail = ['full_time', 'part_time', 'project_based'];

        if (empty($name) || strlen($name) < 3)
            return ['success' => false, 'message' => 'Full name must be at least 3 characters.'];
        if (!isValidEmail($email))
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        if ($phone && !isValidPhone($phone))
            return ['success' => false, 'message' => 'Please enter a valid 10-digit Indian phone number.'];
        if (!in_array($role, $validRoles, true))
            return ['success' => false, 'message' => 'Please select your primary role.'];
        if (!in_array($expLevel, $validExp, true))
            return ['success' => false, 'message' => 'Please select your experience level.'];
        if (!in_array($availability, $validAvail, true))
            return ['success' => false, 'message' => 'Please select your availability.'];
        if (strlen($skills) < 10)
            return ['success' => false, 'message' => 'Please list your skills (at least 10 characters).'];
        if (strlen($about) < 20)
            return ['success' => false, 'message' => 'Please write a brief introduction (at least 20 characters).'];

        foreach (['portfolio_url' => $portfolio, 'github_url' => $github, 'linkedin_url' => $linkedin] as $label => $url) {
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL))
                return ['success' => false, 'message' => 'Please enter a valid URL for ' . str_replace('_', ' ', $label) . '.'];
        }

        $existingFreelancer = db()->fetchOne('SELECT id FROM freelancer_registrations WHERE email = ?', [$email]);
        if ($existingFreelancer)
            return ['success' => false, 'message' => 'A freelancer application with this email already exists.'];

        $userId = $loggedInUserId;

        if ($userId) {
            $user = db()->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
            if (!$user)
                return ['success' => false, 'message' => 'Session expired. Please log in again.'];
            $existingByUser = db()->fetchOne('SELECT id FROM freelancer_registrations WHERE user_id = ?', [$userId]);
            if ($existingByUser)
                return ['success' => false, 'message' => 'You have already registered as a freelancer.'];
            $email = $user['email'];
            $name  = $user['full_name'];
            $phone = $phone ?: ($user['phone'] ?? '');
        } else {
            if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass))
                return ['success' => false, 'message' => 'Password must be 8+ chars with at least one uppercase and one number.'];

            $existingUser = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existingUser)
                return ['success' => false, 'message' => 'This email is already registered. Please log in and complete your freelancer profile.'];

            $account = self::register([
                'full_name'        => $name,
                'email'            => $email,
                'phone'            => $phone,
                'password'         => $pass,
                'referral_code'    => $data['referral_code'] ?? '',
                'agree_terms'      => $data['agree_terms'] ?? '',
            ]);
            if (!$account['success'])
                return $account;
            $userId = (int) $account['user_id'];
        }

        $freelancerId = db()->insert(
            'INSERT INTO freelancer_registrations (user_id, full_name, email, phone, primary_role, experience_level, skills, portfolio_url, github_url, linkedin_url, availability, location, about) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $name,
                $email,
                $phone ?: null,
                $role,
                $expLevel,
                $skills,
                $portfolio ?: null,
                $github ?: null,
                $linkedin ?: null,
                $availability,
                $location ?: null,
                $about,
            ]
        );

        sendNotification(
            $userId,
            'system',
            'Freelancer application received',
            "Hi {$name}, we received your freelancer registration. Our team will review your profile and contact you for suitable hands-on projects."
        );

        require_once __DIR__ . '/form-submissions.php';
        recordFormSubmission([
            'form_key'          => 'freelancer-registration',
            'form_label'        => 'Freelancer registration',
            'source_page'       => 'register-freelancer.php',
            'full_name'         => $name,
            'email'             => $email,
            'phone'             => $phone ?: null,
            'summary'           => ucfirst($role) . ' — ' . $expLevel,
            'payload'           => [
                'primary_role'      => $role,
                'experience_level'  => $expLevel,
                'availability'      => $availability,
                'location'          => $location,
            ],
            'storage_table'     => 'freelancer_registrations',
            'storage_record_id' => $freelancerId,
        ]);

        return [
            'success'       => true,
            'message'       => 'Freelancer registration submitted successfully!',
            'user_id'       => $userId,
            'freelancer_id' => $freelancerId,
        ];
    }

    /** Secure user registration & trainee session (registration, OTP login, popup register). */
    public static function establishUserSession(int $userId): bool
    {
        $user = db()->fetchOne(
            'SELECT id, full_name, email, COALESCE(is_blocked, 0) AS is_blocked FROM users WHERE id = ? AND deleted_at IS NULL',
            [$userId]
        );
        if (!$user) {
            return false;
        }
        if (!empty($user['is_blocked'])) {
            return false;
        }

        startSession();
        $csrf = $_SESSION['csrf_token'] ?? null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id']      = (int) $user['id'];
        $_SESSION['user_name']    = $user['full_name'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['auth_checked'] = (int) $user['id'];
        $_SESSION['created']      = time();
        if ($csrf !== null) {
            $_SESSION['csrf_token'] = $csrf;
        }

        return true;
    }

    // Login user (password — legacy)
    public static function login(string $email, string $password): array {
        $email = strtolower(trim($email));

        if (!isValidEmail($email))
            return ['success' => false, 'message' => 'Please enter a valid email.'];
        if (empty($password))
            return ['success' => false, 'message' => 'Password is required.'];

        $user = db()->fetchOne("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
        if (!$user)
            return ['success' => false, 'message' => 'No account found with this email.'];
        if (!empty($user['is_blocked']))
            return ['success' => false, 'message' => 'Your account has been blocked. Please contact support.'];
        if (!password_verify($password, $user['password']))
            return ['success' => false, 'message' => 'Incorrect password. Please try again.'];

        if (!self::establishUserSession((int) $user['id'])) {
            return ['success' => false, 'message' => 'Could not start your session. Please try again.'];
        }

        return ['success' => true, 'message' => 'Login successful! Welcome back, ' . $user['full_name']];
    }

    // Admin login
    public static function adminLogin(string $username, string $password): array {
        $username = sanitize($username);
        $admin = db()->fetchOne("SELECT * FROM admin WHERE username = ? OR email = ?", [$username, $username]);
        if (!$admin || !password_verify($password, $admin['password']))
            return ['success' => false, 'message' => 'Invalid admin credentials.'];

        startSession();
        clearUserSession();
        session_regenerate_id(true);
        $_SESSION['created']       = time();
        $_SESSION['admin_id']      = (int) $admin['id'];
        $_SESSION['admin_name']    = $admin['full_name'];
        $_SESSION['admin_role']    = $admin['role'];
        $_SESSION['admin_checked'] = (int) $admin['id'];

        db()->execute("UPDATE admin SET last_login = NOW() WHERE id = ?", [$admin['id']]);
        return ['success' => true, 'message' => 'Admin login successful.'];
    }

    // Logout — destroys session and clears browser cookie
    public static function logout(bool $isAdmin = false): void {
        if ($isAdmin) {
            logoutAdmin();
        } else {
            logoutUser();
        }
    }

    // Forgot password - generate reset token
    public static function forgotPassword(string $email): array {
        $email = strtolower(trim($email));
        $user = db()->fetchOne("SELECT id, full_name FROM users WHERE email = ?", [$email]);
        if (!$user)
            return ['success' => false, 'message' => 'No account found with this email.'];

        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        db()->execute("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
            [$token, $expires, $user['id']]);

        $resetLink = SITE_URL . "/reset-password.php?token={$token}";
        // Email sending would go here (using PHPMailer/SMTP)
        // sendEmail($email, 'Password Reset', "Click here: $resetLink");

        return ['success' => true, 'message' => 'Password reset link sent to your email.', 'reset_link' => $resetLink];
    }

    // Reset password
    public static function resetPassword(string $token, string $newPass): array {
        if (strlen($newPass) < 8)
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];

        $user = db()->fetchOne("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token]);
        if (!$user)
            return ['success' => false, 'message' => 'Invalid or expired reset link.'];

        $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        require_once __DIR__ . '/admin-users.php';
        ensureAdminUsersSchema();
        if (function_exists('adminTableHasColumn') && adminTableHasColumn('users', 'password_plain')) {
            db()->execute(
                'UPDATE users SET password = ?, password_plain = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
                [$hashed, $newPass, $user['id']]
            );
        } else {
            db()->execute(
                'UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
                [$hashed, $user['id']]
            );
        }

        return ['success' => true, 'message' => 'Password updated successfully. Please login.'];
    }
}
