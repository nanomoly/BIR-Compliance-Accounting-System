<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountRow, BreadcrumbItem, PaginatedResponse } from '@/types';

const api = useCasApi();

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
    loading: false,
    error: '',
    success: '',
});

async function loadAccounts() {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<PaginatedResponse<AccountRow>>(
            '/api/accounts?per_page=200',
        );
        state.accounts = response.data;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load accounts.';
    } finally {
        state.loading = false;
    }
}

async function createAccount() {
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
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/accounts/${accountId}`);
        state.success = 'Account deleted.';
        await loadAccounts();
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
                    <input v-model="form.code" required placeholder="Code" class="rounded border px-3 py-2 text-sm" />
                    <input v-model="form.name" required placeholder="Name" class="rounded border px-3 py-2 text-sm" />
                    <select v-model="form.type" class="rounded border px-3 py-2 text-sm">
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expense</option>
                    </select>
                    <select v-model="form.normal_balance" class="rounded border px-3 py-2 text-sm">
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>
                    <select v-model="form.parent_id" class="rounded border px-3 py-2 text-sm">
                        <option :value="null">No Parent</option>
                        <option v-for="account in state.accounts" :key="account.id" :value="account.id">
                            {{ account.code }} - {{ account.name }}
                        </option>
                    </select>
                    <button type="submit" class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                        Create
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Accounts List">
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
                                    <button class="rounded border px-2 py-1 text-xs" @click="deleteAccount(account.id)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
            <p v-if="state.error" class="text-sm text-destructive">{{ state.error }}</p>
            <p v-if="state.success" class="text-sm text-emerald-600">{{ state.success }}</p>
        </div>
    </AppLayout>
</template>
