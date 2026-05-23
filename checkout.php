<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment.php';

startSession();
requireLogin();

$userId = $_SESSION['user_id'];
$user   = db()->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);

$type = $_GET['type'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

if (!in_array($type, ['webinar', 'bootcamp'], true) || $id < 1) {
    header('Location: ' . url(''));
    exit;
}

$item = null;
$fee  = 0;
$isFreeWebinar = false;
$alreadyRegistered = false;

if ($type === 'webinar') {
    $item = db()->fetchOne(
        "SELECT * FROM webinars WHERE id = ? AND status IN ('upcoming','live')",
        [$id]
    );
    if (!$item) {
        header('Location: ' . url('webinars.php'));
        exit;
    }
    $fee = (float) $item['fee'];
    $isFreeWebinar = !empty($item['is_free']);
    if ($isFreeWebinar) {
        $fee = 0.0;
    }
    $alreadyRegistered = (bool) db()->fetchOne(
        'SELECT id FROM webinar_registrations WHERE user_id = ? AND webinar_id = ?',
        [$userId, $id]
    );
} else {
    $item = db()->fetchOne("SELECT * FROM bootcamps WHERE id = ? AND status='open'", [$id]);
    if (!$item) {
        header('Location: ' . url('bootcamps.php'));
        exit;
    }
    $fee = (float) $item['discounted_fee'];
    $alreadyRegistered = (bool) db()->fetchOne(
        'SELECT id FROM bootcamp_enrollments WHERE user_id = ? AND bootcamp_id = ?',
        [$userId, $id]
    );
}

$walletBalance = getWalletBalance($userId);
$useWallet     = false;
$walletApplied = 0;
$finalFee      = $fee;

if (isset($_POST['use_wallet'])) {
    $walletApplied = min($walletBalance, $fee);
    $finalFee      = max(0, $fee - $walletApplied);
    $useWallet     = true;
}

$error     = '';
$orderData = null;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_free'])
    && $type === 'webinar'
    && $isFreeWebinar
) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif ($alreadyRegistered) {
        redirectToRegistrationSuccess('webinar', $id);
    } else {
        $regNo = generateRegNo('CYB-W');
        db()->execute(
            "INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_status) VALUES (?,?,?,'free')",
            [$userId, $id, $regNo]
        );
        db()->execute('UPDATE webinars SET registered_seats = registered_seats + 1 WHERE id = ?', [$id]);
        sendNotification(
            $userId,
            'registration',
            'You are registered!',
            "You have successfully registered for: {$item['title']}"
        );
        redirectToRegistrationSuccess('webinar', $id);
    }
}

$skipOrderCreate = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['confirm_free']) || isset($_POST['confirm_wallet']));

