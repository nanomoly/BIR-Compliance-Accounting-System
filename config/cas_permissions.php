<?php

return [
    'modules' => [
        'accounts' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'suppliers' => ['view', 'create', 'update', 'delete'],
        'branches' => ['view', 'create', 'update', 'delete'],
        'inventory' => ['view', 'create', 'update', 'delete'],
        'sales' => ['view', 'create', 'update', 'delete'],
        'collections' => ['view', 'create'],
        'purchases' => ['view', 'create', 'update', 'delete'],
        'hr' => ['view', 'create', 'update', 'delete'],
        'payroll' => ['view', 'create', 'update'],
        'banking' => ['view', 'create', 'update', 'delete'],
        'journals' => ['view', 'create', 'post', 'reverse'],
        'reports' => ['view', 'export'],
        'ledgers' => ['view'],
        'backups' => ['view', 'create', 'restore'],
        'system_info' => ['view', 'update'],
        'e_invoices' => ['view', 'create', 'issue', 'cancel', 'transmit'],
        'users' => ['view', 'create', 'update'],
        'audit_trail' => ['view'],
        'user_access' => ['view', 'assign'],
    ],
];
