<?php
/**
 * EXD — session-scoped checkout intents.
 *
 * A checkout intent is a one-form retry key tied to a specific service and
 * browser session. It is deliberately reusable for a short window so the same
 * POST can be retried safely; the database idempotency layer decides whether
 * the purchase is new or a replay.
 */

const CHECKOUT_INTENT_TTL_SECONDS = 1800;
const CHECKOUT_INTENT_MAX_ACTIVE = 12;

function checkout_intent_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['checkout_intents']) || !is_array($_SESSION['checkout_intents'])) {
        $_SESSION['checkout_intents'] = [];
    }
}

function checkout_intent_prune(): void {
    checkout_intent_boot();
    $cutoff = time() - CHECKOUT_INTENT_TTL_SECONDS;
    foreach ($_SESSION['checkout_intents'] as $key => $intent) {
        if (!is_array($intent) || (int) ($intent['issued_at'] ?? 0) < $cutoff) {
            unset($_SESSION['checkout_intents'][$key]);
        }
    }
    if (count($_SESSION['checkout_intents']) > CHECKOUT_INTENT_MAX_ACTIVE) {
        uasort($_SESSION['checkout_intents'], static fn(array $a, array $b): int => ((int) ($a['issued_at'] ?? 0)) <=> ((int) ($b['issued_at'] ?? 0)));
        while (count($_SESSION['checkout_intents']) > CHECKOUT_INTENT_MAX_ACTIVE) {
            array_shift($_SESSION['checkout_intents']);
        }
    }
}

function checkout_intent_issue(int $serviceId): string {
    if ($serviceId <= 0) {
        throw new InvalidArgumentException('serviceId must be positive');
    }
    checkout_intent_prune();
    $key = 'ci_' . bin2hex(random_bytes(24));
    $_SESSION['checkout_intents'][$key] = [
        'service_id' => $serviceId,
        'issued_at' => time(),
    ];
    return $key;
}

function checkout_intent_verify(string $key, int $serviceId): bool {
    if ($key === '' || $serviceId <= 0 || !preg_match('/^ci_[a-f0-9]{48}$/', $key)) {
        return false;
    }
    checkout_intent_prune();
    $intent = $_SESSION['checkout_intents'][$key] ?? null;
    if (!is_array($intent)) {
        return false;
    }
    return (int) ($intent['service_id'] ?? 0) === $serviceId;
}

function checkout_intent_forget(string $key): void {
    checkout_intent_boot();
    unset($_SESSION['checkout_intents'][$key]);
}
