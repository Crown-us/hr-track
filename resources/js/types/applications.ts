import type { HrUser } from '../components/hr-shell';
export type ApplicationRow = {
    id: number;
    applicant_id: number;
    vacancy_id: number;
    version: number;
    name: string;
    email: string;
    job_title: string;
    status: string;
    status_label: string;
    application_date: string;
    notes?: string | null;
    phone?: string | null;
    source?: 'internal' | 'public';
    email_verified_at?: string | null;
    submitted_name?: string | null;
    submitted_phone?: string | null;
};
export type Stage = { value: string; label: string };
export type RecruitmentSummary = {
    total: number;
    applicants: number;
    counts: Record<string, number>;
    recent: ApplicationRow[];
};
export type ApplicationProps = {
    auth: { user: HrUser };
    flash: { success: string | null };
    canManage: boolean;
    stages: Stage[];
    mode: 'index' | 'create' | 'show';
    application?: ApplicationRow;
    applications?: {
        data: ApplicationRow[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; active: boolean; label: string }[];
    };
    filters?: { q?: string; vacancy_id?: string; status?: string; view?: 'board' | 'list' };
    counts?: Record<string, number>;
    vacancies?: { id: number; job_title: string }[];
    today?: string;
    applicants?: { id: number; full_name: string; email: string }[];
    selectedApplicant?: { id: number; full_name: string; email: string } | null;
    applicantSearch?: string;
    existingMode?: boolean;
    histories?: {
        id: number;
        label: string;
        actor: string;
        created_at: string;
        note?: string | null;
    }[];
    documents?: { id: number; size_bytes: number; created_at: string }[];
    nextStages?: Stage[];
};
