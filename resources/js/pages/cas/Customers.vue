<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BranchOption = {
    id: number;
    name: string;
    code: string;
};

type CustomerItem = {
    id: number;
    branch_id: number | null;
    code: string;
    name: string;
    tin: string | null;
    address: string | null;
    email: string | null;
    phone: string | null;
    branch?: { id: number; name: string; code: string } | null;
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Customers', href: '/cas/customers' },
];

const state = reactive({
    customers: [] as CustomerItem[],
    branches: [] as BranchOption[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    saving: false,
    deleting: false,
    editingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const form = reactive({
    code: '',
    name: '',
    branch_id: null as number | null,
    tin: '',
    address: '',
    email: '',
    phone: '',
});

const editForm = reactive({
    name: '',
    branch_id: null as number | null,
    tin: '',
    address: '',
    email: '',
    phone: '',
});

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const [customersResponse, catalogResponse] = await Promise.all([
            api.get<{
                data: CustomerItem[];
                current_page: number;
                last_page: number;
                per_page: number;
                total: number;
            }>(`/api/customers?per_page=${state.perPage}&page=${page}`),
            api.get<{ branches: BranchOption[] }>('/api/customers/catalog'),
        ]);

        state.customers = customersResponse.data;
        state.currentPage = customersResponse.current_page;
        state.lastPage = customersResponse.last_page;
        state.perPage = customersResponse.per_page;
        state.total = customersResponse.total;
        state.branches = catalogResponse.branches;
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load customers.';
    } finally {
        state.loading = false;
    }
}

function exportCustomers() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/customers?${query.toString()}`, '_blank');
}

async function createCustomer() {
    if (!can('customers.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/customers', {
            code: form.code,
            name: form.name,
            branch_id: form.branch_id,
            tin: form.tin || null,
            address: form.address || null,
            email: form.email || null,
            phone: form.phone || null,
        });

        state.success = 'Customer created successfully.';
        form.code = '';
        form.name = '';
        form.branch_id = null;
        form.tin = '';
        form.address = '';
        form.email = '';
        form.phone = '';

        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to create customer.';
    } finally {
        state.saving = false;
    }
}

function startEdit(customer: CustomerItem) {
    state.editingId = customer.id;
    editForm.name = customer.name;
    editForm.branch_id = customer.branch_id;
    editForm.tin = customer.tin ?? '';
    editForm.address = customer.address ?? '';
    editForm.email = customer.email ?? '';
    editForm.phone = customer.phone ?? '';
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveEdit(customerId: number) {
    if (!can('customers.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/customers/${customerId}`, {
            name: editForm.name,
            branch_id: editForm.branch_id,
            tin: editForm.tin || null,
            address: editForm.address || null,
            email: editForm.email || null,
            phone: editForm.phone || null,
        });

        state.success = 'Customer updated successfully.';
        state.editingId = 0;
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to update customer.';
    } finally {
        state.saving = false;
    }
}

async function deleteCustomer(customerId: number) {
    if (!can('customers.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/customers/${customerId}`);
        state.success = 'Customer deleted successfully.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to delete customer.';
    } finally {
        state.deleting = false;
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Add Customer"
                description="Create and maintain customer master records."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createCustomer">
                    <input
                        v-model="form.code"
                        required
                        placeholder="Code"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.name"
                        required
                        placeholder="Customer name"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <select
                        v-model.number="form.branch_id"
                        class="rounded border px-3 py-2 text-sm"
                    >
                        <option :value="null">No branch</option>
                        <option
                            v-for="branch in state.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.code }} - {{ branch.name }}
                        </option>
                    </select>

                    <input
                        v-model="form.tin"
                        placeholder="TIN"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="Email"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.phone"
                        placeholder="Phone"
                        class="rounded border px-3 py-2 text-sm"
                    />

                    <input
                        v-model="form.address"
                        placeholder="Address"
                        class="rounded border px-3 py-2 text-sm md:col-span-2"
                    />
                    <button
                        v-if="can('customers.create')"
                        type="submit"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving"
                    >
                        {{ state.saving ? 'Saving...' : 'Create Customer' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Customer List">
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
                            v-if="can('customers.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportCustomers"
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
                                <th class="px-2 py-2 text-left">TIN</th>
                                <th class="px-2 py-2 text-left">Email</th>
                                <th class="px-2 py-2 text-left">Phone</th>
                                <th class="px-2 py-2 text-left">Branch</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="customer in state.customers"
                                :key="customer.id"
                                class="border-b"
                            >
                                <td class="px-2 py-2 align-top">{{ customer.code }}</td>

                                <template v-if="state.editingId === customer.id">
                                    <td class="px-2 py-2 align-top">
                                        <input
                                            v-model="editForm.name"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input
                                            v-model="editForm.tin"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input
                                            v-model="editForm.email"
                                            type="email"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input
                                            v-model="editForm.phone"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <select
                                            v-model.number="editForm.branch_id"
                                            class="w-full rounded border px-2 py-1"
                                        >
                                            <option :value="null">No branch</option>
                                            <option
                                                v-for="branch in state.branches"
                                                :key="`edit-branch-${branch.id}`"
                                                :value="branch.id"
                                            >
                                                {{ branch.code }} - {{ branch.name }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="can('customers.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.saving"
                                                @click="saveEdit(customer.id)"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="cancelEdit"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="px-2 py-2 align-top">{{ customer.name }}</td>
                                    <td class="px-2 py-2 align-top">{{ customer.tin ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">{{ customer.email ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">{{ customer.phone ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">
                                        {{ customer.branch?.name ?? '-' }}
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="can('customers.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="startEdit(customer)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="can('customers.delete')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.deleting"
                                                @click="deleteCustomer(customer.id)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage <= 1"
                        @click="loadData(state.currentPage - 1)"
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
                        @click="loadData(state.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading customers...
            </p>
        </div>
    </AppLayout>
</template>
