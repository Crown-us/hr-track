<?php

namespace Database\Seeders;

use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::where('role', 'admin')->orderBy('id')->first() ?? User::orderBy('id')->first();
        $employees = Employee::where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->take(3)
            ->get();

        if (! $actor || $employees->isEmpty()) {
            $this->command?->warn('Catatan pelanggaran demo dilewati: butuh akun HR dan pegawai aktif.');

            return;
        }

        $today = Carbon::now('Asia/Jakarta');
        $cases = [
            [
                'employee' => $employees[0],
                'reference_number' => 'DEMO-SP-001',
                'kind' => 'sp1',
                'incident_on' => $today->copy()->subDays(45)->toDateString(),
                'subject' => 'Keterlambatan berulang',
                'description' => 'Catatan demo: pegawai terlambat tiga kali dalam satu bulan dan sudah diberikan pembinaan lisan.',
                'follow_up_on' => $today->copy()->subDays(30)->toDateString(),
                'status' => 'resolved',
                'result' => 'Pegawai menyelesaikan pembinaan dan menunjukkan perbaikan kehadiran.',
            ],
            [
                'employee' => $employees[1] ?? $employees[0],
                'reference_number' => 'DEMO-KONS-001',
                'kind' => 'counseling',
                'incident_on' => $today->copy()->subDays(20)->toDateString(),
                'subject' => 'Konseling beban kerja',
                'description' => 'Catatan demo: sesi konseling untuk membahas pembagian beban kerja dan prioritas tim.',
                'follow_up_on' => $today->copy()->addDays(3)->toDateString(),
                'status' => 'open',
                'result' => null,
            ],
            [
                'employee' => $employees[2] ?? $employees[0],
                'reference_number' => 'DEMO-PEL-001',
                'kind' => 'violation',
                'incident_on' => $today->copy()->subDays(10)->toDateString(),
                'subject' => 'Pelanggaran prosedur keamanan data',
                'description' => 'Catatan demo: dokumen internal dibagikan melalui kanal yang belum disetujui.',
                'follow_up_on' => null,
                'status' => 'open',
                'result' => null,
            ],
        ];

        foreach ($cases as $case) {
            $record = DisciplinaryCase::firstOrCreate(
                ['reference_number' => $case['reference_number']],
                [
                    'employee_id' => $case['employee']->id,
                    'kind' => $case['kind'],
                    'incident_on' => $case['incident_on'],
                    'subject' => $case['subject'],
                    'description' => $case['description'],
                    'follow_up_on' => $case['follow_up_on'],
                    'created_by' => $actor->id,
                ]
            );

            if (! $record->wasRecentlyCreated) {
                continue;
            }

            $record->forceFill(['status' => $case['status']])->save();

            DB::table('discipline_events')->insert([
                'disciplinary_case_id' => $record->id,
                'actor_id' => $actor->id,
                'action' => 'created',
                'changes' => json_encode(['after' => $record->only(['reference_number', 'kind', 'incident_on', 'subject', 'follow_up_on', 'status'])], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            if ($case['result']) {
                DB::table('discipline_events')->insert([
                    'disciplinary_case_id' => $record->id,
                    'actor_id' => $actor->id,
                    'action' => 'resolved',
                    'changes' => json_encode(['reason' => $case['result']], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
