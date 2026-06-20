<?php
declare(strict_types=1);

require_once __DIR__ . '/program-pricing.php';
require_once __DIR__ . '/public-catalog.php';

function securePaymentProgramPickerValue(string $selectedType, int $selectedId, string $selectedSlug): string
{
    if ($selectedType === 'webinar' && $selectedId > 0) {
        return 'webinar-id:' . $selectedId;
    }
    if ($selectedType === 'webinar' && $selectedSlug !== '') {
        return 'webinar:' . $selectedSlug;
    }
    if ($selectedType === 'bootcamp' && $selectedId > 0) {
        return 'bootcamp-id:' . $selectedId;
    }
    if ($selectedType === 'bootcamp' && $selectedSlug !== '') {
        return 'bootcamp:' . $selectedSlug;
    }

    return '';
}

function securePaymentProgramOptionSelected(
    string $pickerValue,
    string $programPickerValue,
    string $selectedType,
    int $selectedId,
    string $selectedSlug,
    string $optionSlug = ''
): bool {
    if ($programPickerValue !== '' && $pickerValue === $programPickerValue) {
        return true;
    }
    if ($selectedType === 'bootcamp' && $optionSlug !== '' && $selectedSlug === $optionSlug) {
        return true;
    }
    if ($selectedType === 'webinar' && str_starts_with($pickerValue, 'webinar-id:')) {
        return $selectedId === (int) substr($pickerValue, 11);
    }

    return false;
}

/** @return array<string, mixed>|null */
function securePaymentFetchBootcampRow(string $slug, int $id): ?array
{
    if ($id > 0) {
        $row = publicFetchBootcampById($id);
        if (is_array($row)) {
            return bootcampRowWithProgramDefaults($row);
        }
    }
    if ($slug !== '') {
        $row = publicFetchBootcampBySlug($slug);
        if (is_array($row)) {
            return bootcampRowWithProgramDefaults($row);
        }
        if (isCybeorchProgramPathSlug($slug)) {
            $cfg = cybeorchProgramPathCatalog()[$slug];

            return bootcampRowWithProgramDefaults([
                'slug'           => $slug,
                'title'          => $cfg['title'],
                'short_desc'     => $cfg['short_desc'],
                'description'    => $cfg['description'],
                'instructor'     => $cfg['instructor'],
                'category'       => $cfg['category'],
                'discounted_fee' => $cfg['fee_inr'],
                'duration_label' => $cfg['duration_label'],
            ]);
        }
    }

    return null;
}

/** @return array<string, mixed>|null */
function securePaymentFetchWebinarRow(string $slug, int $id): ?array
{
    if ($id > 0) {
        $row = publicFetchWebinarById($id);
        if (is_array($row)) {
            return $row;
        }
    }
    if ($slug !== '') {
        $row = publicFetchWebinarBySlug($slug);
        if (is_array($row)) {
            return $row;
        }
    }

    return null;
}

function securePaymentEnsureBootcampId(string $slug, int $id): int
{
    if ($id > 0) {
        return $id;
    }
    if ($slug === '') {
        return 0;
    }
    require_once __DIR__ . '/training-catalog.php';
    ensureTrainingCatalog();
    $row = publicFetchBootcampBySlug($slug);

    return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
}

/**
 * @param array<string, array{title: string, desc: string, fee: float|int, duration: string, schedule: string, instructor: string}> $paidWebinarTracks
 * @return array{
 *   selectedType: string,
 *   selectedSlug: string,
 *   selectedId: int,
 *   row: ?array,
 *   programLabel: string,
 *   programDesc: string,
 *   programMeta: array<string, string>,
 *   programFee: float,
 *   programPickerValue: string
 * }
 */
