<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Append-only audit trail.
 *
 * Every controller/service action that creates, changes, deletes, approves
 * or reverses a business record is expected to call Audit::log() in the
 * same database transaction as the change itself, so an audit entry never
 * exists without its corresponding change and vice versa. Audit rows are
 * never updated or deleted by the application (no update/delete method is
 * exposed here on purpose) -- correcting a mistake must be done with a new
 * row (a correction/reversal entry), never by rewriting history.
 */
final class Audit
{
    /** @param array<string,mixed>|null $before @param array<string,mixed>|null $after */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?string $notes = null,
    ): void {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, before_data, after_data, notes, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :before_data, :after_data, :notes, :ip_address, :created_at)'
        );
        $stmt->execute([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'notes' => $notes,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
