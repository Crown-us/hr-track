<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrainingSeeder extends Seeder
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
            $this->command?->warn('Pelatihan demo dilewati: butuh akun HR dan pegawai aktif.');

            return;
        }

        $completed = Training::firstOrCreate(
            ['title' => 'Orientasi Nilai Perusahaan'],
            [
                'trainer' => 'Tim People Operations',
                'location' => 'Ruang Pelatihan Lantai 2',
                'starts_at' => Carbon::now('UTC')->subDays(12)->setTime(2, 0),
                'ends_at' => Carbon::now('UTC')->subDays(12)->setTime(5, 0),
                'status' => 'completed',
                'notes' => 'Pelatihan demo yang sudah selesai dengan evaluasi peserta.',
                'created_by' => $actor->id,
            ]
        );

        $scheduled = Training::firstOrCreate(
            ['title' => 'Komunikasi Efektif untuk Tim'],
            [
                'trainer' => 'Nadia Prameswari',
                'location' => 'Ruang Rapat Utama',
                'starts_at' => Carbon::now('UTC')->addDays(7)->setTime(2, 0),
                'ends_at' => Carbon::now('UTC')->addDays(7)->setTime(5, 0),
                'status' => 'scheduled',
                'notes' => 'Pelatihan demo yang akan datang.',
                'created_by' => $actor->id,
            ]
        );

        Training::firstOrCreate(
            ['title' => 'Kepatuhan Data dan Privasi'],
            [
                'trainer' => 'Tim Legal',
                'location' => 'Ruang Pelatihan Lantai 2',
                'starts_at' => Carbon::now('UTC')->addDays(14)->setTime(2, 0),
                'ends_at' => Carbon::now('UTC')->addDays(14)->setTime(4, 0),
                'status' => 'cancelled',
                'notes' => 'Dibatalkan karena bentrok dengan agenda audit internal.',
                'created_by' => $actor->id,
            ]
        );

        $this->participant($completed, $employees[0], 'attended', 78, 90);
        $this->participant($completed, $employees[1], 'attended', 65, 82);
        $this->participant($completed, $employees[2], 'attended', 72, 72);
        $this->participant($completed, $employees[3], 'absent', null, null);

        $this->participant($scheduled, $employees[0], 'registered', null, null);
        $this->participant($scheduled, $employees[1], 'registered', null, null);
        $this->participant($scheduled, $employees[4] ?? $employees[3], 'registered', null, null);

        foreach ([$completed, $scheduled] as $training) {
            if ($training->wasRecentlyCreated) {
                DB::table('training_events')->insert([
                    'training_id' => $training->id,
                    'actor_id' => $actor->id,
                    'action' => 'created',
                    'changes' => json_encode(['after' => $training->only(['title', 'trainer', 'location', 'starts_at', 'ends_at', 'status'])], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function participant(Training $training, Employee $employee, string $attendance, ?float $preTest, ?float $postTest): void
    {
        TrainingParticipant::firstOrCreate(
            ['training_id' => $training->id, 'employee_id' => $employee->id],
            [
                'attendance' => $attendance,
                'pre_test' => $preTest,
                'post_test' => $postTest,
                'notes' => $attendance === 'attended' ? 'Peserta demo mengikuti seluruh sesi.' : null,
            ]
        );
    }
}
