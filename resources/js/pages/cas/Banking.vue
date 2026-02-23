<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, toRef } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useQueryTabSync } from '@/composables/useQueryTabSync';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BankAccount = {
    id: number;
    bank_name: string;
    account_name: string;
    account_number: string;
    account_type: string;
    current_balance: number;
    is_active: boolean;
};

type BankTransaction = {
    id: number;
    bank_account_id: number;
    transaction_date: string;
    transaction_type: 'debit' | 'credit';
    amount: number;
    reference_no: string | null;
    description: string | null;
    bank_account?: { bank_name: string; account_number: string };
};

type BankStatementLine = {
    id: number;
    transaction_date: string;
    description: string | null;
    reference_no: string | null;
    transaction_type: 'debit' | 'credit';
    amount: number;
    balance: number | null;
    is_matched: boolean;
    unmatched_reason?: string | null;
};

type BankReconciliationMatch = {
    id: number;
    bank_statement_line_id: number;
    bank_transaction_id: number;
    matched_amount: number;
    bank_transaction?: {
        id: number;
        transaction_date: string;
        transaction_type: 'debit' | 'credit';
        amount: number;
        reference_no: string | null;
    };
    statement_line?: {
        id: number;
        transaction_date: string;
        transaction_type: 'debit' | 'credit';
        amount: number;
        reference_no: string | null;
    };
};

type SuggestedBankTransaction = {
    id: number;
    transaction_date: string;
    transaction_type: 'debit' | 'credit';
    amount: number;
    reference_no: string | null;
    description: string | null;
    score: number;
};

type BankReconciliation = {
    id: number;
    bank_account_id: number;
    bank_statement_id: number;
    status: 'open' | 'closed';
    statement_opening_balance: number;
    statement_closing_balance: number;
    cleared_balance: number;
    difference: number;
    statement?: {
        id: number;
        statement_date: string;
        opening_balance: number;
        closing_balance: number;
        lines?: BankStatementLine[];
    };
    matches?: BankReconciliationMatch[];
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Banking', href: '/cas/banking' },
];

