import { useRef, useState, type CSSProperties, type KeyboardEvent } from 'react';
import type { AttendanceCalendarData } from '../types/attendance';
import { attendanceDate } from './attendance-format';

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const legend = [
    'present',
    'late',
    'leave',
    'sick',
    'absent',
    'review',
    'off',
    'future',
    'unknown',
    'working',
    'not_employed',
];

export default function AttendanceCalendar({
    calendar: c,
    onSelect,
    busy,
}: {
    calendar: AttendanceCalendarData;
    onSelect: (date: string) => void;
    busy: boolean;
}) {
    const initial = c.days.findIndex((d) => d.date === c.selected.date);
    const [focus, setFocus] = useState(initial);
    const refs = useRef<(HTMLButtonElement | null)[]>([]);
    const move = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const shift: Record<string, number> = {
            ArrowUp: -1,
            ArrowDown: 1,
            ArrowLeft: -7,
            ArrowRight: 7,
        };
        let target: number;
        if (event.key === 'Home') target = 0;
        else if (event.key === 'End') target = c.days.length - 1;
        else if (event.key in shift)
            target = Math.max(0, Math.min(c.days.length - 1, index + shift[event.key]));
        else return;
        event.preventDefault();
        setFocus(target);
        refs.current[target]?.focus();
    };
    return (
        <section
            className="at-calendar"
            aria-labelledby="attendance-calendar-title"
            aria-busy={busy}
        >
            <div className="at-calendar-heading">
                <div>
                    <h2 id="attendance-calendar-title">Kalender kehadiran · {c.year}</h2>
                    <p>Satu kotak, satu hari. Warna menunjukkan status, bukan lama bekerja.</p>
                </div>
                <span>{c.days.length} hari kalender</span>
            </div>
            <div
                className="at-calendar-scroll"
                tabIndex={0}
                role="region"
                aria-label="Kalender tahunan, geser mendatar untuk bulan berikutnya"
            >
                <div className="at-calendar-layout" style={{ '--weeks': c.weeks } as CSSProperties}>
                    <div className="at-months" aria-hidden="true">
                        {c.days
                            .filter((d) => d.month_start)
                            .map((d) => (
                                <span key={d.date} style={{ gridColumn: d.week + 1 }}>
                                    {months[Number(d.date.slice(5, 7)) - 1]}
                                </span>
                            ))}
                    </div>
                    <div className="at-weekdays" aria-hidden="true">
                        {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((d) => (
                            <span key={d}>{d}</span>
                        ))}
                    </div>
                    <div
                        className="at-day-grid"
                        aria-label="Pilih tanggal untuk melihat detail"
                        aria-describedby="attendance-calendar-help"
                    >
                        {c.days.map((d, i) => (
                            <button
                                key={d.date}
                                ref={(el) => {
                                    refs.current[i] = el;
                                }}
                                type="button"
                                className={`at-day at-${d.status} ${d.date === c.today ? 'is-today' : ''} ${d.date === c.selected.date ? 'is-selected' : ''}`}
                                style={{ gridColumn: d.week + 1, gridRow: d.weekday + 1 }}
                                title={`${attendanceDate(d.date)} · ${d.label}`}
                                aria-label={`${attendanceDate(d.date)}: ${d.label}`}
                                aria-pressed={d.date === c.selected.date}
                                tabIndex={i === focus ? 0 : -1}
                                onFocus={() => setFocus(i)}
                                onKeyDown={(e) => move(e, i)}
                                onClick={() => onSelect(d.date)}
                            />
                        ))}
                    </div>
                </div>
            </div>
            <p className="at-help" id="attendance-calendar-help">
                Pilih kotak untuk detail. Tombol panah berpindah hari/minggu; Enter membuka tanggal.
                Di ponsel, geser kalender mendatar.
            </p>
            <ul className="at-legend" aria-label="Legenda status kehadiran">
                {legend.map((k) => (
                    <li key={k}>
                        <span className={`at-swatch at-${k}`} aria-hidden="true" />
                        {k === 'working' ? 'Jadwal / kehadiran berjalan' : c.labels[k]}
                    </li>
                ))}
            </ul>
        </section>
    );
}
