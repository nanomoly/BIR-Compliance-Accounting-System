<?php

return [
    'modules' => [
        'accounts' => ['view', 'create', 'update', 'delete'],
        'customers' => ['view', 'create', 'update'],
        'suppliers' => ['view', 'create', 'update'],
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
