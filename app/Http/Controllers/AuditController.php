<?php

namespace App\Http\Controllers;

use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditController
{
    public function index(Request $request, AuditTrail $audit): Response
    {
        $filters = $request->validate([
            'source' => ['nullable', Rule::in(array_keys(AuditTrail::SOURCES))],
            'action' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $source = $filters['source'] ?? null;

        return Inertia::render('audit', [
            'filters' => ['source' => $source ?? '', 'action' => $filters['action'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'modules' => $audit->modules(),
            'actions' => $audit->actions($source),
            'events' => $audit->page($filters),
        ]);
    }
}
