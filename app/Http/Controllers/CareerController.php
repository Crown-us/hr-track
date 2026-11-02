<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Http\Requests\ApplicationRequest;
use App\Models\Department;
use App\Models\Vacancy;
use App\Services\PublicApplications;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CareerController
{
    public static function job(Vacancy $v, bool $details = false): array
    {
        return [...$v->only('id', 'job_title', 'location'), 'department' => $v->department->name, 'employment_type' => $v->employment_type->value, 'type_label' => $v->employment_type->label(), 'opening_date' => $v->opening_date->format('Y-m-d'), 'closing_date' => $v->closing_date->format('Y-m-d'), ...($details ? $v->only('description', 'requirements') : [])];
    }

    public function index(Request $r)
    {
        $filters = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'type' => ['nullable', Rule::enum(EmploymentType::class)], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = Vacancy::open()->with('department');
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(fn ($q2) => $q2->where('job_title', 'like', '%'.$q.'%')->orWhere('location', 'like', '%'.$q.'%'));
        }if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }if (! empty($filters['type'])) {
            $query->where('employment_type', $filters['type']);
        }

        return Inertia::render('careers', ['mode' => 'index', 'filters' => $filters, 'vacancies' => $query->latest('id')->paginate(12)->withQueryString()->through(fn ($v) => self::job($v)), 'departments' => Department::whereIn('id', Vacancy::open()->select('department_id'))->orderBy('name')->get(['id', 'name']), 'types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], EmploymentType::cases())]);
    }

    public function show(Vacancy $vacancy)
    {
        abort_unless(Vacancy::open()->whereKey($vacancy->id)->exists(), 404);

        return Inertia::render('careers', ['mode' => 'show', 'vacancy' => self::job($vacancy->load('department'), true)]);
    }

    public function form(Vacancy $vacancy)
    {
        abort_unless(Vacancy::open()->whereKey($vacancy->id)->exists(), 404);

        return Inertia::render('careers', ['mode' => 'apply', 'vacancy' => self::job($vacancy->load('department'))]);
    }

    public function store(Request $r, Vacancy $vacancy, PublicApplications $applications)
    {
        abort_unless(Vacancy::open()->whereKey($vacancy->id)->exists(), 404);
        $data = $r->validate(['full_name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9() .-]{8,32}$/'], 'consent' => ['accepted'], 'website' => ['nullable', 'string', 'max:255'], 'cv' => ['required', ...ApplicationRequest::cvRules()]], ['consent.accepted' => 'Konfirmasikan penggunaan data untuk proses lamaran ini.', 'cv.required' => 'Lampirkan CV PDF.', 'cv.mimes' => 'CV harus berupa PDF.', 'cv.max' => 'CV maksimal 5 MiB.', 'phone.regex' => 'Gunakan 8–32 karakter angka, spasi atau tanda telepon.'], ['full_name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Telepon', 'cv' => 'CV']);
        if (empty($data['website'])) {
            $applications->submit($vacancy, $data, $r->file('cv'));
        }

        return redirect('/karier/terima-kasih');
    }

    public function thanks()
    {
        return Inertia::render('careers', ['mode' => 'thanks']);
    }
}
