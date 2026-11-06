import type { HrUser } from '../components/hr-shell';

export type EmployeeRecord = {
    id: number;
    employee_number: string;
    full_name: string;
    work_email?: string;
    phone?: string | null;
    department_id: number;
    position_id: number;
    department: string;
    position: string;
    employment_status: string;
    status_label: string;
    join_date: string;
    archived_at: string | null;
    version: number;
};
export type EmployeeProps = {
    auth: { user: HrUser };
    flash: { success: string | null };
    mode: 'index' | 'create' | 'edit' | 'show' | 'archive';
    employee: EmployeeRecord | null;
    departments: { id: number; name: string }[];
    positions: { id: number; department_id: number; name: string }[];
    statuses: { value: string; label: string }[];
    can: { create: boolean; admin: boolean };
    filters?: {
        q?: string;
        department_id?: string;
        status?: string;
        sort?: string;
        archived?: boolean;
    };
    employees?: {
        data: EmployeeRecord[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    events: {
        id: number;
        action: string;
        reason: string | null;
        actor: string;
        created_at: string;
        has_document: boolean;
        changes: { before: Record<string, string>; after: Record<string, string> } | null;
    }[];
};
