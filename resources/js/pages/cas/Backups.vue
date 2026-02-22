<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';

const api = useCasApi();

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
    loading: false,
    error: '',
    success: '',
});

async function loadBackups() {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<PaginatedResponse<BackupRow>>(
            '/api/backups?per_page=50',
        );
        state.backups = response.data;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load backups.';
    } finally {
        state.loading = false;
    }
}

async function createBackup() {
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
                <button class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground" @click="createBackup">
                    Create Backup
                </button>
            </SectionCard>

            <SectionCard title="Backup History">
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
                                <td class="px-2 py-2">{{ backup.backup_at }}</td>
                                <td class="px-2 py-2">{{ backup.restore_at ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    <button class="rounded border px-2 py-1 text-xs" @click="restoreBackup(backup.id)">
                                        Restore
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading backups...</p>
            <p v-if="state.error" class="text-sm text-destructive">{{ state.error }}</p>
            <p v-if="state.success" class="text-sm text-emerald-600">{{ state.success }}</p>
        </div>
    </AppLayout>
</template>
