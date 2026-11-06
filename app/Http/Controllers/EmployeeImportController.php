<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeImportRequest;
use App\Models\Employee;
use App\Services\EmployeeImportReader;
use App\Services\EmployeeImports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController
{
    public function create(Request $request, EmployeeImports $imports): Response
    {
        Gate::authorize('create', Employee::class);
        $batch = $request->validate(['batch' => ['nullable', 'string', 'size:40']])['batch'] ?? null;

        return Inertia::render('employee-import', [
            'preview' => $batch ? $imports->review($request->user(), $batch) : null,
            'limits' => ['rows' => EmployeeImportReader::MAX_ROWS, 'megabytes' => 2],
            'columns' => array_map(fn (string $key) => ['label' => EmployeeImportReader::LABELS[$key], 'required' => in_array($key, EmployeeImportReader::REQUIRED, true)], array_keys(EmployeeImportReader::LABELS)),
        ]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', Employee::class);

        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF".implode(';', EmployeeImportReader::LABELS)."\r\n";
        }, 'template-impor-pegawai.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(EmployeeImportRequest $request, EmployeeImports $imports)
    {
        $token = $imports->preview($request->user(), $request->file('file'));

        return to_route('employees.import', ['batch' => $token]);
    }

    public function store(Request $request, EmployeeImports $imports)
    {
        Gate::authorize('create', Employee::class);
        $batch = $request->validate(['batch' => ['required', 'string', 'size:40']])['batch'];
        try {
            $count = $imports->commit($request->user(), $batch);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('employees.import', ['batch' => $batch]));
        }

        return to_route('employees.index')->with('success', $count.' pegawai berhasil diimpor.');
    }
}
