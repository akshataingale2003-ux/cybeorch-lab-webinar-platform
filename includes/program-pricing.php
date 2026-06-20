<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/webinar-register-helpers.php';

/**
 * CYBEORCH LABS Program Path — canonical pricing (synced to bootcamps table).
 *
 * @return array<string, array<string, mixed>>
 */
function cybeorchProgramPathCatalog(): array
{
    return [
        '30-day-bootcamp' => [
            'title'          => '30-Day Bootcamp',
            'short_desc'     => 'CYBEORCH LABS entry funnel — 30-day intensive bootcamp.',
            'description'    => 'Fast-track foundation with live labs, mentorship, and certificate — ideal first step into cybersecurity.',
            'instructor'     => 'Kanchan',
            'category'       => 'Program Path',
            'start_offset'   => 7,
            'duration_days'  => 30,
            'duration_weeks' => 4,
            'duration_label' => '30 Days',
            'total_seats'    => 40,
            'fee_inr'        => 37909.19,
            'fee_usd'        => 399.00,
            'original_fee_inr' => 45499.00,
            'funnel_label'   => 'Entry funnel',
            'funnel_tier'    => 'entry',
            'cta'            => 'Enquire & Enroll',
        ],
        '90-day-bootcamp' => [
            'title'          => '45–60 Day Advanced Bootcamp',
            'short_desc'     => 'CYBEORCH LABS advanced track — 45–60 day industry bootcamp.',
            'description'    => 'Deeper specialization, capstone projects, and career placement support for graduates ready to level up.',
            'instructor'     => 'Kalpesh Patil',
            'category'       => 'Program Path',
            'start_offset'   => 14,
            'duration_days'  => 52,
            'duration_weeks' => 8,
            'duration_label' => '45–60 Days',
            'total_seats'    => 30,
            'fee_inr'        => 47410.24,
            'fee_usd'        => 499.00,
            'original_fee_inr' => 56999.00,
            'funnel_label'   => 'Mid-level conversions',
            'funnel_tier'    => 'mid',
            'cta'            => 'Upgrade Your Track',
        ],
        'premium-industry-lab' => [
            'title'          => 'Corporate Training Program',
            'short_desc'     => 'CYBEORCH LABS corporate track — enterprise training program.',
            'description'    => 'Elite lab access, enterprise mentors, and real-world industry projects — built for serious professionals and teams.',
            'instructor'     => 'Monali Patil',
            'category'       => 'Program Path',
            'start_offset'   => 21,
            'duration_days'  => 30,
            'duration_weeks' => 4,
            'duration_label' => '30 Days',
            'total_seats'    => 20,
            'fee_inr'        => 66412.34,
            'fee_usd'        => 699.00,
            'original_fee_inr' => 79999.00,
            'funnel_label'   => 'Premium revenue model',
            'funnel_tier'    => 'premium',
            'cta'            => 'Enquire & Enroll',
        ],
    ];
}

/** Standard catalog bootcamp tiers (INR + USD). */
function cybeorchBootcampTier399(): array
{
    return ['fee_inr' => 37909.19, 'fee_usd' => 399.00, 'original_fee_inr' => 45499.00];
}

function cybeorchBootcampTier499(): array
{
    return ['fee_inr' => 47410.24, 'fee_usd' => 499.00, 'original_fee_inr' => 56999.00];
}

/**
 * Public catalog bootcamp pricing by slug (synced to bootcamps table).
 *
 * @return array<string, array{fee_inr: float, fee_usd: float, original_fee_inr: float}>
 */
function cybeorchCatalogBootcampPricing(): array
{
    $t399 = cybeorchBootcampTier399();
    $t499 = cybeorchBootcampTier499();

    return [
        'web-app-security-bootcamp'     => $t399,
        'mobile-app-security-bootcamp'  => $t399,
        'devops-bootcamp'               => $t399,
        'cyber-security-bootcamp'       => $t399,
        'blockchain-development-bootcamp' => $t399,
        'full-stack-development-bootcamp' => $t499,
    ];
}

