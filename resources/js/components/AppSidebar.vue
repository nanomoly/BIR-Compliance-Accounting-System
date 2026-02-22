<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Database,
    FileSearch,
    FileSpreadsheet,
    LayoutGrid,
    ReceiptText,
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
        icon: Users,
        requiredPermission: 'suppliers.view',
    },
    {
        title: 'Ledgers',
        href: '/cas/ledgers',
        icon: BookOpen,
        requiredPermission: 'ledgers.view',
    },
];

const transactionNavItems: SidebarNavItem[] = [
    {
        title: 'Journals',
        href: '/cas/journals',
        icon: NotebookPen,
        requiredPermission: 'journals.view',
    },
    {
        title: 'E-Invoicing',
        href: '/cas/e-invoicing',
        icon: ReceiptText,
        requiredPermission: 'e_invoices.view',
    },
];

const reportNavItems: SidebarNavItem[] = [
    {
        title: 'Reports',
        href: '/cas/reports',
        icon: FileSpreadsheet,
        requiredPermission: 'reports.view',
    },
];

const complianceNavItems: SidebarNavItem[] = [
    {
        title: 'System Users',
        href: '/cas/users',
        icon: Users,
        requiredPermission: 'users.view',
    },
    {
        title: 'Backups',
        href: '/cas/backups',
        icon: Database,
        requiredPermission: 'backups.view',
    },
    {
        title: 'System Info',
        href: '/cas/system-info',
        icon: ShieldCheck,
        requiredPermission: 'system_info.view',
    },
    {
        title: 'User Access',
        href: '/cas/user-access',
        icon: Users,
        requiredPermission: 'user_access.view',
    },
    {
        title: 'Audit Trail',
        href: '/cas/audit-trail',
        icon: FileSearch,
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
const visibleTransactionNavItems = computed(() =>
    filterByPermission(transactionNavItems),
);
const visibleReportNavItems = computed(() => filterByPermission(reportNavItems));
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
            <NavMain v-if="visibleCoreNavItems.length > 0" :items="visibleCoreNavItems" label="Core" />
            <NavMain
                v-if="visibleTransactionNavItems.length > 0"
                :items="visibleTransactionNavItems"
                label="Transactions"
            />
            <NavMain v-if="visibleReportNavItems.length > 0" :items="visibleReportNavItems" label="Reports" />
            <NavMain
                v-if="visibleComplianceNavItems.length > 0"
                :items="visibleComplianceNavItems"
                label="Compliance"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="footerNavItems.length > 0" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
