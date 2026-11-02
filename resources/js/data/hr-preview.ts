export const stages = ['Screening', 'Interview', 'Offering', 'Diterima', 'Ditolak'] as const;
export type Stage = (typeof stages)[number];
export const employees = [
    {
        id: 'EMP-001',
        name: 'Ayu Prameswari',
        team: 'People Operations',
        position: 'HR Generalist',
        joined: '12 Jan 2026',
        status: 'Tetap',
    },
    {
        id: 'EMP-002',
        name: 'Bima Saputra',
        team: 'Engineering',
        position: 'Frontend Developer',
        joined: '02 Feb 2026',
        status: 'Tetap',
    },
    {
        id: 'EMP-003',
        name: 'Citra Halim',
        team: 'Finance',
        position: 'Finance Analyst',
        joined: '16 Mar 2026',
        status: 'Kontrak',
    },
    {
        id: 'EMP-004',
        name: 'Dimas Nugroho',
        team: 'Marketing',
        position: 'Content Strategist',
        joined: '06 Apr 2026',
        status: 'Tetap',
    },
    {
        id: 'EMP-005',
        name: 'Nadia Kirana',
        team: 'Engineering',
        position: 'Product Designer',
        joined: '18 Mei 2026',
        status: 'Tetap',
    },
    {
        id: 'EMP-006',
        name: 'Fajar Ramadhan',
        team: 'People Operations',
        position: 'Recruiter',
        joined: '01 Jun 2026',
        status: 'Kontrak',
    },
    {
        id: 'EMP-007',
        name: 'Laras Wibowo',
        team: 'Marketing',
        position: 'Marketing Intern',
        joined: '03 Agu 2026',
        status: 'Magang',
    },
    {
        id: 'EMP-008',
        name: 'Rizky Mahendra',
        team: 'Finance',
        position: 'Accounting Staff',
        joined: '14 Sep 2026',
        status: 'Tetap',
    },
];
export const vacancies = [
    { title: 'Frontend Developer', team: 'Engineering', location: 'Jakarta · Hybrid' },
    { title: 'HR Generalist', team: 'People Operations', location: 'Jakarta · Kantor' },
    { title: 'Finance Staff', team: 'Finance', location: 'Jakarta · Kantor' },
];
export type Candidate = {
    id: string;
    name: string;
    job: string;
    email: string;
    applied: string;
    stage: Stage;
    note: string;
    interview?: string;
};
export const candidates: Candidate[] = [
    {
        id: 'APP-001',
        name: 'Nadia Putri',
        job: 'HR Generalist',
        email: 'nadia.putri@example.test',
        applied: '30 Okt 2026',
        stage: 'Screening',
        note: 'Berkas lengkap, menunggu tinjauan HR.',
    },
    {
        id: 'APP-002',
        name: 'Raka Pradana',
        job: 'Frontend Developer',
        email: 'raka.pradana@example.test',
        applied: '28 Okt 2026',
        stage: 'Interview',
        note: 'Wawancara teknis bersama tim Engineering.',
        interview: '09.00',
    },
    {
        id: 'APP-003',
        name: 'Sari Wulandari',
        job: 'Finance Staff',
        email: 'sari.wulandari@example.test',
        applied: '27 Okt 2026',
        stage: 'Offering',
        note: 'Menunggu tanggapan kandidat atas penawaran.',
    },
    {
        id: 'APP-004',
        name: 'Arif Setiawan',
        job: 'Frontend Developer',
        email: 'arif.setiawan@example.test',
        applied: '31 Okt 2026',
        stage: 'Screening',
        note: 'Portofolio sudah diterima.',
    },
    {
        id: 'APP-005',
        name: 'Maya Pertiwi',
        job: 'HR Generalist',
        email: 'maya.pertiwi@example.test',
        applied: '29 Okt 2026',
        stage: 'Interview',
        note: 'Diskusi pengalaman dan budaya kerja.',
        interview: '13.30',
    },
    {
        id: 'APP-006',
        name: 'Aditya Pangestu',
        job: 'Finance Staff',
        email: 'aditya.pangestu@example.test',
        applied: '31 Okt 2026',
        stage: 'Screening',
        note: 'Menunggu peninjauan pengalaman kerja.',
    },
    {
        id: 'APP-007',
        name: 'Intan Maharani',
        job: 'Frontend Developer',
        email: 'intan.maharani@example.test',
        applied: '20 Okt 2026',
        stage: 'Diterima',
        note: 'Konfirmasi penerimaan sudah dicatat.',
    },
    {
        id: 'APP-008',
        name: 'Yoga Pratama',
        job: 'HR Generalist',
        email: 'yoga.pratama@example.test',
        applied: '22 Okt 2026',
        stage: 'Ditolak',
        note: 'Proses seleksi telah selesai.',
    },
];
export const initials = (name: string) =>
    name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('');
