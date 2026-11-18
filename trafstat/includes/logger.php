<?php
/**
 * includes/logger.php
 * Append-only activity logging with a SHA-256 hash chain.
 *
 * Every entry stores the hash of the previous entry, so any silent edit or
 * deletion of a historical row breaks the chain and becomes detectable.
 *
 * NEVER pass passwords, session identifiers, or unnecessary personal data
 * into $description or $details.
 */

function log_activity(
    ?int $adminId,
    string $eventType,
    string $description,
    ?string $relatedTable = null,
    ?int $relatedRecordId = null,
    array $details = []
): void {
    try {
        $pdo = db();

        $previousHash = $pdo->query(
            'SELECT entry_hash FROM activity_logs ORDER BY log_id DESC LIMIT 1'
        )->fetchColumn();

        if (!is_string($previousHash) || $previousHash === '') {
            $previousHash = str_repeat('0', 64);
        }

        $createdAt = date('Y-m-d H:i:s');
        $ipAddress = client_ip();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $detailsJson = $details === []
            ? null
            : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = implode('|', [
            $previousHash,
            (string) ($adminId ?? ''),
            $eventType,
            $description,
            $relatedTable ?? '',
            (string) ($relatedRecordId ?? ''),
            $detailsJson ?? '',
            $ipAddress,
            $createdAt,
        ]);

        $entryHash = hash('sha256', $payload);

        $statement = $pdo->prepare(
            'INSERT INTO activity_logs
                (admin_id, event_type, action_description, related_table,
                 related_record_id, details, ip_address, user_agent,
                 previous_hash, entry_hash, created_at)
             VALUES
                (:admin_id, :event_type, :description, :related_table,
                 :related_record_id, :details, :ip_address, :user_agent,
                 :previous_hash, :entry_hash, :created_at)'
        );

        $statement->execute([
            ':admin_id'         => $adminId,
            ':event_type'       => $eventType,
            ':description'      => $description,
            ':related_table'    => $relatedTable,
            ':related_record_id'=> $relatedRecordId,
            ':details'          => $detailsJson,
            ':ip_address'       => $ipAddress,
            ':user_agent'       => $userAgent,
            ':previous_hash'    => $previousHash,
            ':entry_hash'       => $entryHash,
            ':created_at'       => $createdAt,
        ]);
    } catch (Throwable $exception) {
        // Logging must never break the user-facing request.
        error_log('[TrafStat][LOG] ' . $exception->getMessage());
    }
}

/**
 * Recompute the entire activity-log hash chain and report its integrity.
 *
 * Returns:
 *   ok                 bool   true if no break detected
 *   total_checked      int    number of entries walked
 *   first_broken_log_id int   the log_id where a mismatch was found (or null)
 *   reason             string human-readable diagnosis
 *   truncation_warning string notice when the chain begins mid-stream
 *
 * Tamper-evidence, not tamper-proofing: an attacker with write access to the
 * table and knowledge of the algorithm could forge a new valid chain. This
 * detects casual edits and deletions, not sophisticated attackers.
 */
function verify_activity_log_chain(): array
{
    $rows = db_all(
        'SELECT log_id, admin_id, event_type, action_description,
                related_table, related_record_id, details, ip_address,
                previous_hash, entry_hash, created_at
           FROM activity_logs
       ORDER BY log_id ASC'
    );

    $total = count($rows);

    if ($total === 0) {
        return [
            'ok' => true,
            'total_checked' => 0,
            'first_broken_log_id' => null,
            'reason' => 'No log entries to verify.',
            'truncation_warning' => null,
        ];
    }

    $expectedPrevious   = null;
    $firstBroken        = null;
    $reason             = null;
    $truncationWarning  = null;
    $zeroHash           = str_repeat('0', 64);

    foreach ($rows as $index => $row) {
        if ($index === 0) {
            if ($row['previous_hash'] !== $zeroHash) {
                $truncationWarning =
                    'Chain appears truncated — the earliest retained entry has a '
                    . 'non-zero previous_hash. This is expected if log retention '
                    . 'has purged older entries.';
            }
            $expectedPrevious = $row['previous_hash'];
        }

        if ($row['previous_hash'] !== $expectedPrevious) {
            $firstBroken = (int) $row['log_id'];
            $reason      = 'previous_hash mismatch at log_id ' . $row['log_id'];
            break;
        }

        $detailsJson = $row['details'] ?? '';

        $payload = implode('|', [
            $row['previous_hash'],
            (string) ($row['admin_id'] ?? ''),
            $row['event_type'],
            $row['action_description'],
            $row['related_table'] ?? '',
            (string) ($row['related_record_id'] ?? ''),
            $detailsJson,
            $row['ip_address'],
            $row['created_at'],
        ]);

        $recomputed = hash('sha256', $payload);

        if (!hash_equals($row['entry_hash'], $recomputed)) {
            $firstBroken = (int) $row['log_id'];
            $reason      = 'entry_hash mismatch at log_id ' . $row['log_id'];
            break;
        }

        $expectedPrevious = $row['entry_hash'];
    }

    return [
        'ok'                 => $firstBroken === null,
        'total_checked'      => $total,
        'first_broken_log_id' => $firstBroken,
        'reason'             => $reason,
        'truncation_warning' => $truncationWarning,
    ];
}   