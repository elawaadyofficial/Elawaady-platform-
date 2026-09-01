<?php
/**
 * EXD — what a service costs.
 *
 * Price is computed in one place. A page that renders a price and a page that
 * charges for one must agree, and the only way to guarantee that is for both
 * to call the same function — so the storefront's live total and the amount
 * the order is written with come from here.
 */

require_once __DIR__ . '/../db_connect.php';

/**
 * The unit price of a service.
 *
 * A provider-priced service is quoted from the provider's rate plus the
 * store's margin; everything else uses the price on the row.
 */
function service_unit_price(array $service): float {
    if (($service['price_mode'] ?? 'manual') === 'provider_auto'
        && (float) ($service['provider_base_price'] ?? 0) > 0) {
        $per    = max(1, (int) ($service['provider_price_per'] ?? 1000));
        $margin = max(0.0, (float) ($service['profit_percent'] ?? 0));
        return round(((float) $service['provider_base_price'] / $per) * (1 + $margin / 100), 4);
    }
    return round((float) ($service['price'] ?? 0), 2);
}

/** The line total for a quantity, before options. */
function service_line_total(array $service, int $quantity): float {
    return round(service_unit_price($service) * max(1, $quantity), 2);
}

/**
 * What a set of chosen options adds.
 *
 * A percentage option is applied to the line total, a fixed one is added per
 * order — which is why the base is passed in rather than recomputed here.
 */
function service_options_total($conn, int $serviceId, array $chosen, float $lineTotal): array {
    if (!$chosen) {
        return ['total' => 0.0, 'lines' => []];
    }

    $values = fetch_all(
        $conn,
        'SELECT ov.id, ov.label, ov.value_key, ov.price_delta, ov.delta_mode, o.label AS option_label
           FROM service_option_values ov
           JOIN service_options o ON o.id = ov.option_id
          WHERE o.service_id = ? AND ov.is_active = 1',
        'i',
        $serviceId
    );

    $byKey = [];
    foreach ($values as $value) {
        $byKey[(string) $value['value_key']] = $value;
    }

    $total = 0.0;
    $lines = [];

    foreach ($chosen as $key) {
        $value = $byKey[(string) $key] ?? null;
        if ($value === null) {
            continue;
        }
        $delta = $value['delta_mode'] === 'percent'
            ? $lineTotal * ((float) $value['price_delta'] / 100)
            : (float) $value['price_delta'];
        $delta   = round($delta, 2);
        $total  += $delta;
        $lines[] = [
            'option_label' => (string) $value['option_label'],
            'value_label'  => (string) $value['label'],
            'price_delta'  => $delta,
        ];
    }

    return ['total' => round($total, 2), 'lines' => $lines];
}

/** The mediation fee a service charges on a given amount. */
function service_mediation_fee(array $service, float $amount): float {
    if ((int) ($service['mediation_enabled'] ?? 0) !== 1) {
        return 0.0;
    }
    $fee = (float) ($service['mediation_fee'] ?? 0);
    if ($fee <= 0) {
        return 0.0;
    }
    return round(($service['mediation_fee_mode'] ?? 'fixed') === 'percent' ? $amount * $fee / 100 : $fee, 2);
}

/**
 * The whole quote for a service: what it costs, and why.
 *
 * Returning the parts as well as the total means the page can show the
 * breakdown and the order can store it, without either recomputing anything.
 */
function service_quote($conn, array $service, int $quantity, array $chosenOptions = [], bool $withMediation = false): array {
    $quantity  = max(1, $quantity);
    $unit      = service_unit_price($service);
    $lineTotal = round($unit * $quantity, 2);

    $options       = service_options_total($conn, (int) $service['id'], $chosenOptions, $lineTotal);
    $beforeFee     = round($lineTotal + $options['total'], 2);
    $mediationFee  = $withMediation ? service_mediation_fee($service, $beforeFee) : 0.0;

    return [
        'unit_price'     => $unit,
        'quantity'       => $quantity,
        'line_total'     => $lineTotal,
        'options_total'  => $options['total'],
        'option_lines'   => $options['lines'],
        'mediation_fee'  => $mediationFee,
        'total'          => round($beforeFee + $mediationFee, 2),
        'currency'       => (string) ($service['currency'] ?: 'EGP'),
    ];
}

/** Whether a quantity is one this service accepts. */
function service_quantity_valid(array $service, int $quantity): bool {
    $min  = max(1, (int) ($service['min_quantity'] ?? 1));
    $max  = max($min, (int) ($service['max_quantity'] ?? 1000000));
    $step = max(1, (int) ($service['quantity_step'] ?? 1));

    return $quantity >= $min && $quantity <= $max && (($quantity - $min) % $step === 0);
}
