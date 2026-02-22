<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    AccountRow,
    BreadcrumbItem,
    JournalPayload,
    PaginatedResponse,
} from '@/types';

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Journals', href: '/cas/journals' },
];

const state = reactive({
    accounts: [] as AccountRow[],
    entries: [] as Array<any>,
    loading: false,
    error: '',
    success: '',
});

const form = reactive<JournalPayload>({
    branch_id: 1,
    journal_type: 'general',
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    reference_no: '',
    lines: [
        { account_id: 0, debit: 0, credit: 0, particulars: '' },
        { account_id: 0, debit: 0, credit: 0, particulars: '' },
    ],
});

function addLine() {
    form.lines.push({ account_id: 0, debit: 0, credit: 0, particulars: '' });
}

function removeLine(index: number) {
    if (form.lines.length > 2) {
        form.lines.splice(index, 1);
    }
}

async function loadData() {
    state.loading = true;

    try {
        const [accounts, entries] = await Promise.all([
            api.get<PaginatedResponse<AccountRow>>('/api/accounts?per_page=200'),
            api.get<PaginatedResponse<any>>('/api/journal-entries?per_page=100'),
        ]);

        state.accounts = accounts.data;
        state.entries = entries.data;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load journals.';
    } finally {
        state.loading = false;
    }
}

async function createEntry() {
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/journal-entries', {
            ...form,
            reference_no: form.reference_no || null,
            lines: form.lines,
        });
        state.success = 'Journal entry created.';
        form.description = '';
        form.reference_no = '';
        form.lines = [
            { account_id: 0, debit: 0, credit: 0, particulars: '' },
            { account_id: 0, debit: 0, credit: 0, particulars: '' },
        ];
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to create journal entry.';
    }
}

async function postEntry(entryId: number) {
    state.error = '';

    try {
        await api.post(`/api/journal-entries/${entryId}/post`);
        state.success = 'Entry posted.';
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to post entry.';
    }
}

async function reverseEntry(entryId: number) {
    state.error = '';

    try {
        await api.post(`/api/journal-entries/${entryId}/reverse`);
        state.success = 'Reversal entry created.';
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to reverse entry.';
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Journals" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Create Journal Entry"
                description="Debit and credit totals must match before posting."
            >
                <form class="grid gap-3" @submit.prevent="createEntry">
                    <div class="grid gap-3 md:grid-cols-4">
                        <select v-model="form.journal_type" class="rounded border px-3 py-2 text-sm">
                            <option value="general">General Journal</option>
                            <option value="sales">Sales Journal</option>
                            <option value="purchase">Purchase Journal</option>
                            <option value="cash_receipts">Cash Receipts Journal</option>
                            <option value="cash_disbursements">Cash Disbursements Journal</option>
                        </select>
                        <input v-model="form.entry_date" type="date" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.reference_no" placeholder="Reference No" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.description" required placeholder="Description" class="rounded border px-3 py-2 text-sm" />
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">Account</th>
                                    <th class="px-2 py-2 text-left">Debit</th>
                                    <th class="px-2 py-2 text-left">Credit</th>
                                    <th class="px-2 py-2 text-left">Particulars</th>
                                    <th class="px-2 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-b">
                                    <td class="px-2 py-2">
                                        <select v-model.number="line.account_id" required class="w-full rounded border px-2 py-1">
                                            <option :value="0" disabled>Select account</option>
                                            <option v-for="account in state.accounts" :key="account.id" :value="account.id">
                                                {{ account.code }} - {{ account.name }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.debit" type="number" step="0.01" min="0" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.credit" type="number" step="0.01" min="0" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model="line.particulars" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <button type="button" class="rounded border px-2 py-1 text-xs" @click="removeLine(index)">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" class="rounded border px-3 py-2 text-sm" @click="addLine">Add Line</button>
                        <button type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground">Save Draft</button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Journal Entries">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Entry #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Journal</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Debit</th>
                                <th class="px-2 py-2 text-left">Credit</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in state.entries" :key="entry.id" class="border-b">
                                <td class="px-2 py-2">{{ entry.entry_number }}</td>
                                <td class="px-2 py-2">{{ entry.entry_date }}</td>
                                <td class="px-2 py-2">{{ entry.journal_type }}</td>
                                <td class="px-2 py-2 uppercase">{{ entry.status }}</td>
                                <td class="px-2 py-2">{{ entry.total_debit }}</td>
                                <td class="px-2 py-2">{{ entry.total_credit }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <button
                                            v-if="entry.status === 'draft'"
                                            class="rounded border px-2 py-1 text-xs"
                                            @click="postEntry(entry.id)"
                                        >
                                            Post
                                        </button>
                                        <button
                                            v-if="entry.status === 'posted'"
                                            class="rounded border px-2 py-1 text-xs"
                                            @click="reverseEntry(entry.id)"
                                        >
                                            Reverse
                                        </button>
                                    </div>
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
