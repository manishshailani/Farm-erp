<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Services\LivestockService;

/**
 * RFID scanning workflow (spec section 5.2). The "scan" form field is a
 * plain text input that is auto-focused and auto-submits on Enter -- a
 * USB keyboard-wedge reader and manual typing both work through exactly
 * this one field, with no reader-specific code. See the class docblock
 * on LivestockService for what is and is not supported.
 */
final class RfidController
{
    public function scanForm(Request $request, array $args): void
    {
        View::render('admin/livestock/rfid_scan', ['result' => null]);
    }

    public function scan(Request $request, array $args): void
    {
        $tag = trim($request->string('tag_number'));
        $animal = $tag !== '' ? LivestockService::resolveTag($tag, Auth::id()) : null;

        View::render('admin/livestock/rfid_scan', [
            'result' => $tag === '' ? null : ['tag' => $tag, 'animal' => $animal],
        ]);
    }
}
