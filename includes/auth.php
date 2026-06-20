<?php
// ============================================
// CYBEORCH LABS - Authentication Module
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
        require_once __DIR__ . '/referral-helpers.php';
        $refResolved = resolveReferrerFromCode($refCode, $email);
        if (!$refResolved['ok']) {
            return ['success' => false, 'message' => $refResolved['message']];
        }
        $referrerId = $refResolved['referrer_id'];
        $referredByCode = $refResolved['referral_code'];

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
            $existingReferral = db()->fetchOne(
                'SELECT id FROM referrals WHERE referred_id = ? LIMIT 1',
                [$userId]
            );
            if (!$existingReferral) {
                db()->execute('INSERT INTO referrals (referrer_id, referred_id) VALUES (?, ?)', [$referrerId, $userId]);
                processReferralRewardForReferredUser($userId, true);
            }
        }

        sendNotification($userId, 'system', 'Welcome to CYBEORCH LABS! 🎉',
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

        return ['success' => true, 'message' => 'Account created successfully! Welcome to CYBEORCH LABS.', 'user_id' => $userId];
    }

    // Register freelancer profile (creates user account if not logged in)
    public static function registerFreelancer(array $data, ?int $loggedInUserId = null): array {
        require_once __DIR__ . '/admin-schema.php';
        ensureFreelancerRegistrationsSchema();

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

        $userId = $loggedInUserId;
        $name = '';
        $email = '';

        if ($userId) {
            $user = db()->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                return ['success' => false, 'message' => 'Session expired. Please log in again.'];
            }
            $name  = trim((string) ($user['full_name'] ?? ''));
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $phone = $phone !== '' ? $phone : trim((string) ($user['phone'] ?? ''));
        } else {
            $name  = sanitize($data['full_name'] ?? '');
            $email = strtolower(trim($data['email'] ?? ''));
        }

        $validRoles = ['developer', 'designer', 'cybersecurity', 'devops', 'qa', 'data', 'mobile', 'other'];
        $validExp   = ['fresher', '1-2', '3-5', '5+'];
        $validAvail = ['full_time', 'part_time', 'project_based'];

        if ($name === '' || strlen($name) < 3) {
            return ['success' => false, 'message' => 'Full name must be at least 3 characters.'];
        }
        if (!isValidEmail($email)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        if ($phone !== '' && !isValidPhone($phone)) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit Indian phone number.'];
        }
        if (!in_array($role, $validRoles, true)) {
            return ['success' => false, 'message' => 'Please select your primary role.'];
        }
        if (!in_array($expLevel, $validExp, true)) {
            return ['success' => false, 'message' => 'Please select your experience level.'];
        }
        if (!in_array($availability, $validAvail, true)) {
            return ['success' => false, 'message' => 'Please select your availability.'];
        }
        if (strlen($skills) < 10) {
            return ['success' => false, 'message' => 'Please list your skills (at least 10 characters).'];
        }
        if (strlen($about) < 20) {
            return ['success' => false, 'message' => 'Please write a brief introduction (at least 20 characters).'];
        }

        foreach (['portfolio_url' => $portfolio, 'github_url' => $github, 'linkedin_url' => $linkedin] as $label => $url) {
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'message' => 'Please enter a valid URL for ' . str_replace('_', ' ', $label) . '.'];
            }
        }

        if (freelancerRoleAlreadyApplied($userId, $email, $role)) {
            return ['success' => false, 'message' => 'You have already applied for this freelancer role.'];
        }

        if (!$userId) {
            if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
                return ['success' => false, 'message' => 'Password must be 8+ chars with at least one uppercase and one number.'];
            }

            $existingUser = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existingUser) {
                return ['success' => false, 'message' => 'This email is already registered. Please log in to apply for additional freelancer roles.'];
            }

            $account = self::register([
                'full_name'        => $name,
                'email'            => $email,
                'phone'            => $phone,
                'password'         => $pass,
                'referral_code'    => $data['referral_code'] ?? '',
                'agree_terms'      => $data['agree_terms'] ?? '',
            ]);
            if (!$account['success']) {
                return $account;
            }
            $userId = (int) $account['user_id'];
        }

        $resumePath = trim((string) ($data['resume_path'] ?? ''));
        $resumeOriginalName = trim((string) ($data['resume_original_name'] ?? ''));
        if ($resumePath === '') {
            return ['success' => false, 'message' => 'Please attach your Resume or CV (PDF, DOC, or DOCX).'];
        }

        $freelancerId = db()->insert(
            'INSERT INTO freelancer_registrations (user_id, full_name, email, phone, primary_role, experience_level, skills, portfolio_url, github_url, linkedin_url, availability, location, about, resume_path, resume_original_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
                $resumePath,
                $resumeOriginalName ?: null,
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
                'primary_role'          => $role,
                'experience_level'      => $expLevel,
                'availability'          => $availability,
                'location'              => $location,
                'resume_path'           => $resumePath,
                'resume_original_name'  => $resumeOriginalName,
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
        $user = fetchSessionUserById($userId);
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

        $user = db()->fetchOne(
            'SELECT * FROM users WHERE email = ? AND ' . userActiveSql(''),
            [$email]
        );
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

    // Forgot password — generate reset token and email
    public static function forgotPassword(string $email): array {
        require_once __DIR__ . '/password-reset.php';
        return requestPasswordReset($email);
    }

    // Reset password via token
    public static function resetPassword(string $token, string $newPass, string $confirmPass = ''): array {
        require_once __DIR__ . '/password-reset.php';
        if ($confirmPass === '') {
            $confirmPass = $newPass;
        }
        return completePasswordReset($token, $newPass, $confirmPass);
    }
}
