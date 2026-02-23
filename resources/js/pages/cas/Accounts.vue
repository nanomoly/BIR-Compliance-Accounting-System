<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountRow, BreadcrumbItem, PaginatedResponse } from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Chart of Accounts', href: '/cas/accounts' },
];

const form = reactive({
    branch_id: 1,
    parent_id: null as number | null,
    code: '',
    name: '',
    type: 'asset',
    normal_balance: 'debit',
    is_active: true,
    is_control_account: false,
});

const state = reactive({
    accounts: [] as AccountRow[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    error: '',
    success: '',
});

useStateNotifications(state);

async function loadAccounts(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<PaginatedResponse<AccountRow>>(
            `/api/accounts?per_page=${state.perPage}&page=${page}`,
        );
        state.accounts = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load accounts.';
    } finally {
        state.loading = false;
    }
}

function exportAccounts() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/accounts?${query.toString()}`, '_blank');
}

async function createAccount() {
    if (!can('accounts.create')) {
        return;
    }

    state.error = '';
    state.success = '';

    try {
        await api.post('/api/accounts', form);
        state.success = 'Account created.';
        form.code = '';
        form.name = '';
        form.parent_id = null;
        await loadAccounts();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to create account.';
    }
}

async function deleteAccount(accountId: number) {
    if (!can('accounts.delete')) {
        return;
    }

    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/accounts/${accountId}`);
        state.success = 'Account deleted.';
        await loadAccounts(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to delete account.';
    }
}

onMounted(loadAccounts);
</script>

<template>
    <Head title="Chart of Accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Create Account"
                description="BIR-compliant code format: digits with optional separators (- or .)."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createAccount">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Code <span class="text-destructive">*</span></label>
                        <input v-model="form.code" required placeholder="Code" class="rounded border px-3 py-2 text-sm" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Name <span class="text-destructive">*</span></label>
                        <input v-model="form.name" required placeholder="Name" class="rounded border px-3 py-2 text-sm" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Type</label>
                        <select v-model="form.type" class="rounded border px-3 py-2 text-sm">
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="revenue">Revenue</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Normal Balance</label>
                        <select v-model="form.normal_balance" class="rounded border px-3 py-2 text-sm">
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Parent Account</label>
                        <select v-model="form.parent_id" class="rounded border px-3 py-2 text-sm">
                            <option :value="null">No Parent</option>
                            <option v-for="account in state.accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button v-if="can('accounts.create')" type="submit" class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                            Create
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Accounts List">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                    <div class="flex items-end gap-2">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium">From</label>
                            <input
                                v-model="state.exportFromDate"
                                type="date"
                                class="rounded border px-2 py-2 text-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium">To</label>
                            <input
                                v-model="state.exportToDate"
                                type="date"
                                class="rounded border px-2 py-2 text-sm"
                            />
                        </div>
                        <button
                            v-if="can('accounts.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportAccounts"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Code</th>
                                <th class="px-2 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-left">Type</th>
                                <th class="px-2 py-2 text-left">Normal</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="account in state.accounts" :key="account.id" class="border-b">
                                <td class="px-2 py-2">{{ account.code }}</td>
                                <td class="px-2 py-2">{{ account.name }}</td>
                                <td class="px-2 py-2 uppercase">{{ account.type }}</td>
                                <td class="px-2 py-2 uppercase">{{ account.normal_balance }}</td>
                                <td class="px-2 py-2">
                                    <button v-if="can('accounts.delete')" class="rounded border px-2 py-1 text-xs" @click="deleteAccount(account.id)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage <= 1"
                        @click="loadAccounts(state.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-muted-foreground">
                        Page {{ state.currentPage }} of {{ state.lastPage }}
                    </span>
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage >= state.lastPage"
                        @click="loadAccounts(state.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
        </div>
    </AppLayout>
</template>
