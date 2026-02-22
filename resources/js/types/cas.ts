export type CasReportFormat = 'json' | 'pdf' | 'excel';

export type AccountRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
    parent_id: number | null;
    is_active: boolean;
};

export type JournalLinePayload = {
    account_id: number;
    debit: number;
    credit: number;
    particulars?: string;
    customer_id?: number | null;
    supplier_id?: number | null;
};

export type JournalPayload = {
    branch_id: number;
    journal_type:
        | 'general'
        | 'sales'
        | 'purchase'
        | 'cash_receipts'
        | 'cash_disbursements';
    entry_date: string;
    description: string;
    reference_no?: string | null;
    lines: JournalLinePayload[];
};

export type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