const state = reactive({
    activeTab: 'accounts',
    accounts: [] as BankAccount[],
    transactions: [] as BankTransaction[],
    reconciliations: [] as BankReconciliation[],
    selectedReconciliationId: 0,
    selectedReconciliationLines: [] as BankStatementLine[],
    availableTransactions: [] as BankTransaction[],
    selectedLineId: 0,
    selectedTransactionId: 0,
    unmatchedReason: '',
    selectedReconciliationMatches: [] as BankReconciliationMatch[],
    suggestedMatchesByLine: {} as Record<number, SuggestedBankTransaction[]>,
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    accountPage: 1,
    accountLastPage: 1,
    accountTotal: 0,
    transactionPage: 1,
    transactionLastPage: 1,
    transactionTotal: 0,
    reconciliationPage: 1,
    reconciliationLastPage: 1,
    reconciliationTotal: 0,
    perPage: 15,
    loading: false,
    saving: false,
    deleting: false,
    editingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const { syncFromQuery } = useQueryTabSync(toRef(state, 'activeTab'), ['accounts', 'transactions', 'statement-import', 'reconciliation']);

const accountForm = reactive({
    bank_name: '',
    account_name: '',
    account_number: '',
    account_type: 'checking',
    current_balance: 0,
    is_active: true,
});

const accountEditForm = reactive({
    bank_name: '',
    account_name: '',
    account_number: '',
    account_type: 'checking',
    current_balance: 0,
    is_active: true,
});

const transactionForm = reactive({
    bank_account_id: 0,
    transaction_date: new Date().toISOString().slice(0, 10),
    transaction_type: 'credit' as 'debit' | 'credit',
    amount: 0,
    reference_no: '',
    description: '',
});

const statementImportForm = reactive({
    bank_account_id: 0,
    statement_date: new Date().toISOString().slice(0, 10),
    opening_balance: 0,
    closing_balance: 0,
    statement_csv: '',
});

const selectedSuggestions = computed(() => {
    if (!state.selectedLineId) {
        return [] as SuggestedBankTransaction[];
    }

    return state.suggestedMatchesByLine[state.selectedLineId] ?? [];
});

async function loadAccounts(page = 1) {
    const response = await api.get<{
        data: BankAccount[];
        current_page: number;
        last_page: number;
        total: number;
    }>(`/api/bank-accounts?per_page=${state.perPage}&page=${page}`);

    state.accounts = response.data;
    state.accountPage = response.current_page;
    state.accountLastPage = response.last_page;
    state.accountTotal = response.total;

    if (state.accounts.length > 0 && transactionForm.bank_account_id === 0) {
        transactionForm.bank_account_id = state.accounts[0].id;
    }
}

async function loadTransactions(page = 1) {
    const query = new URLSearchParams({
        per_page: String(state.perPage),
        page: String(page),
    });

    if (transactionForm.bank_account_id > 0) {
        query.set('bank_account_id', String(transactionForm.bank_account_id));
    }

    const response = await api.get<{
        data: BankTransaction[];
        current_page: number;
        last_page: number;
        total: number;
    }>(`/api/bank-transactions?${query.toString()}`);

    state.transactions = response.data;
    state.transactionPage = response.current_page;
    state.transactionLastPage = response.last_page;
    state.transactionTotal = response.total;
}

async function loadReconciliations(page = 1) {
    const query = new URLSearchParams({
        per_page: String(state.perPage),
        page: String(page),
    });

    if (transactionForm.bank_account_id > 0) {
        query.set('bank_account_id', String(transactionForm.bank_account_id));
    }

    const response = await api.get<{
        data: BankReconciliation[];
        current_page: number;
        last_page: number;
        total: number;
    }>(`/api/banking/reconciliations?${query.toString()}`);

    state.reconciliations = response.data;
    state.reconciliationPage = response.current_page;
    state.reconciliationLastPage = response.last_page;
    state.reconciliationTotal = response.total;

    if (state.reconciliations.length > 0 && state.selectedReconciliationId === 0) {
        await loadReconciliationDetails(state.reconciliations[0].id);
    }
}

async function loadReconciliationDetails(reconciliationId: number) {
    state.selectedReconciliationId = reconciliationId;

    const response = await api.get<{
        reconciliation: BankReconciliation;
        available_transactions: BankTransaction[];
        suggested_matches?: Record<number, SuggestedBankTransaction[]>;
    }>(`/api/banking/reconciliations/${reconciliationId}`);

    state.selectedReconciliationLines = response.reconciliation.statement?.lines ?? [];
    state.selectedReconciliationMatches = response.reconciliation.matches ?? [];
    state.availableTransactions = response.available_transactions;
    state.suggestedMatchesByLine = response.suggested_matches ?? {};
    state.selectedLineId = 0;
    state.selectedTransactionId = 0;
    state.unmatchedReason = '';
}

function useTopSuggestion() {
    if (selectedSuggestions.value.length === 0) {
        return;
    }

    state.selectedTransactionId = selectedSuggestions.value[0].id;
}

async function loadData() {
    state.loading = true;
    state.error = '';

    try {
        await loadAccounts(state.accountPage);
        await loadTransactions(state.transactionPage);
        await loadReconciliations(state.reconciliationPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load banking data.';
    } finally {
        state.loading = false;
    }
}

function exportBanking() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/banking?${query.toString()}`, '_blank');
}

async function createAccount() {
    if (!can('banking.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/bank-accounts', accountForm);
        state.success = 'Bank account created.';
        accountForm.bank_name = '';
        accountForm.account_name = '';
        accountForm.account_number = '';
        accountForm.account_type = 'checking';
        accountForm.current_balance = 0;
        accountForm.is_active = true;
        await loadData();
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create bank account.';
    } finally {
        state.saving = false;
    }
}

function startEdit(account: BankAccount) {
    state.editingId = account.id;
    accountEditForm.bank_name = account.bank_name;
    accountEditForm.account_name = account.account_name;
    accountEditForm.account_number = account.account_number;
    accountEditForm.account_type = account.account_type;
    accountEditForm.current_balance = account.current_balance;
    accountEditForm.is_active = account.is_active;
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveAccount(accountId: number) {
    if (!can('banking.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/bank-accounts/${accountId}`, accountEditForm);
        state.success = 'Bank account updated.';
        state.editingId = 0;
        await loadData();
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to update bank account.';
    } finally {
        state.saving = false;
    }
}

