export type ContractRecord = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    employee_archived: boolean;
    contract_number: string;
    type: 'pkwt' | 'pkwtt';
    starts_on: string;
    ends_on: string | null;
    notes?: string | null;
    version: number;
    status: 'active' | 'scheduled' | 'expired' | 'voided';
    status_label: string;
};
export type ContractSummary = {
    due_count: number;
    expired_count: number;
    recent: ContractRecord[];
};
