export type AttendanceRecord = {
    id: number;
    version: number;
    scheduled_start: string;
    scheduled_end: string;
    clock_in: string | null;
    clock_out: string | null;
    tolerance_minutes: number;
    voided: boolean;
    source: string;
    clock_in_source: 'web' | 'hr' | null;
    clock_out_source: 'web' | 'hr' | null;
};
export type AttendancePunchData = {
    server_now: string;
    days: (AttendanceRecord & {
        date: string;
        can_in: boolean;
        can_out: boolean;
        message: string;
        checkout_deadline: string;
    })[];
};
export type AttendanceCell = {
    date: string;
    status: string;
    label: string;
    week: number;
    weekday: number;
    month_start: boolean;
};
export type AttendanceCalendarData = {
    year: number;
    today: string;
    weeks: number;
    days: AttendanceCell[];
    summary: { present: number; on_time: number; late: number; review: number; absent: number };
    selected: AttendanceCell & {
        record: AttendanceRecord | null;
        late_minutes: number;
        leave: { type: string; label: string } | null;
        holiday: string | null;
    };
    events: { id: number; action: string; actor: string; reason: string; created_at: string }[];
    labels: Record<string, string>;
};
