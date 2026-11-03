<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VacancySeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all()->keyBy('code');

        if ($departments->isEmpty()) {
            $this->command?->warn('Master departemen belum ada. Jalankan EmployeeMasterSeeder terlebih dahulu.');

            return;
        }

        $vacancies = [
            [
                'Software Engineer', 'ENG', 'full_time', 'Jakarta', 'published', -5, 45,
                'Membangun dan merawat aplikasi internal yang dipakai tim operasional dan HR. Posisi ini bekerja dekat dengan product owner, desainer, dan tim QA untuk merilis fitur secara bertahap.',
                'Minimal 2 tahun pengalaman dengan PHP atau JavaScript, terbiasa menulis tes otomatis, memahami dasar basis data relasional, dan nyaman bekerja dalam tim lintas fungsi.',
            ],
            [
                'HR Generalist', 'HR', 'full_time', 'Jakarta', 'published', -3, 30,
                'Mendukung administrasi kepegawaian, proses rekrutmen, dan kegiatan pengembangan karyawan. Anda akan menjadi penghubung antara tim HR dan karyawan untuk pertanyaan kebijakan sehari-hari.',
                'Pengalaman 1–3 tahun di bidang HR, kemampuan komunikasi tertulis yang baik, teliti mengelola data, dan memahami dasar-dasar ketenagakerjaan Indonesia.',
            ],
            [
                'Finance Staff', 'FIN', 'contract', 'Bandung', 'draft', 7, 60,
                'Membantu proses rekonsiliasi, pencatatan transaksi, dan penyusunan laporan keuangan bulanan. Posisi ini bekerja bersama tim akuntansi dan operasional cabang.',
                'Lulusan akuntansi atau keuangan, memahami dasar jurnal dan rekonsiliasi, mahir menggunakan spreadsheet, serta bersedia bekerja dari kantor Bandung.',
            ],
            [
                'Marketing Lead', 'MKT', 'full_time', 'Surabaya', 'closed', -40, -5,
                'Menyusun strategi pemasaran, memimpin kampanye digital, dan mengukur hasilnya bersama tim penjualan. Anda akan mengelola anggaran kampanye serta hubungan dengan mitra kreatif.',
                'Pengalaman 4 tahun di pemasaran, pernah memimpin tim kecil, kuat dalam analisis kampanye, dan memiliki portofolio peluncuran produk atau layanan.',
            ],
            [
                'Operations Staff', 'OPS', 'part_time', 'Medan', 'published', -2, 21,
                'Menjaga kelancaran operasional harian cabang, memeriksa kelengkapan dokumen, dan membantu koordinasi dengan mitra pengiriman. Jam kerja fleksibel dengan dua hari kerja di kantor.',
                'Teliti, komunikatif, mampu bekerja dengan tenggat harian, dan bersedia bekerja paruh waktu dari Medan. Pengalaman operasional menjadi nilai tambah.',
            ],
            [
                'Engineering Lead', 'ENG', 'full_time', 'Jakarta', 'draft', 14, 75,
                'Memimpin tim engineering, menjaga kualitas arsitektur, dan mendampingi anggotanya berkembang. Anda akan bekerja bersama kepala produk untuk menyusun prioritas teknis dan rencana rilis.',
                'Pengalaman 5 tahun di pengembangan perangkat lunak, pernah menjadi tech lead, memahami desain sistem, dan terbiasa melakukan code review serta mentoring.',
            ],
        ];

        $today = Carbon::now('Asia/Jakarta');

        foreach ($vacancies as [$title, $departmentCode, $type, $location, $status, $openOffset, $closeOffset, $description, $requirements]) {
            $department = $departments->get($departmentCode);

            if (! $department) {
                continue;
            }

            Vacancy::updateOrCreate(
                ['job_title' => $title],
                [
                    'department_id' => $department->id,
                    'employment_type' => $type,
                    'location' => $location,
                    'description' => $description,
                    'requirements' => $requirements,
                    'opening_date' => $today->copy()->addDays($openOffset)->toDateString(),
                    'closing_date' => $today->copy()->addDays($closeOffset)->toDateString(),
                    'status' => $status,
                ]
            );
        }
    }
}
