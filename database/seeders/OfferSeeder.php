<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Position;
use App\Models\RecruitmentOffer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::where('role', 'admin')->orderBy('id')->first() ?? User::where('role', 'hr_staff')->orderBy('id')->first();

        if (! $actor) {
            $this->command?->warn('Penawaran contoh dilewati: butuh akun Admin/Staf HR.');

            return;
        }

        $applications = Application::whereIn('status', ['offering', 'accepted'])
            ->with('vacancy')
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            if (RecruitmentOffer::where('application_id', $application->id)->exists()) {
                continue;
            }

            $vacancy = $application->vacancy;

            if (! $vacancy) {
                continue;
            }

            $position = Position::where('department_id', $vacancy->department_id)->orderBy('id')->first();

            if (! $position) {
                continue;
            }

            $accepted = $application->status === ApplicationStatus::Accepted;
            $today = Carbon::now('Asia/Jakarta');
            $offer = RecruitmentOffer::create([
                'application_id' => $application->id,
                'department_id' => $vacancy->department_id,
                'position_id' => $position->id,
                'employment_status' => match ($vacancy->employment_type->value) {
                    'contract' => 'contract',
                    'internship' => 'intern',
                    'part_time' => 'probation',
                    default => 'permanent',
                },
                'join_date' => $today->copy()->addDays(30)->toDateString(),
                'response_deadline' => $today->copy()->addDays(14)->toDateString(),
                'terms' => 'Penawaran mencakup gaji pokok, tunjangan kesehatan, dan cuti tahunan sesuai kebijakan perusahaan.',
                'status' => $accepted ? 'accepted' : 'pending',
                'response_date' => $accepted ? $today->copy()->subDays(2)->toDateString() : null,
                'response_note' => $accepted ? 'Kandidat menerima penawaran melalui konfirmasi tertulis.' : null,
            ]);

            DB::table('recruitment_offers')->where('id', $offer->id)->update([
                'created_at' => $today->copy()->subDays(6),
                'updated_at' => $accepted ? $today->copy()->subDays(3) : now(),
            ]);

            DB::table('recruitment_offer_events')->insert([
                'recruitment_offer_id' => $offer->id,
                'actor_id' => $actor->id,
                'action' => 'created',
                'changes' => json_encode(['after' => $offer->only(['department_id', 'position_id', 'employment_status', 'join_date', 'response_deadline', 'status'])], JSON_THROW_ON_ERROR),
                'created_at' => $today->copy()->subDays(6),
            ]);

            if ($accepted) {
                DB::table('recruitment_offer_events')->insert([
                    'recruitment_offer_id' => $offer->id,
                    'actor_id' => $actor->id,
                    'action' => 'accepted',
                    'changes' => json_encode(['reason' => $offer->response_note], JSON_THROW_ON_ERROR),
                    'created_at' => $today->copy()->subDays(2),
                ]);
            }
        }
    }
}
