import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import type { LanyardMember, TeamLanyardRailLabels } from './ui/team-lanyard-rail';

const TeamLanyardRail = lazy(() => import('./ui/team-lanyard-rail'));

// Sample people only. They reuse the names shown in the table and the recruitment cards.
const sampleTeam: LanyardMember[] = [
    {
        id: 'ayu',
        name: 'Ayu Prameswari',
        role: 'Staf HR',
        team: 'People Ops',
        tone: 1,
        bio: 'Menyusun jadwal wawancara dan memeriksa kelengkapan berkas kandidat.',
        tags: ['Rekrutmen', 'Jadwal'],
    },
    {
        id: 'bima',
        name: 'Bima Saputra',
        role: 'Software Engineer',
        team: 'Engineering',
        tone: 2,
        bio: 'Meninjau berkas lamaran untuk posisi teknis dan mencatat hasil wawancara.',
        tags: ['Wawancara', 'Evaluasi'],
    },
    {
        id: 'citra',
        name: 'Citra Halim',
        role: 'Finance Staff',
        team: 'Finance',
        tone: 3,
        bio: 'Memantau status cuti dan masa berlaku kontrak untuk tim keuangan.',
        tags: ['Cuti', 'Kontrak'],
    },
    {
        id: 'nadia',
        name: 'Nadia Putri',
        role: 'People Operations',
        team: 'People Ops',
        tone: 4,
        bio: 'Menyiapkan penawaran kerja dan persiapan orientasi pegawai baru.',
        tags: ['Penawaran', 'Orientasi'],
    },
    {
        id: 'raka',
        name: 'Raka Pradana',
        role: 'Frontend Developer',
        team: 'Engineering',
        tone: 5,
        bio: 'Membaca rekap proses rekrutmen sebelum menentukan tindak lanjut.',
        tags: ['Rekap', 'Tindak lanjut'],
    },
];

const labels: TeamLanyardRailLabels = {
    flip: 'Klik atau tekan Enter untuk membalik badge',
    back: 'Klik lagi atau tekan Esc untuk membaliknya',
    show: 'Tampilkan',
};

function useNearViewport() {
    const ref = useRef<HTMLDivElement>(null);
    const [isNear, setIsNear] = useState(false);

    useEffect(() => {
        const node = ref.current;
        if (!node) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry?.isIntersecting) return;
                setIsNear(true);
                observer.disconnect();
            },
            { rootMargin: '300px 0px' },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    return { ref, isNear };
}

// Loads the badge rail only when the visitor scrolls close to it, so first paint stays light.
export function TeamLanyardSection() {
    const { ref, isNear } = useNearViewport();

    return (
        <section id="tim" className="taste-team taste-wrap" data-od-id="team-badges">
            <div ref={ref} className="taste-team-stage">
                {isNear && (
                    <Suspense fallback={null}>
                        <TeamLanyardRail
                            items={sampleTeam}
                            eyebrow="Direktori pegawai · Data contoh"
                            heading="Satu badge, satu profil."
                            caption="Sapu rel dengan kursor, tarik badge ke bawah, lalu klik untuk membaliknya. Nama dan data pada badge adalah data contoh."
                            labels={labels}
                            className="rounded-2xl border border-border"
                        />
                    </Suspense>
                )}
            </div>
        </section>
    );
}
