<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Audit Trail', href: '/cas/audit-trail' },
];

type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

const state = reactive({
    fromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    toDate: new Date().toISOString().slice(0, 10),
    event: '',
    activity: '',
    perPage: 25,
    auditLogs: [] as Array<any>,
    userLogs: [] as Array<any>,
    auditPage: 1,
    auditLastPage: 1,
    auditTotal: 0,
    userPage: 1,
    userLastPage: 1,
    userTotal: 0,
    loading: false,
    error: '',
});

async function loadLogs() {
    state.loading = true;
    state.error = '';

    try {
        const auditQuery = new URLSearchParams({
            from_date: state.fromDate,
            to_date: state.toDate,
            per_page: String(state.perPage),
            page: String(state.auditPage),
        });
        const userQuery = new URLSearchParams({
            from_date: state.fromDate,
            to_date: state.toDate,
            per_page: String(state.perPage),
            page: String(state.userPage),
        });

        if (state.event.length > 0) {
            auditQuery.set('event', state.event);
        }

        if (state.activity.length > 0) {
            userQuery.set('activity', state.activity);
        }

        const [auditResponse, userResponse] = await Promise.all([
            api.get<PaginatedResponse<any>>(
                `/api/audit-logs?${auditQuery.toString()}`,
            ),
            api.get<PaginatedResponse<any>>(
                `/api/user-activity-logs?${userQuery.toString()}`,
            ),
        ]);

        state.auditLogs = auditResponse.data;
        state.auditPage = auditResponse.current_page;
        state.auditLastPage = auditResponse.last_page;
        state.auditTotal = auditResponse.total;

        state.userLogs = userResponse.data;
        state.userPage = userResponse.current_page;
        state.userLastPage = userResponse.last_page;
        state.userTotal = userResponse.total;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load audit trail.';
    } finally {
        state.loading = false;
    }
}

function applyFilters() {
    state.auditPage = 1;
    state.userPage = 1;
    void loadLogs();
}

function goAuditPage(page: number) {
    if (page < 1 || page > state.auditLastPage || page === state.auditPage) {
        return;
    }

    state.auditPage = page;
    void loadLogs();
}

function goUserPage(page: number) {
    if (page < 1 || page > state.userLastPage || page === state.userPage) {
        return;
    }

    state.userPage = page;
    void loadLogs();
}

onMounted(loadLogs);
</script>

<template>
    <Head title="Audit Trail" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Log Filters"
                description="Filter audit trail and user activity logs by date and event type."
            >
                <form class="grid gap-3 md:grid-cols-6" @submit.prevent="applyFilters">
                    <input
                        v-model="state.fromDate"
                        type="date"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="state.toDate"
                        type="date"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="state.event"
                        placeholder="Audit event (create/update/delete/posted)"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="state.activity"
                        placeholder="User activity"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <select
                        v-model.number="state.perPage"
                        class="rounded border px-3 py-2 text-sm"
                    >
                        <option :value="10">10 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                    <button
                        type="submit"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                    >
                        Load
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Audit Logs (Data Changes)">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Date/Time</th>
                                <th class="px-2 py-2 text-left">User</th>
                                <th class="px-2 py-2 text-left">Event</th>
                                <th class="px-2 py-2 text-left">Type</th>
                                <th class="px-2 py-2 text-left">ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in state.auditLogs"
                                :key="`audit-${log.id}`"
                                class="border-b"
                            >
                                <td class="px-2 py-2">{{ log.occurred_at }}</td>
                                <td class="px-2 py-2">
                                    {{ log.user?.name ?? log.user_id ?? '-' }}
                                </td>
                                <td class="px-2 py-2 uppercase">{{ log.event }}</td>
                                <td class="px-2 py-2">{{ log.auditable_type }}</td>
                                <td class="px-2 py-2">{{ log.auditable_id }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                    <p class="text-muted-foreground">
                        Total: {{ state.auditTotal }} | Page {{ state.auditPage }} of
                        {{ state.auditLastPage }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1"
                            :disabled="state.auditPage <= 1"
                            @click="goAuditPage(state.auditPage - 1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1"
                            :disabled="state.auditPage >= state.auditLastPage"
                            @click="goAuditPage(state.auditPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="User Activity Logs">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Date/Time</th>
                                <th class="px-2 py-2 text-left">User</th>
                                <th class="px-2 py-2 text-left">Activity</th>
                                <th class="px-2 py-2 text-left">Route</th>
                                <th class="px-2 py-2 text-left">Method</th>
                                <th class="px-2 py-2 text-left">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in state.userLogs"
                                :key="`user-${log.id}`"
                                class="border-b"
                            >
                                <td class="px-2 py-2">{{ log.occurred_at }}</td>
                                <td class="px-2 py-2">
                                    {{ log.user?.name ?? log.user_id ?? '-' }}
                                </td>
                                <td class="px-2 py-2">{{ log.activity }}</td>
                                <td class="px-2 py-2">{{ log.route }}</td>
                                <td class="px-2 py-2">{{ log.method }}</td>
                                <td class="px-2 py-2">{{ log.ip_address }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                    <p class="text-muted-foreground">
                        Total: {{ state.userTotal }} | Page {{ state.userPage }} of
                        {{ state.userLastPage }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1"
                            :disabled="state.userPage <= 1"
                            @click="goUserPage(state.userPage - 1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1"
                            :disabled="state.userPage >= state.userLastPage"
                            @click="goUserPage(state.userPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading logs...
            </p>
            <p v-if="state.error" class="text-sm text-destructive">
                {{ state.error }}
            </p>
        </div>
    </AppLayout>
</template>