async function deleteAccount(accountId: number) {
    if (!can('banking.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/bank-accounts/${accountId}`);
        state.success = 'Bank account deleted.';
        await loadData();
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete bank account.';
    } finally {
        state.deleting = false;
    }
}

async function createTransaction() {
    if (!can('banking.create')) {
        return;
    }

    if (transactionForm.bank_account_id === 0) {
        state.error = 'Select a bank account before adding a transaction.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/bank-transactions', {
            ...transactionForm,
            reference_no: transactionForm.reference_no || null,
            description: transactionForm.description || null,
        });
        state.success = 'Bank transaction saved.';
        transactionForm.amount = 0;
        transactionForm.reference_no = '';
        transactionForm.description = '';
        await loadData();
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to save bank transaction.';
    } finally {
        state.saving = false;
    }
}

async function importStatement() {
    if (!can('banking.update')) {
        return;
    }

    if (!statementImportForm.bank_account_id) {
        state.error = 'Select a bank account for statement import.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/banking/statements/import', statementImportForm);
        state.success = 'Bank statement imported and reconciliation created.';
        statementImportForm.statement_csv = '';
        await loadReconciliations(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to import statement.';
    } finally {
        state.saving = false;
    }
}

async function matchSelectedLine() {
    if (!can('banking.update')) {
        return;
    }

    if (!state.selectedReconciliationId || !state.selectedLineId || !state.selectedTransactionId) {
        state.error = 'Select a reconciliation, statement line, and transaction before matching.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/banking/reconciliations/${state.selectedReconciliationId}/match`, {
            bank_statement_line_id: state.selectedLineId,
            bank_transaction_id: state.selectedTransactionId,
        });

        state.success = 'Line matched successfully.';
        await Promise.all([
            loadReconciliations(state.reconciliationPage),
            loadReconciliationDetails(state.selectedReconciliationId),
        ]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to match statement line.';
    } finally {
        state.saving = false;
    }
}

async function tagUnmatchedReason() {
    if (!can('banking.update')) {
        return;
    }

    if (!state.selectedReconciliationId || !state.selectedLineId || !state.unmatchedReason.trim()) {
        state.error = 'Select a reconciliation line and provide reason.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/banking/reconciliations/${state.selectedReconciliationId}/tag-unmatched`, {
            bank_statement_line_id: state.selectedLineId,
            unmatched_reason: state.unmatchedReason.trim(),
        });

        state.success = 'Unmatched reason saved.';
        await loadReconciliationDetails(state.selectedReconciliationId);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to save unmatched reason.';
    } finally {
        state.saving = false;
    }
}

async function unmatchLine(matchId: number) {
    if (!can('banking.update')) {
        return;
    }

    if (!state.selectedReconciliationId) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/banking/reconciliations/${state.selectedReconciliationId}/matches/${matchId}`);
        state.success = 'Match removed.';
        await Promise.all([
            loadReconciliations(state.reconciliationPage),
            loadReconciliationDetails(state.selectedReconciliationId),
        ]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to unmatch line.';
    } finally {
        state.saving = false;
    }
}

async function closeReconciliation(reconciliationId: number) {
    if (!can('banking.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/banking/reconciliations/${reconciliationId}/close`, {});
        state.success = 'Reconciliation closed.';
        await Promise.all([
            loadReconciliations(state.reconciliationPage),
            loadReconciliationDetails(reconciliationId),
        ]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to close reconciliation.';
    } finally {
        state.saving = false;
    }
}

