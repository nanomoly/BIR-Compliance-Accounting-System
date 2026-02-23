<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, toRef } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useQueryTabSync } from '@/composables/useQueryTabSync';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Employee = {
    id: number;
    employee_no: string;
    first_name: string;
    last_name: string;
    position: string | null;
    department: string | null;
    hire_date: string | null;
    monthly_rate: number | null;
    is_active: boolean;
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'HR', href: '/cas/hr' },
];

const state = reactive({
    activeTab: 'employee-input',
    employees: [] as Employee[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
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

const { syncFromQuery } = useQueryTabSync(toRef(state, 'activeTab'), ['employee-input', 'employee-records']);

const form = reactive({
    employee_no: '',
    first_name: '',
    last_name: '',
    position: '',
    department: '',
    hire_date: '',
    monthly_rate: 0,
    is_active: true,
});

const editForm = reactive({
    employee_no: '',
    first_name: '',
    last_name: '',
    position: '',
    department: '',
    hire_date: '',
    monthly_rate: 0,
    is_active: true,
});

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            data: Employee[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        }>(`/api/employees?per_page=${state.perPage}&page=${page}`);

        state.employees = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load employees.';
    } finally {
        state.loading = false;
    }
}

function exportHr() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/hr?${query.toString()}`, '_blank');
}

async function createEmployee() {
    if (!can('hr.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/employees', {
            employee_no: form.employee_no,
            first_name: form.first_name,
            last_name: form.last_name,
            position: form.position || null,
            department: form.department || null,
            hire_date: form.hire_date || null,
            monthly_rate: form.monthly_rate || null,
            is_active: form.is_active,
        });

        state.success = 'Employee created.';
        form.employee_no = '';
        form.first_name = '';
        form.last_name = '';
        form.position = '';
        form.department = '';
        form.hire_date = '';
        form.monthly_rate = 0;
        form.is_active = true;
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create employee.';
    } finally {
        state.saving = false;
    }
}

function startEdit(employee: Employee) {
    state.editingId = employee.id;
    editForm.employee_no = employee.employee_no;
    editForm.first_name = employee.first_name;
    editForm.last_name = employee.last_name;
    editForm.position = employee.position ?? '';
    editForm.department = employee.department ?? '';
    editForm.hire_date = employee.hire_date ?? '';
    editForm.monthly_rate = employee.monthly_rate ?? 0;
    editForm.is_active = employee.is_active;
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveEdit(employeeId: number) {
    if (!can('hr.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/employees/${employeeId}`, {
            employee_no: editForm.employee_no,
            first_name: editForm.first_name,
            last_name: editForm.last_name,
            position: editForm.position || null,
            department: editForm.department || null,
            hire_date: editForm.hire_date || null,
            monthly_rate: editForm.monthly_rate || null,
            is_active: editForm.is_active,
        });

        state.success = 'Employee updated.';
        state.editingId = 0;
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to update employee.';
    } finally {
        state.saving = false;
    }
}

async function deleteEmployee(employeeId: number) {
    if (!can('hr.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/employees/${employeeId}`);
        state.success = 'Employee deleted.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete employee.';
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
    <Head title="HR" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'employee-input' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'employee-input'">Employee Input</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'employee-records' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'employee-records'">Employee Records</button>
            </div>

            <SectionCard v-if="state.activeTab === 'employee-input'" title="Add Employee" description="Maintain employee master records for HR and payroll reference.">
                <form class="grid gap-3 md:grid-cols-4" @submit.prevent="createEmployee">
                    <label class="grid gap-1 text-sm">
                        <span>Employee No</span>
                        <input v-model="form.employee_no" required class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>First Name</span>
                        <input v-model="form.first_name" required class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Last Name</span>
                        <input v-model="form.last_name" required class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Position</span>
                        <input v-model="form.position" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Department</span>
                        <input v-model="form.department" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Hire Date</span>
                        <input v-model="form.hire_date" type="date" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Monthly Rate</span>
                        <input v-model.number="form.monthly_rate" type="number" step="0.01" min="0" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>Active</span>
                        <span class="flex items-center gap-2 rounded border px-3 py-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" />
                            Yes
                        </span>
                    </label>
                    <div class="flex items-end">
                        <button v-if="can('hr.create')" type="submit" class="rounded bg-primary px-4 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                            {{ state.saving ? 'Saving...' : 'Create Employee' }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'employee-records'" title="Employee List">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                    <div class="flex items-end gap-2">
                        <label class="grid gap-1 text-xs">
                            <span>From</span>
                            <input v-model="state.exportFromDate" type="date" class="rounded border px-2 py-2 text-sm" />
                        </label>
                        <label class="grid gap-1 text-xs">
                            <span>To</span>
                            <input v-model="state.exportToDate" type="date" class="rounded border px-2 py-2 text-sm" />
                        </label>
                        <button v-if="can('hr.view')" type="button" class="self-end rounded border px-3 py-2 text-sm" @click="exportHr">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Employee No</th>
                                <th class="px-2 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-left">Position</th>
                                <th class="px-2 py-2 text-left">Department</th>
                                <th class="px-2 py-2 text-left">Hire Date</th>
                                <th class="px-2 py-2 text-left">Rate</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="employee in state.employees" :key="employee.id" class="border-b">
                                <template v-if="state.editingId === employee.id">
                                    <td class="px-2 py-2"><input v-model="editForm.employee_no" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><div class="grid gap-1"><input v-model="editForm.first_name" class="rounded border px-2 py-1" /><input v-model="editForm.last_name" class="rounded border px-2 py-1" /></div></td>
                                    <td class="px-2 py-2"><input v-model="editForm.position" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="editForm.department" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="editForm.hire_date" type="date" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model.number="editForm.monthly_rate" type="number" step="0.01" class="w-full rounded border px-2 py-1" /></td>
                                    <td class="px-2 py-2"><input v-model="editForm.is_active" type="checkbox" /></td>
                                    <td class="px-2 py-2">
                                        <div class="flex gap-2">
                                            <button v-if="can('hr.update')" type="button" class="rounded border px-2 py-1" :disabled="state.saving" @click="saveEdit(employee.id)">Save</button>
                                            <button type="button" class="rounded border px-2 py-1" @click="cancelEdit">Cancel</button>
                                        </div>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-2 py-2">{{ employee.employee_no }}</td>
                                    <td class="px-2 py-2">{{ employee.first_name }} {{ employee.last_name }}</td>
                                    <td class="px-2 py-2">{{ employee.position ?? '-' }}</td>
                                    <td class="px-2 py-2">{{ employee.department ?? '-' }}</td>
                                    <td class="px-2 py-2">{{ formatPhDateOnly(employee.hire_date) }}</td>
                                    <td class="px-2 py-2">{{ employee.monthly_rate != null ? formatAmount(employee.monthly_rate) : '-' }}</td>
                                    <td class="px-2 py-2">{{ employee.is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex gap-2">
                                            <button v-if="can('hr.update')" type="button" class="rounded border px-2 py-1" @click="startEdit(employee)">Edit</button>
                                            <button v-if="can('hr.delete')" type="button" class="rounded border px-2 py-1" :disabled="state.deleting" @click="deleteEmployee(employee.id)">Delete</button>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage <= 1" @click="loadData(state.currentPage - 1)">Previous</button>
                    <span class="text-sm text-muted-foreground">Page {{ state.currentPage }} of {{ state.lastPage }}</span>
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage >= state.lastPage" @click="loadData(state.currentPage + 1)">Next</button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading HR data...</p>
        </div>
    </AppLayout>
</template>
