<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { formatPhDateTime } from '@/lib/utils';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Backup & Restore', href: '/cas/backups' },
];

type BackupRow = {
    id: number;
    file_path: string;
    status: string;
    backup_at: string;
    restore_at?: string | null;
};

const state = reactive({
    backups: [] as BackupRow[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
    loading: false,
    error: '',
    success: '',
});

useStateNotifications(state);

async function loadBackups(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<PaginatedResponse<BackupRow>>(
            `/api/backups?per_page=${state.perPage}&page=${page}`,
        );
        state.backups = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load backups.';
    } finally {
        state.loading = false;
    }
}

function exportBackups() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/backups?${query.toString()}`, '_blank');
}

async function createBackup() {
    if (!can('backups.create')) {
        return;
    }

    state.error = '';

    try {
        await api.post('/api/backups');
        state.success = 'Backup created successfully.';
        await loadBackups();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to create backup.';
    }
}

async function restoreBackup(backupId: number) {
    if (!can('backups.restore')) {
        return;
    }

    state.error = '';

    try {
        await api.post(`/api/backups/${backupId}/restore`);
        state.success = 'Backup restored successfully.';
        await loadBackups();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to restore backup.';
    }
}

onMounted(loadBackups);
</script>

<template>
    <Head title="Backup & Restore" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Database Backup"
                description="Create and restore snapshots for CAS compliance continuity."
            >
                <button v-if="can('backups.create')" class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground" @click="createBackup">
                    Create Backup
                </button>
            </SectionCard>

            <SectionCard title="Backup History">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="state.exportFromDate"
                            type="date"
                            class="rounded border px-2 py-2 text-sm"
                        />
                        <input
                            v-model="state.exportToDate"
                            type="date"
                            class="rounded border px-2 py-2 text-sm"
                        />
                        <button
                            v-if="can('backups.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportBackups"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">ID</th>
                                <th class="px-2 py-2 text-left">File</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Backup At</th>
                                <th class="px-2 py-2 text-left">Restore At</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="backup in state.backups" :key="backup.id" class="border-b">
                                <td class="px-2 py-2">{{ backup.id }}</td>
                                <td class="px-2 py-2">{{ backup.file_path }}</td>
                                <td class="px-2 py-2 uppercase">{{ backup.status }}</td>
                                <td class="px-2 py-2">{{ formatPhDateTime(backup.backup_at) }}</td>
                                <td class="px-2 py-2">{{ formatPhDateTime(backup.restore_at) }}</td>
                                <td class="px-2 py-2">
                                    <button v-if="can('backups.restore')" class="rounded border px-2 py-1 text-xs" @click="restoreBackup(backup.id)">
                                        Restore
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
                        @click="loadBackups(state.currentPage - 1)"
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
                        @click="loadBackups(state.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading backups...</p>
        </div>
    </AppLayout>
</template>