async function reopenReconciliation(reconciliationId: number) {
    if (!can('banking.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/banking/reconciliations/${reconciliationId}/reopen`, {});
        state.success = 'Reconciliation reopened.';
        await Promise.all([
            loadReconciliations(state.reconciliationPage),
            loadReconciliationDetails(reconciliationId),
        ]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to reopen reconciliation.';
    } finally {
        state.saving = false;
    }
}

async function deleteTransaction(transactionId: number) {
    if (!can('banking.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/bank-transactions/${transactionId}`);
        state.success = 'Bank transaction deleted.';
        await loadData();
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete bank transaction.';
    } finally {
        state.deleting = false;
    }
}

onMounted(async () => {
    syncFromQuery();

    await loadData();
});
</script>

<template>
    <Head title="Banking" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'accounts' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'accounts'">Accounts</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'transactions' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'transactions'">Transactions</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'statement-import' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'statement-import'">Statement Import</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'reconciliation' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'reconciliation'">Reconciliation</button>
            </div>

            <SectionCard v-if="state.activeTab === 'accounts'" title="Add Bank Account">
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createAccount">
                    <label class="grid gap-1 text-sm"><span>Bank Name</span><input v-model="accountForm.bank_name" required class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Account Name</span><input v-model="accountForm.account_name" required class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Account Number</span><input v-model="accountForm.account_number" required class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Account Type</span><input v-model="accountForm.account_type" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Current Balance</span><input v-model.number="accountForm.current_balance" type="number" step="0.01" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="flex items-center gap-2 rounded border px-3 py-2 text-sm">
                        <input v-model="accountForm.is_active" type="checkbox" />
                        Active
                    </label>
                    <button v-if="can('banking.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">{{ state.saving ? 'Saving...' : 'Create Account' }}</button>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'accounts'" title="Bank Accounts">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Bank</th>
                                <th class="px-2 py-2 text-left">Account</th>
                                <th class="px-2 py-2 text-left">Number</th>
                                <th class="px-2 py-2 text-left">Type</th>
                                <th class="px-2 py-2 text-left">Balance</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="account in state.accounts" :key="account.id" class="border-b">
                                <template v-if="state.editingId === account.id">
                                    <td class="px-2 py-2"><input v-model="accountEditForm.bank_name" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="accountEditForm.account_name" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="accountEditForm.account_number" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="accountEditForm.account_type" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model.number="accountEditForm.current_balance" type="number" step="0.01" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="accountEditForm.is_active" type="checkbox" /></td>
                                    <td class="px-2 py-2"><div class="flex gap-2"><button type="button" class="rounded border px-2 py-1" @click="saveAccount(account.id)">Save</button><button type="button" class="rounded border px-2 py-1" @click="cancelEdit">Cancel</button></div></td>
                                </template>
                                <template v-else>
                                    <td class="px-2 py-2">{{ account.bank_name }}</td>
                                    <td class="px-2 py-2">{{ account.account_name }}</td>
                                    <td class="px-2 py-2">{{ account.account_number }}</td>
                                    <td class="px-2 py-2 uppercase">{{ account.account_type }}</td>
                                    <td class="px-2 py-2">{{ formatAmount(account.current_balance) }}</td>
                                    <td class="px-2 py-2">{{ account.is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-2 py-2"><div class="flex gap-2"><button type="button" class="rounded border px-2 py-1" @click="startEdit(account)">Edit</button><button type="button" class="rounded border px-2 py-1" :disabled="state.deleting" @click="deleteAccount(account.id)">Delete</button></div></td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'transactions'" title="Add Bank Transaction">
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createTransaction">
                    <label class="grid gap-1 text-sm"><span>Bank Account</span><select v-model.number="transactionForm.bank_account_id" class="rounded border px-3 py-2 text-sm" @change="loadTransactions(1)">
                        <option :value="0" disabled>Select bank account</option>
                        <option v-for="account in state.accounts" :key="`txn-account-${account.id}`" :value="account.id">{{ account.bank_name }} - {{ account.account_number }}</option>
                    </select></label>
                    <label class="grid gap-1 text-sm"><span>Transaction Date</span><input v-model="transactionForm.transaction_date" type="date" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Transaction Type</span><select v-model="transactionForm.transaction_type" class="rounded border px-3 py-2 text-sm">
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select></label>
                    <label class="grid gap-1 text-sm"><span>Amount</span><input v-model.number="transactionForm.amount" type="number" step="0.01" min="0.01" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Reference</span><input v-model="transactionForm.reference_no" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Description</span><input v-model="transactionForm.description" class="rounded border px-3 py-2 text-sm" /></label>
                    <button v-if="can('banking.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">{{ state.saving ? 'Saving...' : 'Add Transaction' }}</button>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'transactions'" title="Bank Transactions">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.transactionTotal }}</p>
                    <div class="flex items-end gap-2">
                        <label class="grid gap-1 text-xs"><span>From</span><input v-model="state.exportFromDate" type="date" class="rounded border px-2 py-2 text-sm" /></label>
                        <label class="grid gap-1 text-xs"><span>To</span><input v-model="state.exportToDate" type="date" class="rounded border px-2 py-2 text-sm" /></label>
                        <button v-if="can('banking.view')" type="button" class="self-end rounded border px-3 py-2 text-sm" @click="exportBanking">Export Excel</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Bank</th>
                                <th class="px-2 py-2 text-left">Type</th>
                                <th class="px-2 py-2 text-left">Amount</th>
                                <th class="px-2 py-2 text-left">Reference</th>
                                <th class="px-2 py-2 text-left">Description</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="transaction in state.transactions" :key="transaction.id" class="border-b">
                                <td class="px-2 py-2">{{ formatPhDateOnly(transaction.transaction_date) }}</td>
                                <td class="px-2 py-2">{{ transaction.bank_account?.bank_name ?? '-' }}</td>
                                <td class="px-2 py-2 uppercase">{{ transaction.transaction_type }}</td>
                                <td class="px-2 py-2">{{ formatAmount(transaction.amount) }}</td>
                                <td class="px-2 py-2">{{ transaction.reference_no ?? '-' }}</td>
                                <td class="px-2 py-2">{{ transaction.description ?? '-' }}</td>
                                <td class="px-2 py-2"><button v-if="can('banking.delete')" type="button" class="rounded border px-2 py-1" :disabled="state.deleting" @click="deleteTransaction(transaction.id)">Delete</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'statement-import'" title="Import Bank Statement" description="Paste CSV rows: date,description,reference,type,amount,balance">
                <form class="grid gap-3" @submit.prevent="importStatement">
                    <div class="grid gap-3 md:grid-cols-4">
                        <label class="grid gap-1 text-sm"><span>Bank Account</span><select v-model.number="statementImportForm.bank_account_id" class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select bank account</option>
                            <option v-for="account in state.accounts" :key="`stmt-account-${account.id}`" :value="account.id">
                                {{ account.bank_name }} - {{ account.account_number }}
                            </option>
                        </select></label>
                        <label class="grid gap-1 text-sm"><span>Statement Date</span><input v-model="statementImportForm.statement_date" type="date" required class="rounded border px-3 py-2 text-sm" /></label>
                        <label class="grid gap-1 text-sm"><span>Opening Balance</span><input v-model.number="statementImportForm.opening_balance" type="number" step="0.01" required class="rounded border px-3 py-2 text-sm" /></label>
                        <label class="grid gap-1 text-sm"><span>Closing Balance</span><input v-model.number="statementImportForm.closing_balance" type="number" step="0.01" required class="rounded border px-3 py-2 text-sm" /></label>
                    </div>
                    <label class="grid gap-1 text-sm"><span>Statement CSV</span>
                    <textarea
                        v-model="statementImportForm.statement_csv"
                        rows="6"
                        class="rounded border px-3 py-2 text-sm"
                        placeholder="date,description,reference,type,amount,balance
2026-02-01,Deposit,DEP-1001,credit,10000,10000"
                    />
                    </label>
                    <button v-if="can('banking.update')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                        {{ state.saving ? 'Importing...' : 'Import Statement' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'reconciliation'" title="Bank Reconciliation Workbench">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Reconciliations: {{ state.reconciliationTotal }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.reconciliationPage <= 1" @click="loadReconciliations(state.reconciliationPage - 1)">Previous</button>
                        <span class="text-sm text-muted-foreground">Page {{ state.reconciliationPage }} of {{ state.reconciliationLastPage }}</span>
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.reconciliationPage >= state.reconciliationLastPage" @click="loadReconciliations(state.reconciliationPage + 1)">Next</button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded border p-3">
                        <p class="mb-2 text-sm font-medium">Reconciliation List</p>
                        <div class="space-y-2">
                            <button
                                v-for="reco in state.reconciliations"
                                :key="reco.id"
                                type="button"
                                class="w-full rounded border px-3 py-2 text-left text-sm"
                                @click="loadReconciliationDetails(reco.id)"
                            >
                                <div class="flex items-center justify-between">
                                    <span>#{{ reco.id }} • {{ formatPhDateOnly(reco.statement?.statement_date) }}</span>
                                    <span class="uppercase">{{ reco.status }}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Diff: {{ formatAmount(reco.difference) }}
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="rounded border p-3">
                        <p class="mb-2 text-sm font-medium">Match Statement Line</p>
                        <div class="grid gap-3">
                            <select v-model.number="state.selectedLineId" class="rounded border px-3 py-2 text-sm">
                                <option :value="0" disabled>Select unmatched statement line</option>
                                <option v-for="line in state.selectedReconciliationLines.filter((item) => !item.is_matched)" :key="line.id" :value="line.id">
                                    {{ formatPhDateOnly(line.transaction_date) }} • {{ line.transaction_type }} • {{ formatAmount(line.amount) }} • {{ line.reference_no ?? '-' }}
                                </option>
                            </select>

                            <select v-model.number="state.selectedTransactionId" class="rounded border px-3 py-2 text-sm">
                                <option :value="0" disabled>Select bank transaction</option>
                                <option v-for="txn in state.availableTransactions" :key="txn.id" :value="txn.id">
                                    {{ formatPhDateOnly(txn.transaction_date) }} • {{ txn.transaction_type }} • {{ formatAmount(txn.amount) }} • {{ txn.reference_no ?? '-' }}
                                </option>
                            </select>

                            <div class="rounded border p-2">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-medium">Suggested Matches</p>
                                    <button
                                        type="button"
                                        class="rounded border px-2 py-1 text-xs"
                                        :disabled="selectedSuggestions.length === 0"
                                        @click="useTopSuggestion"
                                    >
                                        Use Top Suggestion
                                    </button>
                                </div>
                                <div v-if="selectedSuggestions.length === 0" class="text-xs text-muted-foreground">
                                    No suggestions for selected line.
                                </div>
                                <div v-else class="space-y-1 text-xs">
                                    <button
                                        v-for="item in selectedSuggestions"
                                        :key="`suggestion-${item.id}`"
                                        type="button"
                                        class="w-full rounded border px-2 py-1 text-left"
                                        @click="state.selectedTransactionId = item.id"
                                    >
                                        Score {{ item.score }} • {{ formatPhDateOnly(item.transaction_date) }} • {{ item.transaction_type }} • {{ formatAmount(item.amount) }} • {{ item.reference_no ?? '-' }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button v-if="can('banking.update')" type="button" class="rounded border px-3 py-2 text-sm" :disabled="state.saving" @click="matchSelectedLine">
                                    Match Selected
                                </button>
                                <input
                                    v-model="state.unmatchedReason"
                                    placeholder="Unmatched reason"
                                    class="rounded border px-3 py-2 text-sm"
                                />
                                <button v-if="can('banking.update')" type="button" class="rounded border px-3 py-2 text-sm" :disabled="state.saving" @click="tagUnmatchedReason">
                                    Save Reason
                                </button>
                                <button
                                    v-if="can('banking.update') && state.selectedReconciliationId"
                                    type="button"
                                    class="rounded border px-3 py-2 text-sm"
                                    :disabled="state.saving"
                                    @click="closeReconciliation(state.selectedReconciliationId)"
                                >
                                    Close Reconciliation
                                </button>
                                <button
                                    v-if="can('banking.update') && state.selectedReconciliationId"
                                    type="button"
                                    class="rounded border px-3 py-2 text-sm"
                                    :disabled="state.saving"
                                    @click="reopenReconciliation(state.selectedReconciliationId)"
                                >
                                    Reopen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="rounded border p-3">
                        <p class="mb-2 text-sm font-medium">Statement Lines</p>
                        <div class="max-h-60 overflow-auto text-sm">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="px-2 py-1 text-left">Date</th>
                                        <th class="px-2 py-1 text-left">Type</th>
                                        <th class="px-2 py-1 text-left">Amount</th>
                                        <th class="px-2 py-1 text-left">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in state.selectedReconciliationLines" :key="`line-${line.id}`" class="border-b">
                                        <td class="px-2 py-1">{{ formatPhDateOnly(line.transaction_date) }}</td>
                                        <td class="px-2 py-1 uppercase">{{ line.transaction_type }}</td>
                                        <td class="px-2 py-1">{{ formatAmount(line.amount) }}</td>
                                        <td class="px-2 py-1">{{ line.unmatched_reason ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded border p-3">
                        <p class="mb-2 text-sm font-medium">Matched Pairs</p>
                        <div class="space-y-2">
                            <div v-for="item in state.selectedReconciliationMatches" :key="`match-${item.id}`" class="rounded border p-2 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span>
                                        {{ formatPhDateOnly(item.statement_line?.transaction_date) }} {{ item.statement_line?.transaction_type }} {{ formatAmount(item.matched_amount) }}
                                        ↔ {{ formatPhDateOnly(item.bank_transaction?.transaction_date) }} {{ item.bank_transaction?.transaction_type }} {{ formatAmount(item.bank_transaction?.amount) }}
                                    </span>
                                    <button v-if="can('banking.update')" type="button" class="rounded border px-2 py-1" :disabled="state.saving" @click="unmatchLine(item.id)">
                                        Unmatch
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading banking data...</p>
        </div>
    </AppLayout>
</template>
