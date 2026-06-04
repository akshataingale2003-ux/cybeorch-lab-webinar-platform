<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/webinar-register-helpers.php';

/**
 * CYBEORCH LAB Program Path — canonical pricing (synced to bootcamps table).
 *
 * @return array<string, array<string, mixed>>
 */
function cybeorchProgramPathCatalog(): array
{
    return [
        '30-day-bootcamp' => [
            'title'          => '30-Day Bootcamp',
            'short_desc'     => 'CYBEORCH LAB entry funnel — 30-day intensive bootcamp.',
            'description'    => 'Fast-track foundation with live labs, mentorship, and certificate — ideal first step into cybersecurity.',
            'instructor'     => 'Priya Nair',
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
            'short_desc'     => 'CYBEORCH LAB advanced track — 45–60 day industry bootcamp.',
            'description'    => 'Deeper specialization, capstone projects, and career placement support for graduates ready to level up.',
            'instructor'     => 'Rahul Sharma',
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
            'short_desc'     => 'CYBEORCH LAB corporate track — enterprise training program.',
            'description'    => 'Elite lab access, enterprise mentors, and real-world industry projects — built for serious professionals and teams.',
            'instructor'     => 'Amit Verma',
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
    } catch (Throwable $e) {
        // DB offline
    }
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

function bootcampShowsDualCurrency(array $bootcamp): bool
{
    return (float) ($bootcamp['fee_usd'] ?? 0) > 0;
}

function bootcampPriceDecimals(array $bootcamp): int
{
    if (bootcampShowsDualCurrency($bootcamp)) {
        return 2;
    }
    $fee = (float) ($bootcamp['discounted_fee'] ?? 0);

    return fmod($fee, 1.0) > 0.001 ? 2 : 0;
}

function bootcampCheckoutFee(array $bootcamp, string $currency = 'INR'): float
{
    $currency = webinarNormalizeCurrency($currency);
    if ($currency === 'USD' && bootcampShowsDualCurrency($bootcamp)) {
        return (float) $bootcamp['fee_usd'];
    }

    return (float) ($bootcamp['discounted_fee'] ?? 0);
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
    $label = trim((string) ($bootcamp['duration_label'] ?? ''));
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
        if ((float) ($bootcamp['discounted_fee'] ?? 0) <= 0) {
            $bootcamp['discounted_fee'] = $cfg['fee_inr'];
        }
        if ((float) ($bootcamp['fee_usd'] ?? 0) <= 0) {
            $bootcamp['fee_usd'] = $cfg['fee_usd'];
        }
        if ((float) ($bootcamp['original_fee'] ?? 0) <= 0) {
            $bootcamp['original_fee'] = $cfg['original_fee_inr'];
        }

        return $bootcamp;
    }

    if (!isCybeorchProgramPathSlug($slug)) {
        return $bootcamp;
    }
    $cfg = cybeorchProgramPathCatalog()[$slug];
    if ((float) ($bootcamp['discounted_fee'] ?? 0) <= 0) {
        $bootcamp['discounted_fee'] = $cfg['fee_inr'];
    }
    if ((float) ($bootcamp['fee_usd'] ?? 0) <= 0) {
        $bootcamp['fee_usd'] = $cfg['fee_usd'];
    }
    if (trim((string) ($bootcamp['duration_label'] ?? '')) === '') {
        $bootcamp['duration_label'] = $cfg['duration_label'];
    }

    return $bootcamp;
}

function syncCybeorchCatalogBootcampPricing(): void
{
    ensureBootcampPricingSchema();

    try {
        foreach (cybeorchCatalogBootcampPricing() as $slug => $cfg) {
            db()->execute(
                'UPDATE bootcamps SET original_fee = ?, discounted_fee = ?, fee_usd = ? WHERE slug = ?',
                [
                    (float) $cfg['original_fee_inr'],
                    (float) $cfg['fee_inr'],
                    (float) $cfg['fee_usd'],
                    $slug,
                ]
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}
function syncCybeorchProgramPathBootcamps(): void
{
    ensureBootcampPricingSchema();

    try {
        foreach (cybeorchProgramPathCatalog() as $slug => $p) {
            $start = date('Y-m-d', strtotime('+' . (int) ($p['start_offset'] ?? 7) . ' days'));
            $days = max(1, (int) ($p['duration_days'] ?? 30));
            $end = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
            $exists = db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$slug]);

            if (!$exists) {
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
                        (float) $p['original_fee_inr'],
                        (float) $p['fee_inr'],
                        (float) $p['fee_usd'],
                        1,
                        'open',
                    ]
                );
                continue;
            }

            db()->execute(
                'UPDATE bootcamps SET title = ?, description = ?, short_desc = ?, instructor = ?, category = ?,
                    start_date = ?, end_date = ?, duration_weeks = ?, duration_label = ?, total_seats = ?,
                    original_fee = ?, discounted_fee = ?, fee_usd = ?, status = ? WHERE slug = ?',
                [
                    $p['title'],
                    $p['description'],
                    $p['short_desc'],
                    $p['instructor'],
                    $p['category'],
                    $start,
                    $end,
                    (int) $p['duration_weeks'],
                    $p['duration_label'],
                    (int) $p['total_seats'],
                    (float) $p['original_fee_inr'],
                    (float) $p['fee_inr'],
                    (float) $p['fee_usd'],
                    'open',
                    $slug,
                ]
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