function resolveSecurePaymentProgram(array $paidWebinarTracks, string $paymentCurrency): array
{
    $selectedType = (string) ($_GET['type'] ?? $_POST['program_type'] ?? 'webinar');
    if (!in_array($selectedType, ['webinar', 'bootcamp'], true)) {
        $selectedType = 'webinar';
    }

    $selectedSlug = sanitize((string) ($_GET['program'] ?? $_GET['slug'] ?? $_POST['program_slug'] ?? ''));
    $selectedId   = (int) ($_GET['id'] ?? $_POST['program_id'] ?? 0);

    $explicitProgramRequest = isset($_GET['type']) || isset($_GET['id']) || isset($_GET['slug']) || isset($_GET['program'])
        || isset($_POST['program_type']);

    if ($selectedSlug === '' && $selectedType === 'webinar' && !$explicitProgramRequest) {
        $selectedSlug = 'ai-ml';
    }

    $row = null;
    $programLabel = '';
    $programDesc = '';
    $programMeta = [];
    $programFee = 0.0;

    if ($selectedType === 'bootcamp') {
        $row = securePaymentFetchBootcampRow($selectedSlug, $selectedId);
    } else {
        $row = securePaymentFetchWebinarRow($selectedSlug, $selectedId);
    }

    if (is_array($row)) {
        if ($selectedType === 'bootcamp') {
            $row = bootcampRowWithProgramDefaults($row);
        }
        $programLabel = (string) ($row['title'] ?? '');
        $programFee = $selectedType === 'bootcamp'
            ? bootcampCheckoutFee($row, $paymentCurrency)
            : webinarCheckoutFee($row, $paymentCurrency);
        $programDesc = (string) ($row['short_desc'] ?? $row['description'] ?? '');
        $selectedSlug = (string) ($row['slug'] ?? $selectedSlug);
        $selectedId = (int) ($row['id'] ?? $selectedId);
        $programMeta = $selectedType === 'bootcamp'
            ? array_filter([
                'Category'   => (string) ($row['category'] ?? ''),
                'Duration'   => bootcampDurationDisplay($row),
                'Schedule'   => !empty($row['start_date']) && !empty($row['end_date'])
                    ? date('d M Y', strtotime((string) $row['start_date'])) . ' – ' . date('d M Y', strtotime((string) $row['end_date']))
                    : '',
                'Instructor' => (string) ($row['instructor'] ?? ''),
            ])
            : array_filter([
                'Category'   => (string) ($row['category'] ?? ''),
                'Duration'   => ((int) ($row['duration_mins'] ?? 0)) > 0 ? ((int) $row['duration_mins']) . ' min' : '',
                'Schedule'   => !empty($row['scheduled_at']) ? date('d M Y, h:i A', strtotime((string) $row['scheduled_at'])) : '',
                'Instructor' => (string) ($row['instructor'] ?? ''),
            ]);
    } elseif ($selectedType === 'webinar' && isset($paidWebinarTracks[$selectedSlug])) {
        $track = $paidWebinarTracks[$selectedSlug];
        $programLabel = $track['title'] . ' Webinar';
        $programFee = webinarCheckoutFee(['is_free' => 0], $paymentCurrency);
        $programDesc = $track['desc'];
        $programMeta = [
            'Duration'   => $track['duration'],
            'Schedule'   => $track['schedule'],
            'Instructor' => $track['instructor'],
        ];
    } elseif ($selectedType === 'webinar' && !$explicitProgramRequest) {
        $selectedSlug = 'ai-ml';
        $track = $paidWebinarTracks[$selectedSlug];
        $programLabel = $track['title'] . ' Webinar';
        $programFee = webinarCheckoutFee(['is_free' => 0], $paymentCurrency);
        $programDesc = $track['desc'];
        $programMeta = [
            'Duration'   => $track['duration'],
            'Schedule'   => $track['schedule'],
            'Instructor' => $track['instructor'],
        ];
    } elseif ($selectedType === 'bootcamp' && !$explicitProgramRequest) {
        $programLabel = 'Bootcamp';
    } elseif ($selectedType === 'webinar') {
        $programLabel = 'Webinar';
    } else {
        $programLabel = 'Bootcamp';
    }

    if ($selectedType === 'bootcamp') {
        $selectedId = securePaymentEnsureBootcampId($selectedSlug, $selectedId);
        if ($selectedId > 0 && (!is_array($row) || (int) ($row['id'] ?? 0) < 1)) {
            $row = securePaymentFetchBootcampRow($selectedSlug, $selectedId);
            if (is_array($row)) {
                $row = bootcampRowWithProgramDefaults($row);
                $programLabel = (string) ($row['title'] ?? $programLabel);
                $programDesc = (string) ($row['short_desc'] ?? $row['description'] ?? $programDesc);
                $programFee = bootcampCheckoutFee($row, $paymentCurrency);
            }
        }
    }

    return [
        'selectedType'       => $selectedType,
        'selectedSlug'       => $selectedSlug,
        'selectedId'         => $selectedId,
        'row'                => is_array($row) ? $row : null,
        'programLabel'       => $programLabel,
        'programDesc'        => $programDesc,
        'programMeta'        => $programMeta,
        'programFee'         => $programFee,
        'programPickerValue' => securePaymentProgramPickerValue($selectedType, $selectedId, $selectedSlug),
    ];
}

/** Build secure-payment URL for any bootcamp/webinar card. */
function securePaymentUrlForCatalogItem(array $item, string $defaultType = 'bootcamp'): string
{
    $type = $defaultType;
    if (!empty($item['scheduled_at']) || (!empty($item['duration_mins']) && empty($item['start_date']))) {
        $type = 'webinar';
    }

    $id = (int) ($item['id'] ?? 0);
    $slug = trim((string) ($item['slug'] ?? ''));
    $params = ['type' => $type];
    if ($id > 0) {
        $params['id'] = (string) $id;
    }
    if ($slug !== '') {
        $params[$type === 'webinar' ? 'program' : 'slug'] = $slug;
    }

    return url('secure-payment.php?' . http_build_query($params));
}

/**
 * Program Path + catalog bootcamp options for the Step 1 dropdown.
 *
 * @return list<array{pickerVal: string, slug: string, label: string}>
 */
function securePaymentProgramPathDropdownOptions(): array
{
    $options = [];
    foreach (cybeorchProgramPathCatalog() as $pathSlug => $pathCfg) {
        $pathRow = securePaymentFetchBootcampRow($pathSlug, 0);
        if ($pathRow === null || bootcampFeeInr($pathRow) <= 0) {
            continue;
        }
        $pathRow = bootcampRowWithProgramDefaults($pathRow);
        $pathId = (int) ($pathRow['id'] ?? 0);
        $shortDesc = trim((string) ($pathCfg['short_desc'] ?? ''));
        $title = (string) $pathCfg['title'];
        $label = $shortDesc !== '' ? $title . ' — ' . $shortDesc . ' · ' . bootcampOptionPriceLabel($pathRow) : $title . ' — ' . bootcampOptionPriceLabel($pathRow);
        $options[] = [
            'pickerVal' => $pathId > 0 ? 'bootcamp-id:' . $pathId : 'bootcamp:' . $pathSlug,
            'slug'      => $pathSlug,
            'label'     => $label,
        ];
    }

    return $options;
}
