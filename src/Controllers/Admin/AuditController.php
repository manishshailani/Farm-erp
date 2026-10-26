<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\View;
use App\Models\AuditLog;

/**
 * Read-only admin screen over the append-only audit_logs table (see
 * App\Core\Audit, which is the only thing that writes to it). This
 * closes the gap noted in docs/FINAL_ACCEPTANCE_REPORT.md ("no UI to
 * browse the audit log") -- it is intentionally read-only: there is no
 * route here that updates or deletes a row, matching Audit::class's own
 * append-only design.
 */
final class AuditController
{
    public function index(Request $request, array $args): void
    {
        $filters = [
            'entity_type' => $request->string('entity_type'),
            'user_id' => $request->int('user_id'),
            'q' => $request->string('q'),
        ];

        View::render('admin/audit/index', [
            'logs' => AuditLog::search($filters),
            'entityTypes' => AuditLog::distinctEntityTypes(),
            'filters' => $filters,
        ]);
    }
}
