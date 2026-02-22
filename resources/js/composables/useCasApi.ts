import type { CasReportFormat } from '@/types';

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function request<T>(
    url: string,
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' = 'GET',
    body?: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        const message =
            payload?.message ??
            payload?.error ??
            `Request failed with status ${response.status}`;
        throw new Error(message);
    }

    return payload as T;
}

export function useCasApi() {
    return {
        get: <T>(url: string) => request<T>(url, 'GET'),
        post: <T>(url: string, body?: unknown) => request<T>(url, 'POST', body),
        put: <T>(url: string, body?: unknown) => request<T>(url, 'PUT', body),
        patch: <T>(url: string, body?: unknown) =>
            request<T>(url, 'PATCH', body),
        del: <T>(url: string) => request<T>(url, 'DELETE'),
        reportUrl: (
            endpoint: string,
            params: {
                from_date: string;
                to_date: string;
                branch_id?: number;
                period?: 'monthly' | 'quarterly' | 'annually';
                format?: CasReportFormat;
                customer_id?: number;
                supplier_id?: number;
            },
        ): string => {
            const search = new URLSearchParams();
            search.set('from_date', params.from_date);
            search.set('to_date', params.to_date);

            if (params.branch_id !== undefined) {
                search.set('branch_id', String(params.branch_id));
            }
            if (params.period !== undefined && params.period.length > 0) {
                search.set('period', params.period);
            }
            if (params.format !== undefined) {
                search.set('format', params.format);
            }
            if (params.customer_id !== undefined) {
                search.set('customer_id', String(params.customer_id));
            }
            if (params.supplier_id !== undefined) {
                search.set('supplier_id', String(params.supplier_id));
            }

            return `/api/${endpoint}?${search.toString()}`;
        },
    };
}
