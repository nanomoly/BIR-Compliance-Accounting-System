<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Suppliers', href: '/cas/suppliers' },
];

const state = reactive({
    suppliers: [] as SupplierItem[],
    branches: [] as BranchOption[],
    loading: false,
    saving: false,
    editingId: 0,
    error: '',
    success: '',
});

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

async function loadData() {
    state.loading = true;
    state.error = '';

    try {
        const [suppliersResponse, catalogResponse] = await Promise.all([
            api.get<{ data: SupplierItem[] }>('/api/suppliers?per_page=100'),
            api.get<{ branches: BranchOption[] }>('/api/suppliers/catalog'),
        ]);

        state.suppliers = suppliersResponse.data;
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

async function createSupplier() {
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

        await loadData();
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
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to update supplier.';
    } finally {
        state.saving = false;
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
                    <input
                        v-model="form.code"
                        required
                        placeholder="Code"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.name"
                        required
                        placeholder="Supplier name"
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
                        type="submit"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving"
                    >
                        {{ state.saving ? 'Saving...' : 'Create Supplier' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Supplier List">
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
                                        <button
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            @click="startEdit(supplier)"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading suppliers...
            </p>
            <p v-if="state.error" class="text-sm text-destructive">
                {{ state.error }}
            </p>
            <p v-if="state.success" class="text-sm text-emerald-600">
                {{ state.success }}
            </p>
        </div>
    </AppLayout>
</template>
