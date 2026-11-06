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
export type MissingContractEmployee = {
    id: number;
    employee_number: string;
    full_name: string;
    status: string;
    status_label: string;
    department: string;
    join_date: string;
};
export type ContractSummary = {
    due_count: number;
    expired_count: number;
    recent: ContractRecord[];
};
