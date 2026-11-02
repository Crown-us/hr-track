import type { HrUser } from '../components/hr-shell';

export type VacancyRecord = {
    id: number;
    job_title: string;
    department_id: number;
    department: string;
    employment_type: string;
    type_label: string;
    location: string;
    description?: string;
    requirements?: string;
    opening_date: string;
    closing_date: string;
    status: string;
    state_label: string;
    version: number;
};
export type VacancyProps = {
    auth: { user: HrUser };
    flash: { success: string | null };
    mode: 'index' | 'create' | 'edit' | 'show';
    today: string;
    vacancy: VacancyRecord | null;
    canManage: boolean;
    departments: { id: number; name: string }[];
    types: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    filters?: {
        q?: string;
        department_id?: string;
        employment_type?: string;
        status?: string;
        sort?: string;
    };
    vacancies?: {
        data: VacancyRecord[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; active: boolean; label: string }[];
    };
};
