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

type SupplierItem = {
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
    { title: 'Suppliers', href: '/cas/suppliers' },
];

const state = reactive({
    suppliers: [] as SupplierItem[],
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
        const [suppliersResponse, catalogResponse] = await Promise.all([
            api.get<{
                data: SupplierItem[];
                current_page: number;
                last_page: number;
                per_page: number;
                total: number;
            }>(`/api/suppliers?per_page=${state.perPage}&page=${page}`),
            api.get<{ branches: BranchOption[] }>('/api/suppliers/catalog'),
        ]);

        state.suppliers = suppliersResponse.data;
        state.currentPage = suppliersResponse.current_page;
        state.lastPage = suppliersResponse.last_page;
        state.perPage = suppliersResponse.per_page;
        state.total = suppliersResponse.total;
        state.branches = catalogResponse.branches;
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load suppliers.';
    } finally {
        state.loading = false;
    }
}

function exportSuppliers() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/suppliers?${query.toString()}`, '_blank');
}

async function createSupplier() {
    if (!can('suppliers.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/suppliers', {
            code: form.code,
            name: form.name,
            branch_id: form.branch_id,
            tin: form.tin || null,
            address: form.address || null,
            email: form.email || null,
            phone: form.phone || null,
        });

        state.success = 'Supplier created successfully.';
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
                : 'Failed to create supplier.';
    } finally {
        state.saving = false;
    }
}

function startEdit(supplier: SupplierItem) {
    state.editingId = supplier.id;
    editForm.name = supplier.name;
    editForm.branch_id = supplier.branch_id;
    editForm.tin = supplier.tin ?? '';
    editForm.address = supplier.address ?? '';
    editForm.email = supplier.email ?? '';
    editForm.phone = supplier.phone ?? '';
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveEdit(supplierId: number) {
    if (!can('suppliers.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/suppliers/${supplierId}`, {
            name: editForm.name,
            branch_id: editForm.branch_id,
            tin: editForm.tin || null,
            address: editForm.address || null,
            email: editForm.email || null,
            phone: editForm.phone || null,
        });

        state.success = 'Supplier updated successfully.';
        state.editingId = 0;
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to update supplier.';
    } finally {
        state.saving = false;
    }
}

async function deleteSupplier(supplierId: number) {
    if (!can('suppliers.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/suppliers/${supplierId}`);
        state.success = 'Supplier deleted successfully.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to delete supplier.';
    } finally {
        state.deleting = false;
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Suppliers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Add Supplier"
                description="Create and maintain supplier master records."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createSupplier">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Code <span class="text-destructive">*</span></label>
                        <input
                            v-model="form.code"
                            required
                            placeholder="Code"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Supplier Name <span class="text-destructive">*</span></label>
                        <input
                            v-model="form.name"
                            required
                            placeholder="Supplier name"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Branch</label>
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
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">TIN</label>
                        <input
                            v-model="form.tin"
                            placeholder="TIN"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="Email"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Phone</label>
                        <input
                            v-model="form.phone"
                            placeholder="Phone"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label class="text-sm font-medium">Address</label>
                        <input
                            v-model="form.address"
                            placeholder="Address"
                            class="rounded border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            v-if="can('suppliers.create')"
                            type="submit"
                            class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                            :disabled="state.saving"
                        >
                            {{ state.saving ? 'Saving...' : 'Create Supplier' }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Supplier List">
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
                            v-if="can('suppliers.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportSuppliers"
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
                                v-for="supplier in state.suppliers"
                                :key="supplier.id"
                                class="border-b"
                            >
                                <td class="px-2 py-2 align-top">{{ supplier.code }}</td>

                                <template v-if="state.editingId === supplier.id">
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
                                                v-if="can('suppliers.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.saving"
                                                @click="saveEdit(supplier.id)"
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
                                    <td class="px-2 py-2 align-top">{{ supplier.name }}</td>
                                    <td class="px-2 py-2 align-top">{{ supplier.tin ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">{{ supplier.email ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">{{ supplier.phone ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">
                                        {{ supplier.branch?.name ?? '-' }}
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="can('suppliers.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="startEdit(supplier)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="can('suppliers.delete')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.deleting"
                                                @click="deleteSupplier(supplier.id)"
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
                Loading suppliers...
            </p>
        </div>
    </AppLayout>
</template>
