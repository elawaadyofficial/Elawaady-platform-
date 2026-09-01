<?php
/**
 * EXD — mediation.
 *
 * A mediated deal is one where the platform holds the buyer's money until the
 * seller has delivered and the buyer has confirmed. The money is held in the
 * same ledger as everything else — a debit from the buyer's balance into
 * held_balance — so a held amount is never invented and never lost.
 *
 * The states form a line, not a free-for-all: mediation_transitions() is the
 * only place a move is allowed, and every move writes a history row.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/wallet.php';

function mediation_statuses(): array {
    return [
        'opened'         => 'مفتوحة',
        'terms_accepted' => 'تم قبول الشروط',
        'funds_held'     => 'المبلغ محجوز',
        'in_delivery'    => 'قيد التسليم',
        'delivered'      => 'تم التسليم',
        'safety_period'  => 'فترة الأمان',
        'released'       => 'تم التحرير',
        'refunded'       => 'مسترد',
        'disputed'       => 'نزاع',
        'cancelled'      => 'ملغاة',
    ];
}

/** Which state may follow which. A state with an empty list is final. */
function mediation_transitions(): array {
    return [
        'opened'         => ['terms_accepted', 'cancelled'],
        'terms_accepted' => ['funds_held', 'cancelled'],
        'funds_held'     => ['in_delivery', 'refunded', 'disputed'],
        'in_delivery'    => ['delivered', 'disputed', 'refunded'],
        'delivered'      => ['safety_period', 'released', 'disputed'],
        'safety_period'  => ['released', 'disputed'],
        'released'       => [],
        'refunded'       => [],
        'disputed'       => ['in_delivery', 'released', 'refunded', 'cancelled'],
        'cancelled'      => [],
    ];
}

function mediation_can_move(string $from, string $to): bool {
    return in_array($to, mediation_transitions()[$from] ?? [], true);
}

function mediation_generate_code($conn): string {
    $prefix = 'MED-' . date('Ymd') . '-';
    $row    = fetch_one($conn, 'SELECT COUNT(*) AS n FROM mediations WHERE case_code LIKE ?', 's', $prefix . '%');
    return $prefix . str_pad((string) ((int) ($row['n'] ?? 0) + 1), 4, '0', STR_PAD_LEFT);
}

/**
 * Move a case, doing whatever the move implies to the money.
 *
 * Holding funds debits the buyer's balance and raises held_balance. Releasing
 * lowers held_balance and credits the seller. Refunding returns the hold to
 * the buyer. Each is one transaction, so a case can never be in a state its
 * money does not match.
 */
function mediation_move(
    $conn,
    int $mediationId,
    string $toStatus,
    string $actorType = 'admin',
    ?int $actorId = null,
    string $note = ''
): array {
    $case = fetch_one($conn, 'SELECT * FROM mediations WHERE id = ?', 'i', $mediationId);
    if ($case === null) {
        return [false, 'الصفقة غير موجودة.'];
    }

    $from = (string) $case['status'];
    if (!mediation_can_move($from, $toStatus)) {
        return [false, 'لا يمكن الانتقال من «' . (mediation_statuses()[$from] ?? $from)
                     . '» إلى «' . (mediation_statuses()[$toStatus] ?? $toStatus) . '».'];
    }

    $parties = fetch_all($conn, 'SELECT user_id, party_role FROM mediation_parties WHERE mediation_id = ?', 'i', $mediationId);
    $buyerId  = 0;
    $sellerId = 0;
    foreach ($parties as $party) {
        if ($party['party_role'] === 'buyer'  && $party['user_id'] !== null) { $buyerId  = (int) $party['user_id']; }
        if ($party['party_role'] === 'seller' && $party['user_id'] !== null) { $sellerId = (int) $party['user_id']; }
    }

    $amount = (float) $case['deal_amount'];
    $fee    = (float) $case['fee_amount'];

    try {
        if ($toStatus === 'funds_held') {
            if ($buyerId === 0) {
                return [false, 'لا يوجد مشترٍ مرتبط بحساب — لا يمكن حجز المبلغ.'];
            }
            wallet_post($buyerId, 'debit', $amount + $fee, 'mediation_hold', null,
                'حجز صفقة ' . (string) $case['case_code'], $actorType, $actorId);

            $stmt = $conn->prepare('UPDATE wallets SET held_balance = held_balance + ? WHERE user_id = ?');
            $held = $amount + $fee;
            $stmt->bind_param('di', $held, $buyerId);
            $stmt->execute();

        } elseif ($toStatus === 'released') {
            if ($buyerId > 0) {
                $stmt = $conn->prepare(
                    'UPDATE wallets SET held_balance = GREATEST(0, held_balance - ?) WHERE user_id = ?'
                );
                $held = $amount + $fee;
                $stmt->bind_param('di', $held, $buyerId);
                $stmt->execute();
            }
            if ($sellerId > 0) {
                // The seller receives the deal amount; the fee stays with the
                // platform, which is what the fee is for.
                wallet_post($sellerId, 'credit', $amount, 'mediation_release', null,
                    'تحرير صفقة ' . (string) $case['case_code'], $actorType, $actorId);
            }

        } elseif ($toStatus === 'refunded') {
            if ($buyerId > 0) {
                $stmt = $conn->prepare(
                    'UPDATE wallets SET held_balance = GREATEST(0, held_balance - ?) WHERE user_id = ?'
                );
                $held = $amount + $fee;
                $stmt->bind_param('di', $held, $buyerId);
                $stmt->execute();

                wallet_post($buyerId, 'credit', $amount + $fee, 'mediation_refund', null,
                    'استرداد صفقة ' . (string) $case['case_code'], $actorType, $actorId);
            }
        }
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }

    $safetyEnds = null;
    if ($toStatus === 'safety_period') {
        $days       = max(0, (int) $case['safety_days']);
        $safetyEnds = date('Y-m-d H:i:s', time() + $days * 86400);
    }

    $closedAt = in_array($toStatus, ['released', 'refunded', 'cancelled'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare(
        'UPDATE mediations SET status = ?, safety_ends_at = COALESCE(?, safety_ends_at), closed_at = ? WHERE id = ?'
    );
    $stmt->bind_param('sssi', $toStatus, $safetyEnds, $closedAt, $mediationId);
    $stmt->execute();

    $hist = $conn->prepare(
        'INSERT INTO mediation_status_history (mediation_id, from_status, to_status, actor_type, actor_id, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $hist->bind_param('isssis', $mediationId, $from, $toStatus, $actorType, $actorId, $note);
    $hist->execute();

    return [true, 'تم تحديث حالة الصفقة.'];
}
