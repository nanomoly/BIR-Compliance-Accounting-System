<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BookOpenText,
    Building2,
    Check,
    Copy,
    Database,
    FileSearch,
    FileSpreadsheet,
    Folder,
    LayoutGrid,
    Menu,
    Monitor,
    ReceiptText,
    ScanLine,
    Search,
    Settings,
    ShieldBan,
    NotebookPen,
    Users,
    ShieldCheck,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

type SidebarNavItem = NavItem & {
    requiredPermission?: string;
};

const coreNavItems: SidebarNavItem[] = [
    {
        title: 'CAS Dashboard',
        href: '/cas',
        icon: LayoutGrid,
    },
];

const setupNavItems: SidebarNavItem[] = [
    {
        title: 'Branches',
        href: '/cas/branches',
        icon: Building2,
        requiredPermission: 'branches.view',
    },
    {
        title: 'Chart of Accounts',
        href: '/cas/accounts',
        icon: Wallet,
        requiredPermission: 'accounts.view',
    },
    {
        title: 'Customers',
        href: '/cas/customers',
        icon: Users,
        requiredPermission: 'customers.view',
    },
    {
        title: 'Suppliers',
        href: '/cas/suppliers',
        icon: Folder,
        requiredPermission: 'suppliers.view',
    },
    {
        title: 'HR',
        href: '/cas/hr',
        icon: ShieldBan,
        requiredPermission: 'hr.view',
    },
];

const operationsNavItems: SidebarNavItem[] = [
    {
        title: 'Inventory',
        href: '/cas/inventory',
        icon: BookOpen,
        requiredPermission: 'inventory.view',
    },
    {
        title: 'Sales Orders',
        href: '/cas/sales',
        icon: ReceiptText,
        requiredPermission: 'sales.view',
    },
    {
        title: 'Collections',
        href: '/cas/collections',
        icon: BookOpenText,
        requiredPermission: 'collections.view',
    },
    {
        title: 'Purchase Orders',
        href: '/cas/purchases',
        icon: FileSearch,
        requiredPermission: 'purchases.view',
    },
    {
        title: 'Payroll',
        href: '/cas/payroll',
        icon: Check,
        requiredPermission: 'payroll.view',
    },
    {
        title: 'Banking',
        href: '/cas/banking',
        icon: Database,
        requiredPermission: 'banking.view',
    },
];

const accountingNavItems: SidebarNavItem[] = [
    {
        title: 'Journals',
        href: '/cas/journals',
        icon: NotebookPen,
        requiredPermission: 'journals.view',
    },
    {
        title: 'E-Invoicing',
        href: '/cas/e-invoicing',
        icon: Copy,
        requiredPermission: 'e_invoices.view',
    },
    {
        title: 'Ledgers',
        href: '/cas/ledgers',
        icon: ScanLine,
        requiredPermission: 'ledgers.view',
    },
    {
        title: 'Reports',
        href: '/cas/reports',
        icon: FileSpreadsheet,
        requiredPermission: 'reports.view',
    },
];

const complianceNavItems: SidebarNavItem[] = [
    {
        title: 'User Access',
        href: '/cas/user-access',
        icon: Settings,
        requiredPermission: 'user_access.view',
    },
    {
        title: 'System Users',
        href: '/cas/users',
        icon: Menu,
        requiredPermission: 'users.view',
    },
    {
        title: 'Backups',
        href: '/cas/backups',
        icon: Monitor,
        requiredPermission: 'backups.view',
    },
    {
        title: 'System Info',
        href: '/cas/system-info',
        icon: ShieldCheck,
        requiredPermission: 'system_info.view',
    },
    {
        title: 'Audit Trail',
        href: '/cas/audit-trail',
        icon: Search,
        requiredPermission: 'audit_trail.view',
    },
];

const footerNavItems: NavItem[] = [
];

const page = usePage<{ auth: { permissions: string[] } }>();

const grantedPermissions = computed(
    () => page.props.auth?.permissions ?? [],
);

function filterByPermission(items: SidebarNavItem[]): NavItem[] {
    return items.filter((item) => {
        if (!item.requiredPermission) {
            return true;
        }

        return grantedPermissions.value.includes(item.requiredPermission);
    });
}

const visibleCoreNavItems = computed(() => filterByPermission(coreNavItems));
const visibleSetupNavItems = computed(() =>
    filterByPermission(setupNavItems),
);
const visibleOperationsNavItems = computed(() =>
    filterByPermission(operationsNavItems),
);
const visibleAccountingNavItems = computed(() =>
    filterByPermission(accountingNavItems),
);
const visibleComplianceNavItems = computed(() =>
    filterByPermission(complianceNavItems),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/cas">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain v-if="visibleCoreNavItems.length > 0" :items="visibleCoreNavItems" label="Overview" />
            <NavMain
                v-if="visibleSetupNavItems.length > 0"
                :items="visibleSetupNavItems"
                label="Setup"
            />
            <NavMain
                v-if="visibleOperationsNavItems.length > 0"
                :items="visibleOperationsNavItems"
                label="Operations"
            />
            <NavMain
                v-if="visibleAccountingNavItems.length > 0"
                :items="visibleAccountingNavItems"
                label="Accounting"
            />
            <NavMain
                v-if="visibleComplianceNavItems.length > 0"
                :items="visibleComplianceNavItems"
                label="Administration"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="footerNavItems.length > 0" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