if ($finalFee > 0 && !$skipOrderCreate) {
    $orderData = Payment::createOrder($userId, $finalFee, $type, $id, [
        'student_name'  => $user['full_name'],
        'student_email' => $user['email'],
        'item'          => $item['title'],
    ]);
    if (!$orderData['success']) {
        $error = $orderData['message'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_wallet'])) {
    debitWallet($userId, $walletApplied, 'redemption', $id, "Paid for: {$item['title']}");
    $regNo = generateRegNo($type === 'webinar' ? 'CYB-W' : 'CYB-B');
    if ($type === 'webinar') {
        db()->execute(
            "INSERT IGNORE INTO webinar_registrations (user_id, webinar_id, registration_no, payment_status) VALUES (?,?,?,'paid')",
            [$userId, $id, $regNo]
        );
        db()->execute('UPDATE webinars SET registered_seats = registered_seats + 1 WHERE id = ?', [$id]);
    } else {
        db()->execute(
            "INSERT IGNORE INTO bootcamp_enrollments (user_id, bootcamp_id, enrollment_no, payment_status) VALUES (?,?,?,'paid')",
            [$userId, $id, $regNo]
        );
        db()->execute('UPDATE bootcamps SET enrolled_seats = enrolled_seats + 1 WHERE id = ?', [$id]);
        creditWallet($userId, NXL_BOOTCAMP_REWARD, 'bootcamp_reward', $id, 'Bootcamp enrollment NxL reward!');
    }
    sendNotification($userId, 'payment', 'Payment Successful ✓', "Paid via NxL wallet for: {$item['title']}");
    redirectToRegistrationSuccess($type, $id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Checkout – CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.checkout-wrap{width:100%;max-width:900px;position:relative;z-index:1;}
.checkout-logo{font-size:1.5rem;text-align:center;margin-bottom:1.5rem;}
.checkout-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;overflow:hidden;}
.checkout-header{padding:1.5rem 2rem;border-bottom:1px solid var(--cyber-border);background:rgba(0,0,0,0.2);display:flex;align-items:center;gap:1rem;}
.checkout-header h2{font-family:'Rajdhani',sans-serif;font-size:1.25rem;font-weight:700;}
.checkout-body{padding:2rem;}
.order-summary{background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:10px;padding:1.25rem;}
.order-row{display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}
.order-row:last-child{border-bottom:none;padding-top:0.75rem;}
.order-label{font-size:0.88rem;color:var(--cyber-muted);}
.order-value{font-size:0.88rem;font-weight:500;}
.order-total-label{font-size:1rem;font-weight:600;}
.order-total-value{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;color:var(--cyber-green);}
.pay-btn{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:1rem 2rem;border-radius:10px;font-weight:700;font-size:1.05rem;width:100%;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-top:1.25rem;}
.pay-btn:hover{background:var(--cyber-green);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,255,136,0.3);}
.pay-btn:disabled{opacity:0.5;cursor:not-allowed;transform:none;}
.security-badges{display:flex;align-items:center;justify-content:center;gap:1.5rem;margin-top:1rem;flex-wrap:wrap;}
.security-badge{display:flex;align-items:center;gap:0.4rem;font-size:0.75rem;color:var(--cyber-muted);}
.alert-box{padding:1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:var(--cyber-green);}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.wallet-use{background:rgba(0,212,255,0.06);border:1px solid var(--cyber-border);border-radius:8px;padding:1rem;margin-bottom:1rem;}
</style>
</head>
<body>

<div class="checkout-wrap">
  <div class="checkout-logo"><?= brandMark() ?></div>

  <div class="checkout-card">
    <div class="checkout-header">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(0,212,255,0.1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--cyber-accent)">
        <i class="fas fa-<?= $type === 'webinar' ? 'video' : 'graduation-cap' ?>"></i>
      </div>
      <div>
        <h2><?= htmlspecialchars($item['title']) ?></h2>
        <div style="font-size:0.8rem;color:var(--cyber-muted);margin-top:2px">
          <?php if ($type === 'webinar'): ?>
          <i class="far fa-calendar me-1"></i><?= date('d M Y, h:i A', strtotime($item['scheduled_at'])) ?>
          <?php else: ?>
          <i class="fas fa-calendar-alt me-1"></i><?= date('d M', strtotime($item['start_date'])) ?> – <?= date('d M Y', strtotime($item['end_date'])) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="checkout-body">
      <div class="row g-4">
        <div class="col-lg-6">
          <?php if ($error): ?>
          <div class="alert-box alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error === 'Invalid request. Please try again.' ? $error : paymentUserMessage()) ?></div>
          <?php endif; ?>

          <!-- Wallet -->
          <?php if ($walletBalance > 0 && $fee > 0): ?>
          <div class="wallet-use">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem">
              <div style="font-size:0.88rem;font-weight:500"><i class="fas fa-coins me-1" style="color:var(--cyber-green)"></i>NxL Wallet Balance</div>
              <div style="font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700;color:var(--cyber-green)"><?= number_format($walletBalance) ?> NxL</div>
            </div>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
              <?php if (!$useWallet): ?>
              <button type="submit" name="use_wallet" value="1" style="background:rgba(0,255,136,0.12);border:1px solid rgba(0,255,136,0.3);color:var(--cyber-green);padding:0.45rem 1rem;border-radius:6px;font-size:0.82rem;cursor:pointer;width:100%">
                <i class="fas fa-check me-1"></i>Apply NxL Wallet (save ₹<?= number_format(min($walletBalance,$fee)) ?>)
              </button>
              <?php else: ?>
              <div style="color:var(--cyber-green);font-size:0.82rem"><i class="fas fa-check-circle me-1"></i>₹<?= number_format($walletApplied) ?> applied from wallet</div>
              <?php endif; ?>
            </form>
          </div>
          <?php endif; ?>

          <!-- Order Summary -->
          <div class="order-summary">
            <div class="order-row">
              <span class="order-label"><?= ucfirst($type) ?> Fee</span>
              <span class="order-value">₹<?= number_format($fee) ?></span>
            </div>
            <?php if ($walletApplied > 0): ?>
            <div class="order-row">
              <span class="order-label" style="color:var(--cyber-green)">NxL Wallet Discount</span>
              <span class="order-value" style="color:var(--cyber-green)">-₹<?= number_format($walletApplied) ?></span>
            </div>
            <?php endif; ?>
            <div class="order-row">
              <span class="order-label">GST (18%)</span>
              <span class="order-value">Included</span>
            </div>
            <div class="order-row">
              <span class="order-total-label">Total Payable</span>
              <span class="order-total-value">₹<?= number_format($finalFee) ?></span>
            </div>
          </div>

          <?php if ($type === 'webinar' && $isFreeWebinar): ?>
            <?php if ($alreadyRegistered): ?>
            <div class="alert-box alert-success"><i class="fas fa-check-circle"></i>You are already registered for this webinar.</div>
            <a href="<?= url('my-registrations.php') ?>" class="pay-btn" style="text-decoration:none;margin-top:0.75rem;background:var(--cyber-accent)">
              <i class="fas fa-ticket-alt"></i> View My Registrations
            </a>
            <?php else: ?>
            <div class="order-summary mb-3">
              <div class="order-row">
                <span class="order-total-label">Registration Fee</span>
                <span class="order-total-value" style="color:var(--cyber-green)">FREE</span>
              </div>
            </div>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
              <button type="submit" name="confirm_free" value="1" class="pay-btn" style="background:var(--cyber-green)">
                <i class="fas fa-check-circle"></i> Confirm Free Registration
              </button>
            </form>
            <?php endif; ?>
          <?php elseif ($finalFee > 0 && !empty($orderData['success'])): ?>
          <button id="rzp-btn" class="pay-btn" type="button">
            <i class="fas fa-lock"></i> Pay <?= formatRupee($finalFee) ?> Securely
          </button>
          <?php elseif ($finalFee > 0): ?>
            <div class="alert-box alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars(paymentUserMessage()) ?></div>
            <a href="javascript:location.reload()" class="pay-btn" style="text-decoration:none;margin-top:0.75rem;background:var(--cyber-accent)">
              <i class="fas fa-redo"></i> Try Again
            </a>
          <?php elseif ($finalFee == 0 && $walletApplied > 0): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="use_wallet" value="1">
            <button type="submit" name="confirm_wallet" value="1" class="pay-btn" style="background:var(--cyber-green)">
              <i class="fas fa-coins"></i> Confirm – Pay via NxL Wallet
            </button>
          </form>
          <?php elseif ($finalFee == 0 && $fee == 0 && !$isFreeWebinar): ?>
          <div class="alert-box alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars(paymentUserMessage()) ?></div>
          <?php endif; ?>

          <div class="security-badges">
            <div class="security-badge"><i class="fas fa-lock" style="color:var(--cyber-green)"></i>256-bit SSL</div>
            <div class="security-badge"><i class="fas fa-shield-alt" style="color:var(--cyber-accent)"></i>Secure Payment</div>
            <a href="<?= url('refund.php') ?>" class="security-badge text-decoration-none"><i class="fas fa-undo" style="color:var(--cyber-orange)"></i>Refund Policy</a>
          </div>
        </div>

        <div class="col-lg-6">
          <div style="background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:10px;padding:1.5rem;">
            <h5 style="font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--cyber-accent)">What You'll Get</h5>
            <ul style="list-style:none;font-size:0.88rem;color:var(--cyber-muted);">
              <?php if ($type === 'webinar'): ?>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Live session access on <?= date('d M Y', strtotime($item['scheduled_at'])) ?></li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Session recording (if available)</li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Q&A with instructor</li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Attendance certificate</li>
              <li style="padding:0.4rem 0"><i class="fas fa-coins" style="color:var(--cyber-green);margin-right:8px"></i>Earn <?= NXL_WEBINAR_REWARD ?> NxL tokens on attendance</li>
              <?php else: ?>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i><?= $item['duration_weeks'] ?>-week intensive program</li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Hands-on lab exercises</li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>1:1 mentorship sessions</li>
              <li style="padding:0.4rem 0"><i class="fas fa-check" style="color:var(--cyber-green);margin-right:8px"></i>Certificate of completion</li>
              <li style="padding:0.4rem 0"><i class="fas fa-coins" style="color:var(--cyber-green);margin-right:8px"></i>Earn <?= NXL_BOOTCAMP_REWARD ?> NxL tokens on enrollment</li>
              <?php endif; ?>
            </ul>
          </div>

          <!-- User Registration & Trainee Info -->
          <div style="background:rgba(0,0,0,0.15);border:1px solid var(--cyber-border);border-radius:10px;padding:1.25rem;margin-top:1rem;">
            <h5 style="font-size:0.88rem;font-weight:600;margin-bottom:0.75rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:0.5px">Registering As</h5>
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-blue));display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-weight:700;color:var(--cyber-dark)">
                <?= strtoupper(substr($user['full_name'],0,1)) ?>
              </div>
              <div>
                <div style="font-size:0.88rem;font-weight:500"><?= htmlspecialchars($user['full_name']) ?></div>
                <div style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="text-align:center;margin-top:1rem">
    <a href="javascript:history.back()" style="color:var(--cyber-muted);font-size:0.85rem;text-decoration:none"><i class="fas fa-arrow-left me-1"></i>Go Back</a>
  </div>
