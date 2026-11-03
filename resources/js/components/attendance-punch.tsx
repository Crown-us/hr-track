import { router, useForm } from '@inertiajs/react';
import { LogIn, LogOut, RefreshCw } from 'lucide-react';
import { attendanceDate } from './attendance-format';
import type { AttendancePunchData } from '../types/attendance';

const time = (v: string | null) =>
    v ? `${attendanceDate(v.slice(0, 10))} · ${v.slice(11, 16)} WIB` : 'Belum dicatat';

function PunchDay({ day }: { day: AttendancePunchData['days'][number] }) {
    const action = day.can_in ? 'in' : 'out';
    const f = useForm({ day_id: day.id, version: day.version, action, confirm: false });
    const available = day.can_in || day.can_out;
    return (
        <form
            className="at-punch-day"
            onSubmit={(e) => {
                e.preventDefault();
                if (available && f.data.confirm) {
                    f.post('/attendance/punch', { preserveScroll: true, preserveState: false });
                }
            }}
        >
            <div>
                <h3>Jadwal {attendanceDate(day.date)}</h3>
                <p>
                    {time(day.scheduled_start)} — {time(day.scheduled_end)}
                </p>
            </div>
            <dl className="at-punch-times">
                <div>
                    <dt>Masuk</dt>
                    <dd>{time(day.clock_in)}</dd>
                </div>
                <div>
                    <dt>Pulang</dt>
                    <dd>{time(day.clock_out)}</dd>
                </div>
            </dl>
            <p className="at-punch-message">{day.message}</p>
            {available && (
                <div className="at-punch-action">
                    <label className="at-check">
                        <input
                            type="checkbox"
                            checked={f.data.confirm}
                            onChange={(e) => f.setData('confirm', e.target.checked)}
                        />
                        Saya siap mencatat {day.can_in ? 'masuk' : 'pulang'} sekarang.
                    </label>
                    <button className="ep-primary" disabled={f.processing || !f.data.confirm}>
                        {day.can_in ? <LogIn size={16} /> : <LogOut size={16} />}
                        {f.processing ? 'Menyimpan…' : day.can_in ? 'Catat masuk' : 'Catat pulang'}
                    </button>
                </div>
            )}
            {!day.clock_out && day.clock_in && (
                <p className="at-caption">
                    Batas pulang: {time(day.checkout_deadline)}. Lewat batas, hubungi HR.
                </p>
            )}
        </form>
    );
}

export default function AttendancePunch({ data }: { data: AttendancePunchData }) {
    return (
        <section className="ep-card at-punch" aria-labelledby="punch-title">
            <div className="at-punch-heading">
                <div>
                    <h2 id="punch-title">Catat kehadiran</h2>
                    <p>
                        Jam diambil dari server saat disimpan. Pencatatan web tidak memeriksa
                        lokasi.
                    </p>
                </div>
                <button
                    type="button"
                    className="ep-secondary"
                    onClick={() => router.reload({ only: ['punch', 'calendar'] })}
                >
                    <RefreshCw size={14} /> Perbarui status
                </button>
            </div>
            {data.days.length ? (
                data.days.map((day) => (
                    <PunchDay
                        key={`${day.id}-${day.version}-${day.can_in}-${day.can_out}`}
                        day={day}
                    />
                ))
            ) : (
                <p className="at-punch-message">
                    Belum ada jadwal untuk dicatat saat ini. Hubungi HR untuk pengaturan jadwal.
                </p>
            )}
            <p className="at-caption">
                Masuk tersedia mulai 60 menit sebelum awal hingga sebelum akhir jadwal. Pulang
                maksimal 4 jam setelah akhir jadwal, dengan durasi hadir maksimal 24 jam. Status
                diperbarui{' '}
                {new Date(data.server_now).toLocaleTimeString('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    hour: '2-digit',
                    minute: '2-digit',
                })}{' '}
                WIB.
            </p>
        </section>
    );
}
