<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'System Information', href: '/cas/system-info' },
];

const state = reactive({
    software_version: '',
    database_version: '',
    developer_information: {
        name: '',
        tin: '',
    },
    company: {
        name: '',
        tin: '',
        address: '',
    },
    loading: false,
    saving: false,
    error: '',
    success: '',
});

useStateNotifications(state);

async function loadSystemInfo() {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            software_version: string;
            database_version: string;
            developer_information: { name: string; tin: string };
            company: { name: string; tin: string; address: string };
        }>('/api/system-info');

        state.software_version = response.software_version;
        state.database_version = response.database_version;
        state.developer_information = response.developer_information;
        state.company = response.company;
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load system information.';
    } finally {
        state.loading = false;
    }
}

async function saveCompanyProfile() {
    if (!can('system_info.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        const response = await api.put<{
            message: string;
            company: { name: string; tin: string; address: string };
        }>('/api/system-info/company-profile', {
            name: state.company.name,
            tin: state.company.tin,
            registered_address: state.company.address,
        });

        state.company = response.company;
        state.success = response.message;
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to update company profile.';
    } finally {
        state.saving = false;
    }
}

onMounted(loadSystemInfo);
</script>

<template>
    <Head title="System Information" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard title="Software & Database">
                <div class="grid gap-2 text-sm">
                    <p><strong>Software Version:</strong> {{ state.software_version || '-' }}</p>
                    <p><strong>Database Version:</strong> {{ state.database_version || '-' }}</p>
                </div>
            </SectionCard>

            <SectionCard title="Developer Information">
                <div class="grid gap-2 text-sm">
                    <p><strong>Name:</strong> {{ state.developer_information.name || '-' }}</p>
                    <p><strong>TIN:</strong> {{ state.developer_information.tin || '-' }}</p>
                </div>
            </SectionCard>

            <SectionCard title="Company Header Information">
                <form class="grid gap-3" @submit.prevent="saveCompanyProfile">
                    <input
                        v-model="state.company.name"
                        required
                        placeholder="Company name"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="state.company.tin"
                        required
                        placeholder="TIN"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <textarea
                        v-model="state.company.address"
                        required
                        placeholder="Registered address"
                        class="min-h-24 rounded border px-3 py-2 text-sm"
                    />

                    <button
                        v-if="can('system_info.update')"
                        type="submit"
                        class="w-fit rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving"
                    >
                        {{ state.saving ? 'Saving...' : 'Save Company Profile' }}
                    </button>
                </form>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading system information...</p>
        </div>
    </AppLayout>
</template>