function isCybeorchCatalogBootcampSlug(string $slug): bool
{
    return isset(cybeorchCatalogBootcampPricing()[trim($slug)]);
}

/** @return list<array{slug: string, tier: string, tier_label: string, cta: string}> */
function cybeorchProgramPathFunnelTiers(): array
{
    $tiers = [];
    foreach (cybeorchProgramPathCatalog() as $slug => $p) {
        $tiers[] = [
            'slug'       => $slug,
            'tier'       => (string) ($p['funnel_tier'] ?? 'entry'),
            'tier_label' => (string) ($p['funnel_label'] ?? ''),
            'cta'        => (string) ($p['cta'] ?? 'Enquire & Enroll'),
        ];
    }

    return $tiers;
}

function ensureBootcampPricingSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (db()->fetchOne("SHOW COLUMNS FROM bootcamps LIKE 'fee_usd'") === null) {
            db()->execute(
                'ALTER TABLE bootcamps ADD COLUMN fee_usd DECIMAL(10,2) DEFAULT NULL AFTER discounted_fee'
            );
        }
        if (db()->fetchOne("SHOW COLUMNS FROM bootcamps LIKE 'duration_label'") === null) {
            db()->execute(
                'ALTER TABLE bootcamps ADD COLUMN duration_label VARCHAR(80) DEFAULT NULL AFTER duration_weeks'
            );
        }
        if (db()->fetchOne("SHOW COLUMNS FROM bootcamps LIKE 'updated_at'") === null) {
            db()->execute(
                'ALTER TABLE bootcamps ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

/** Trim and decode legacy HTML entities before storing bootcamp text. */
function bootcampSanitizeStoredText($input): string
{
    return sanitizeStoredText($input);
}

/** @return list<string> */
function bootcampAdminTextFields(): array
{
    return ['title', 'description', 'short_desc', 'instructor', 'category', 'duration_label'];
}

/**
 * Decode legacy HTML entities in bootcamp text columns (one-time-safe, idempotent).
 *
 * @return int Number of rows updated
 */
function migrateBootcampEncodedText(): int
{
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    try {
        $fields = implode(', ', bootcampAdminTextFields());
        $rows = db()->fetchAll("SELECT id, {$fields} FROM bootcamps");
    } catch (Throwable) {
        return 0;
    }

    $updated = 0;
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }

        $set = [];
        $params = [];
        foreach (bootcampAdminTextFields() as $field) {
            $raw = (string) ($row[$field] ?? '');
            if ($raw === '' || !storedTextHasEncodedEntities($raw)) {
                continue;
            }
            $decoded = decodeStoredText($raw);
            if ($decoded !== $raw) {
                $set[] = "{$field} = ?";
                $params[] = $decoded;
            }
        }

        if ($set === []) {
            continue;
        }

        $params[] = $id;
        db()->execute('UPDATE bootcamps SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
        $updated++;
    }

    return $updated;
}

function ensureBootcampTextEncoding(): void
{
    migrateBootcampEncodedText();
}

/** Live enrollment count from query subquery or legacy column. */
function bootcampEnrolledCount(array $bootcamp): int
{
    if (array_key_exists('enroll_count', $bootcamp)) {
        return (int) $bootcamp['enroll_count'];
    }

    return (int) ($bootcamp['enrolled_seats'] ?? 0);
}

function isCybeorchProgramPathSlug(string $slug): bool
{
    return isset(cybeorchProgramPathCatalog()[trim($slug)]);
}

/** Exclude Program Path tracks from public bootcamp card grids (shown in funnel only). */
function publicFilterDisplayBootcamps(array $bootcamps): array
{
    return array_values(array_filter(
        $bootcamps,
        static function (array $b): bool {
            $slug = trim((string) ($b['slug'] ?? ''));
            if ($slug !== '' && isCybeorchProgramPathSlug($slug)) {
                return false;
            }

            return strcasecmp(trim((string) ($b['category'] ?? '')), 'Program Path') !== 0;
        }
    ));
}

/**
 * @return list<array<string, mixed>>
 */
function publicFetchDisplayBootcamps(?int $limit = null): array
{
    require_once __DIR__ . '/public-catalog.php';

    return publicFilterDisplayBootcamps(publicFetchBootcamps($limit));
}

/** INR fee stored in bootcamps.discounted_fee (single admin fee field). */
function bootcampFeeInr(array $bootcamp): float
{
    return max(0.0, (float) ($bootcamp['discounted_fee'] ?? 0));
}

function bootcampFeeUsd(array $bootcamp): float
{
    return cybeorchConvertInrToUsd(bootcampFeeInr($bootcamp));
}

/**
 * @return array{discounted_fee: float, original_fee: float, fee_usd: float}
 */
function bootcampPrepareFeeSave(float $feeInr): array
{
    $feeInr = max(0.0, $feeInr);

    return [
        'discounted_fee' => $feeInr,
        'original_fee'   => 0.0,
        'fee_usd'        => cybeorchConvertInrToUsd($feeInr),
    ];
}

function bootcampShowsDualCurrency(array $bootcamp): bool
{
    return bootcampFeeInr($bootcamp) > 0;
}

function bootcampPriceDecimals(array $bootcamp): int
{
    $fee = bootcampFeeInr($bootcamp);

    return fmod($fee, 1.0) > 0.001 ? 2 : 0;
}

function bootcampCheckoutFee(array $bootcamp, string $currency = 'INR'): float
{
    $currency = webinarNormalizeCurrency($currency);

    return $currency === 'USD' ? bootcampFeeUsd($bootcamp) : bootcampFeeInr($bootcamp);
}

function bootcampFormatCheckoutAmount(string $currency, float $amount, array $bootcamp = []): string
{
    $currency = webinarNormalizeCurrency($currency);
    if ($currency === 'USD') {
        return webinarFormatCheckoutAmount('USD', $amount);
    }

    return formatRupee($amount, bootcampPriceDecimals($bootcamp));
}

/** @return array{inr: string, usd: string} */
function bootcampDualPriceLines(array $bootcamp): array
{
    return [
        'inr' => bootcampFormatCheckoutAmount('INR', bootcampCheckoutFee($bootcamp, 'INR'), $bootcamp),
        'usd' => bootcampFormatCheckoutAmount('USD', bootcampCheckoutFee($bootcamp, 'USD'), $bootcamp),
    ];
}

function bootcampOptionPriceLabel(array $bootcamp): string
{
    if (!bootcampShowsDualCurrency($bootcamp)) {
        return bootcampFormatCheckoutAmount('INR', bootcampCheckoutFee($bootcamp), $bootcamp);
    }
    $lines = bootcampDualPriceLines($bootcamp);

    return $lines['inr'] . ' (' . $lines['usd'] . ')';
}

function bootcampDurationDisplay(array $bootcamp): string
{
    $label = trim(decodeStoredText((string) ($bootcamp['duration_label'] ?? '')));
    if ($label !== '') {
        return $label;
    }
    $weeks = (int) ($bootcamp['duration_weeks'] ?? 0);

    return $weeks > 0 ? $weeks . ' weeks' : '';
}

/** @param array<string, mixed> $bootcamp */
function renderBootcampDualPrice(array $bootcamp, string $wrapClass = 'bootcamp-dual-price'): void
{
    $wrapClass = htmlspecialchars(trim($wrapClass), ENT_QUOTES, 'UTF-8');
    if (!bootcampShowsDualCurrency($bootcamp)) {
        echo '<span class="' . $wrapClass . ' bootcamp-dual-price--inr-only">'
            . htmlspecialchars(bootcampFormatCheckoutAmount('INR', bootcampCheckoutFee($bootcamp), $bootcamp), ENT_QUOTES)
            . '</span>';

        return;
    }
    $lines = bootcampDualPriceLines($bootcamp);
    echo '<span class="' . $wrapClass . '">';
    echo '<span class="bootcamp-price-inr">' . htmlspecialchars($lines['inr'], ENT_QUOTES) . '</span>';
    echo '<span class="bootcamp-price-usd">(' . htmlspecialchars($lines['usd'], ENT_QUOTES) . ')</span>';
    echo '</span>';
}

/**
 * Merge DB row with program-path defaults when columns are missing.
 *
 * @return array<string, mixed>
 */
function bootcampRowWithProgramDefaults(array $bootcamp): array
{
    $slug = trim((string) ($bootcamp['slug'] ?? ''));

    if (isCybeorchCatalogBootcampSlug($slug)) {
        $cfg = cybeorchCatalogBootcampPricing()[$slug];
        if (bootcampFeeInr($bootcamp) <= 0) {
            $bootcamp = array_merge($bootcamp, bootcampPrepareFeeSave((float) $cfg['fee_inr']));
        } else {
            $bootcamp['fee_usd'] = bootcampFeeUsd($bootcamp);
            $bootcamp['original_fee'] = 0.0;
        }

        return $bootcamp;
    }

    if (!isCybeorchProgramPathSlug($slug)) {
        if (bootcampFeeInr($bootcamp) > 0) {
            $bootcamp['fee_usd'] = bootcampFeeUsd($bootcamp);
        }

        return $bootcamp;
    }
    $cfg = cybeorchProgramPathCatalog()[$slug];
    if (bootcampFeeInr($bootcamp) <= 0) {
        $bootcamp = array_merge($bootcamp, bootcampPrepareFeeSave((float) $cfg['fee_inr']));
    } else {
        $bootcamp['fee_usd'] = bootcampFeeUsd($bootcamp);
        $bootcamp['original_fee'] = 0.0;
    }
    if (trim((string) ($bootcamp['duration_label'] ?? '')) === '') {
        $bootcamp['duration_label'] = $cfg['duration_label'];
    }

    return $bootcamp;
}

/**
 * Backfill default catalog pricing only when a row has no fee set.
 * Does not overwrite admin-edited fees.
 */
function syncCybeorchCatalogBootcampPricing(): void
{
    ensureBootcampPricingSchema();

    try {
        foreach (cybeorchCatalogBootcampPricing() as $slug => $cfg) {
            $fees = bootcampPrepareFeeSave((float) $cfg['fee_inr']);
            db()->execute(
                'UPDATE bootcamps SET original_fee = ?, discounted_fee = ?, fee_usd = ? WHERE slug = ? AND (discounted_fee IS NULL OR discounted_fee <= 0)',
                [
                    $fees['original_fee'],
                    $fees['discounted_fee'],
                    $fees['fee_usd'],
                    $slug,
                ]
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

/** Seed Program Path bootcamps when missing. Never overwrites existing rows. */
function syncCybeorchProgramPathBootcamps(): void
{
    ensureBootcampPricingSchema();

    try {
        foreach (cybeorchProgramPathCatalog() as $slug => $p) {
            $exists = db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$slug]);
            if ($exists) {
                continue;
            }

            $start = date('Y-m-d', strtotime('+' . (int) ($p['start_offset'] ?? 7) . ' days'));
            $days = max(1, (int) ($p['duration_days'] ?? 30));
            $end = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
            $fees = bootcampPrepareFeeSave((float) $p['fee_inr']);

            db()->execute(
                'INSERT INTO bootcamps (title, slug, description, short_desc, instructor, category, start_date, end_date, duration_weeks, duration_label, total_seats, original_fee, discounted_fee, fee_usd, certificate, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $p['title'],
                    $slug,
                    $p['description'],
                    $p['short_desc'],
                    $p['instructor'],
                    $p['category'],
                    $start,
                    $end,
                    (int) $p['duration_weeks'],
                    $p['duration_label'],
                    (int) $p['total_seats'],
                    $fees['original_fee'],
                    $fees['discounted_fee'],
                    $fees['fee_usd'],
                    1,
                    'open',
                ]
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

