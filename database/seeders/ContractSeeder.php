<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::where('role', 'admin')->orderBy('id')->first() ?? User::orderBy('id')->first();
        $employees = Employee::where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->take(8)
            ->get();

        if (! $actor || $employees->isEmpty()) {
            $this->command?->warn('Kontrak demo dilewati: butuh akun HR dan pegawai aktif.');

            return;
        }

        $today = Carbon::now('Asia/Jakarta');
        $plans = [
            ['DEMO-PKWT-001', 'pkwt', $today->copy()->subYear()->toDateString(), $today->copy()->addDays(14)->toDateString(), 'Kontrak demo berakhir dalam dua minggu.'],
            ['DEMO-PKWT-002', 'pkwt', $today->copy()->subYears(2)->toDateString(), $today->copy()->subDays(20)->toDateString(), 'Kontrak demo sudah berakhir.'],
            ['DEMO-PKWTT-001', 'pkwtt', $today->copy()->subYears(2)->toDateString(), null, 'Kontrak tetap demo.'],
            ['DEMO-PKWT-003', 'pkwt', $today->copy()->subMonths(10)->toDateString(), $today->copy()->addDays(25)->toDateString(), 'Kontrak demo berakhir bulan depan.'],
            ['DEMO-PKWT-004', 'pkwt', $today->copy()->addDays(30)->toDateString(), $today->copy()->addYear()->toDateString(), 'Kontrak demo dijadwalkan mulai bulan depan.'],
            ['DEMO-PKWTT-002', 'pkwtt', $today->copy()->subYear()->toDateString(), null, 'Kontrak tetap demo untuk staf senior.'],
            ['DEMO-PKWT-005', 'pkwt', $today->copy()->subMonths(11)->toDateString(), $today->copy()->addDays(5)->toDateString(), 'Kontrak demo hampir berakhir.'],
            ['DEMO-PKWT-006', 'pkwt', $today->copy()->subYears(2)->toDateString(), $today->copy()->subDays(60)->toDateString(), 'Kontrak demo lama sudah berakhir.'],
        ];

        foreach ($employees as $index => $employee) {
            $plan = $plans[$index] ?? null;

            if (! $plan) {
                break;
            }

            [$number, $type, $startsOn, $endsOn, $notes] = $plan;

            $contract = EmployeeContract::firstOrCreate(
                ['contract_number' => $number],
                [
                    'employee_id' => $employee->id,
                    'type' => $type,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'notes' => $notes,
                    'created_by' => $actor->id,
                ]
            );

            if ($contract->wasRecentlyCreated) {
                DB::table('contract_events')->insert([
                    'employee_contract_id' => $contract->id,
                    'actor_id' => $actor->id,
                    'action' => 'created',
                    'changes' => json_encode(['after' => $contract->only(['contract_number', 'type', 'starts_on', 'ends_on', 'notes'])], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