</div>

<?php if ($finalFee > 0 && $orderData && $orderData['success']): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const options = {
  key: '<?= RAZORPAY_KEY_ID ?>',
  amount: <?= $orderData['amount_paise'] ?>,
  currency: '<?= RAZORPAY_CURRENCY ?>',
  name: 'CYBEORCH LAB',
  description: '<?= addslashes($item['title']) ?>',
  order_id: '<?= $orderData['razorpay_order_id'] ?>',
  handler: function(response) {
    // Submit verification to server
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = <?= json_encode(url('api/payment-verify.php')) ?>;
    ['razorpay_payment_id','razorpay_order_id','razorpay_signature'].forEach(key => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = key; inp.value = response[key];
      form.appendChild(inp);
    });
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= generateCSRF() ?>';
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
  },
  prefill: {
    name: '<?= addslashes($user['full_name']) ?>',
    email: '<?= addslashes($user['email']) ?>',
    contact: '<?= addslashes($user['phone'] ?? '') ?>'
  },
  theme: { color: '#00d4ff' },
  modal: { ondismiss: function() { document.getElementById('rzp-btn').disabled = false; } }
};

document.getElementById('rzp-btn').addEventListener('click', function() {
  this.disabled = true;
  this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  const rzp = new Razorpay(options);
  rzp.open();
  rzp.on('payment.failed', function(res) {
    alert(<?= json_encode(paymentUserMessage()) ?>);
    document.getElementById('rzp-btn').disabled = false;
    document.getElementById('rzp-btn').innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($finalFee) ?> Securely';
  });
});
</script>
<?php endif; ?>
<?php renderSiteScripts(); ?>
</body>
</html>
