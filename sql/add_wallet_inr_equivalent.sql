-- NXL Wallet: INR equivalent column + extended transaction reasons
ALTER TABLE wallet_transactions
    ADD COLUMN inr_equivalent DECIMAL(10,2) DEFAULT NULL AFTER amount;

ALTER TABLE wallet_transactions
    MODIFY COLUMN reason ENUM(
        'webinar_reward','referral_bonus','bootcamp_reward','admin_credit','admin_debit',
        'redemption','cashback','signup_bonus','special_reward','transfer_in','transfer_out'
    ) NOT NULL;

-- Backfill INR equivalent for existing rows (1 credit = ₹1.25 at current rate; adjust if historical rate differed)
UPDATE wallet_transactions
SET inr_equivalent = ROUND(amount * 1.25, 2)
WHERE inr_equivalent IS NULL;

-- Ensure every user has an NXL wallet row
INSERT INTO wallet (user_id, balance)
SELECT u.id, 0 FROM users u
WHERE NOT EXISTS (SELECT 1 FROM wallet w WHERE w.user_id = u.id);
