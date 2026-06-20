<?php
declare(strict_types=1);

/**
 * Payment & receipt schema migrations (runtime-safe).
 */
function ensurePaymentReceiptSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/admin-actions.php';

    ensureAdminActionsSchema();

    $columns = [
        'receipt_no'            => 'VARCHAR(50) NULL',
        'payment_type'          => "VARCHAR(20) NULL COMMENT 'online|nxl_token|mixed|free'",
        'nxl_tokens_used'       => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'nxl_inr_value'         => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'cash_amount'           => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'wallet_transaction_id' => 'INT NULL',
        'nxl_transaction_ref'   => 'VARCHAR(50) NULL',
        'registration_no'       => 'VARCHAR(50) NULL',
    ];

    foreach ($columns as $col => $def) {
        if (!adminTableHasColumn('payments', $col)) {
            try {
                db()->execute("ALTER TABLE payments ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
                // ignore races
            }
        }
    }

    try {
        db()->execute('CREATE UNIQUE INDEX idx_payments_receipt_no ON payments (receipt_no)');
    } catch (Throwable $e) {
        // index may already exist
    }

    paymentSchemaBackfillReceiptNumbers();
}

function paymentSchemaBackfillReceiptNumbers(): void
{
    try {
        $rows = db()->fetchAll(
            "SELECT id, invoice_no, receipt_no FROM payments WHERE receipt_no IS NULL OR receipt_no = '' LIMIT 200"
        );
        foreach ($rows as $row) {
            $receipt = paymentSchemaGenerateReceiptNo();
            if (!empty($row['invoice_no'])) {
                $exists = db()->fetchOne('SELECT id FROM payments WHERE receipt_no = ? AND id != ?', [$row['invoice_no'], $row['id']]);
                if (!$exists) {
                    $receipt = $row['invoice_no'];
                }
            }
            db()->execute('UPDATE payments SET receipt_no = ? WHERE id = ?', [$receipt, $row['id']]);
        }
    } catch (Throwable $e) {
        // table may not exist during install
    }
}

function paymentSchemaGenerateReceiptNo(): string
{
    $prefix = 'RCP-' . date('Ymd') . '-';
    try {
        $row = db()->fetchOne(
            "SELECT receipt_no FROM payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = 1;
        if ($row && preg_match('/-(\d+)$/', (string) $row['receipt_no'], $m)) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return $prefix . strtoupper(bin2hex(random_bytes(2)));
    }
}

function paymentSchemaGenerateNxlTransactionRef(int $walletTxId = 0): string
{
    if ($walletTxId > 0) {
        return 'NXL-TXN-' . str_pad((string) $walletTxId, 8, '0', STR_PAD_LEFT);
    }
    return 'NXL-TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
