# BIR Compliance Accounting System (CAS) — System Documentation

> **Version:** 1.0 &nbsp;|&nbsp; **Generated:** February 22, 2026 &nbsp;|&nbsp; **Framework:** Laravel 12 + Vue 3 + Inertia.js

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Architecture](#3-architecture)
4. [Authentication & Authorization](#4-authentication--authorization)
5. [Module Reference](#5-module-reference)
   - 5.1 [Setup & Master Data](#51-setup--master-data)
   - 5.2 [Operations](#52-operations)
   - 5.3 [Accounting Engine](#53-accounting-engine)
   - 5.4 [Financial Reports](#54-financial-reports)
   - 5.5 [Administration](#55-administration)
6. [Data Models](#6-data-models)
7. [API Reference](#7-api-reference)
8. [Business Rules & Validations](#8-business-rules--validations)
9. [Events & Listeners](#9-events--listeners)
10. [Permissions Matrix](#10-permissions-matrix)
11. [Frontend Architecture](#11-frontend-architecture)
12. [Deployment & Configuration](#12-deployment--configuration)

---

## 1. System Overview

The **BIR Compliance Accounting System (CAS)** is a full-featured, web-based double-entry accounting application designed for Philippine businesses. It supports the complete accounting cycle — from master data setup, through sales/purchase/payroll operations, to journal posting, ledger maintenance, and BIR-mandated financial report generation.

### Key Capabilities

| Area | Features |
|------|----------|
| **Chart of Accounts** | Hierarchical account tree with parent-child relationships |
| **Double-Entry Bookkeeping** | Draft → Post → Reverse lifecycle with balanced-entry validation |
| **Sales Cycle** | Sales Orders → Confirm → Invoice → Collection Receipt |
| **Purchase Cycle** | Purchase Orders → Receive → Bill |
| **Payroll** | PH statutory deductions (SSS, PhilHealth, Pag-IBIG, withholding tax) with maker-checker |
| **Banking** | Bank accounts, transactions, CSV statement import, auto-match reconciliation |
| **E-Invoicing** | Draft → Issue → Transmit to BIR EIS (with PDF printing) |
| **BIR Reports** | Trial Balance, Balance Sheet, Income Statement, Journal Book, GL Book, AR/AP/Customer/Supplier Ledgers |
| **Multi-Format Export** | JSON, PDF (DomPDF), Excel (Maatwebsite) for all reports; Excel export for all modules |
| **Audit Trail** | Automatic audit logging via Eloquent observers + user activity logs |
| **Role-Based Access** | 3 roles (Admin, Accountant, Auditor) with 21-module granular permission matrix |
| **Two-Factor Authentication** | TOTP-based 2FA via Laravel Fortify |
| **Backup & Restore** | JSON-based database snapshots with selective table backup |

---

## 2. Technology Stack

### Backend

| Component | Technology | Version |
|-----------|-----------|---------|
| Framework | Laravel | 12.x |
| Language | PHP | ≥ 8.2 |
| Auth | Laravel Fortify | 1.30+ |
| Permissions | Spatie Laravel Permission | latest |
| PDF | barryvdh/laravel-dompdf | latest |
| Excel | Maatwebsite/Excel | latest |
| Date/Time | CarbonImmutable | (via Laravel) |
| Testing | Pest | 3.x |

### Frontend

| Component | Technology | Version |
|-----------|-----------|---------|
| Framework | Vue.js | 3.5+ |
| SPA Bridge | Inertia.js | 2.x |
| Build Tool | Vite | 7.x |
| Language | TypeScript | 5.2+ |
| Styling | Tailwind CSS | 4.x |
| Charts | Chart.js + vue-chartjs | 4.5 / 5.3 |
| UI Primitives | Reka UI | 2.6+ |
| Icons | Lucide Vue Next | 0.468+ |

### Infrastructure

| Component | Technology |
|-----------|-----------|
| Web Server | Apache (XAMPP) |
| Database | MySQL |
| Runtime | PHP-FPM via XAMPP |

---

## 3. Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────┐
│                    Browser (SPA)                     │
│  Vue 3 + Inertia.js + TypeScript + Tailwind CSS     │
└────────────────────────┬────────────────────────────┘
                         │ Inertia Protocol + JSON API
┌────────────────────────┴────────────────────────────┐
│                  Laravel 12 Backend                   │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌───────────────────┐  │
│  │ Inertia  │  │   API    │  │   Form Requests   │  │
│  │ Pages    │  │Controllers│  │   (Validation)    │  │
│  └────┬─────┘  └────┬─────┘  └───────────────────┘  │
│       │              │                                │
│  ┌────┴──────────────┴─────────────────────────────┐ │
│  │              Service Layer                       │ │
│  │  AccountService · JournalEntryService            │ │
│  │  EInvoicingService · ReportEngineService         │ │
│  │  AccessControlService · UserManagementService    │ │
│  │  BackupService · Report Services                 │ │
│  └────────────────────┬────────────────────────────┘ │
│                       │                               │
│  ┌────────────────────┴────────────────────────────┐ │
│  │           Repository Layer (Contracts)           │ │
│  │  AccountRepo · JournalEntryRepo · LedgerRepo    │ │
│  │  InvoiceRepo · UserRepo                         │ │
│  └────────────────────┬────────────────────────────┘ │
│                       │                               │
│  ┌────────────────────┴────────────────────────────┐ │
│  │          Eloquent Models (33 models)             │ │
│  │  + Observers · Events · Listeners · Policies    │ │
│  └─────────────────────────────────────────────────┘ │
└──────────────────────────┬──────────────────────────┘
                           │
                    ┌──────┴──────┐
                    │   MySQL DB  │
                    └─────────────┘
```

### Directory Structure

```
app/
├── Actions/            # Single-purpose action classes (control numbers, references)
├── Concerns/           # Traits (Auditable, PasswordValidationRules)
├── DTOs/               # Immutable data transfer objects
├── Enums/              # PHP 8.1+ backed enums
├── Events/             # Domain events (JournalEntryPosted)
├── Exports/            # Maatwebsite Excel export classes
├── Http/
│   ├── Controllers/
│   │   ├── Api/        # 23 JSON API controllers
│   │   └── Cas/        # CasPageController (Inertia page renderer)
│   ├── Middleware/      # HandleInertiaRequests, LogUserActivity, HandleAppearance
│   └── Requests/       # 35 form request validators
├── Listeners/          # Event listeners (PostJournalEntryToLedger)
├── Models/             # 33 Eloquent models
├── Observers/          # AuditObserver
├── Policies/           # AccountPolicy, JournalEntryPolicy
├── Providers/          # App, Auth, Event, Fortify service providers
├── Repositories/
│   ├── Contracts/      # 5 repository interfaces
│   └── Eloquent/       # 5 concrete repository implementations
└── Services/
    ├── Accounting/     # 6 core business services
    └── Reports/        # 4 report generation services

resources/js/
├── components/cas/     # SectionCard.vue (shared card component)
├── composables/        # 10 Vue composable utilities
├── layouts/            # AppLayout.vue (main layout)
├── lib/                # utils.ts (formatPhDateOnly, formatPhDateTime, formatAmount)
├── pages/
│   ├── auth/           # Login, Register, ForgotPassword, ResetPassword, etc.
│   ├── cas/            # 21 CAS module pages
│   └── settings/       # Profile, Password, Appearance, TwoFactor
└── types/              # TypeScript type definitions
```

### Request Lifecycle

1. **Browser** sends request via Inertia.js (page navigation) or fetch (API call)
2. **Laravel Router** matches route → applies middleware (`auth`, `verified`, permission gate)
3. **CasPageController** renders Inertia pages with initial props OR **API Controller** handles JSON
4. **Form Requests** validate input data
5. **Services** execute business logic within database transactions
6. **Repositories** abstract Eloquent persistence
7. **Events/Listeners** handle side effects (e.g., ledger posting after journal entry post)
8. **Observers** capture audit logs automatically
9. Response returns as Inertia page render or JSON

---

## 4. Authentication & Authorization

### Authentication (Laravel Fortify)

| Feature | Status |
|---------|--------|
| Email/Password Login | ✅ Enabled |
| Email Verification | ✅ Enabled |
| Two-Factor Authentication | ✅ TOTP with recovery codes |
| Password Reset | ✅ Email-based |
| Registration | ❌ Disabled (admin creates users) |
| Rate Limiting | Login: 5/min, 2FA: 5/min |

**Password Requirements (Production):** Minimum 12 characters, mixed case, letters, numbers, symbols, not compromised (Have I Been Pwned check).

### Authorization Model

The system uses a **dual-layer** authorization approach:

1. **Spatie Permissions** — Granular module-level permissions (e.g., `journals.post`)
2. **Legacy Role Check** — `UserRole` enum column (`admin`, `accountant`, `auditor`)
3. **Policies** — Combine both checks (permission OR role) for key models

### User Roles

| Role | Description |
|------|-------------|
| **Admin** | Full system access, user management, backup/restore |
| **Accountant** | Create/post journals, manage operations, generate reports |
| **Auditor** | Read-only access to all financial data and audit trail |

### Shared Inertia Props

Every page receives these via `HandleInertiaRequests` middleware:

| Prop | Type | Description |
|------|------|-------------|
| `name` | `string` | Application name |
| `companyName` | `string` | Company profile name (for sidebar) |
| `auth.user` | `object` | Authenticated user |
| `auth.permissions` | `string[]` | Flat list of granted permission names |
| `sidebarOpen` | `boolean` | Sidebar state from cookie |

---

## 5. Module Reference

### 5.1 Setup & Master Data

#### Chart of Accounts
- **Page:** `Accounts.vue` — **Route:** `GET /cas/accounts`
- **Purpose:** Maintain the hierarchical chart of accounts
- **Fields:** Code*, Name*, Type (Asset/Liability/Equity/Revenue/Expense), Normal Balance (Debit/Credit), Parent Account, Active flag
- **Features:** CRUD, parent-child tree structure, Excel export
- **API:** `apiResource('accounts', AccountController)`

#### Branches
- **Page:** `Branches.vue` — **Route:** `GET /cas/branches`
- **Purpose:** Manage company branches for multi-branch operations
- **Fields:** Code*, Branch Name*, TIN, Address*, Main Branch flag
- **Features:** CRUD, main branch designation, Excel export
- **API:** Standard CRUD endpoints under `/api/branches`

#### Customers
- **Page:** `Customers.vue` — **Route:** `GET /cas/customers`
- **Purpose:** Customer master file
- **Fields:** Code*, Name*, TIN, Address, Email, Phone
- **Features:** CRUD, catalog API (for dropdowns), Excel export, soft deletes
- **API:** Standard CRUD + `/api/customers/catalog`

#### Suppliers
- **Page:** `Suppliers.vue` — **Route:** `GET /cas/suppliers`
- **Purpose:** Supplier/vendor master file
- **Fields:** Code*, Name*, TIN, Address, Email, Phone
- **Features:** CRUD, catalog API (for dropdowns), Excel export, soft deletes
- **API:** Standard CRUD + `/api/suppliers/catalog`

#### Human Resources
- **Page:** `HR.vue` — **Route:** `GET /cas/hr`
- **Purpose:** Employee master file for payroll processing
- **Fields:** Employee No*, First Name*, Last Name*, Position, Department, Hire Date, Monthly Rate, Active flag
- **Features:** CRUD, inline editing, Excel export, soft deletes
- **API:** Standard CRUD under `/api/employees`

#### Inventory Items
- **Page:** `Inventory.vue` (Items tab) — **Route:** `GET /cas/inventory`
- **Purpose:** Inventory item catalog
- **Fields:** SKU*, Name*, Unit, Quantity on Hand, Reorder Level, Active flag
- **Features:** CRUD, Excel export, soft deletes
- **API:** Standard CRUD under `/api/inventory-items`

---

### 5.2 Operations

#### Sales
- **Page:** `Sales.vue` — **Route:** `GET /cas/sales`
- **Purpose:** Complete sales order lifecycle

**Workflow:**
```
Draft SO → Confirm → Convert to Invoice → Collect Payment (via Collections)
```

| Action | Status Change | Permission |
|--------|--------------|------------|
| Create SO | → `draft` | `sales.create` |
| Update SO | (draft only) | `sales.update` |
| Confirm SO | `draft` → `confirmed` | `sales.update` |
| Convert to Invoice | `confirmed` → `invoiced` | `sales.update` |
| Delete SO | (draft only) | `sales.delete` |

- **Auto-numbering:** `SO-{timestamp}-{seq}`
- **Invoice conversion** creates a `sales` type Invoice with lines mirrored from the SO

#### Purchases
- **Page:** `Purchases.vue` — **Route:** `GET /cas/purchases`
- **Purpose:** Complete purchase order lifecycle

**Workflow:**
```
Draft PO → Receive → Convert to Bill
```

| Action | Status Change | Permission |
|--------|--------------|------------|
| Create PO | → `draft` | `purchases.create` |
| Update PO | (draft/ordered) | `purchases.update` |
| Receive PO | → `received` | `purchases.update` |
| Convert to Bill | `received` → `billed` | `purchases.update` |
| Delete PO | (draft only) | `purchases.delete` |

- **Auto-numbering:** `PO-{timestamp}-{seq}`
- **Bill conversion** creates a `purchase` type Invoice using `received_quantity` for line amounts

#### Collections
- **Page:** `Collections.vue` — **Route:** `GET /cas/collections`
- **Purpose:** Issue collection receipts against sales invoices
- **Fields:** Invoice (dropdown), Receipt Date, Amount, Payment Method (Cash/Bank/Check/Online), Reference #, Remarks
- **Features:** Auto-selects invoice balance, tracks total/paid/balance status
- **Auto-numbering:** Receipt numbers
- **API:** `/api/collections/receipts` (index + store), `/api/collections/catalog` (invoice list)

#### Inventory Movements
- **Page:** `Inventory.vue` (Movements tab) — **Route:** `GET /cas/inventory`
- **Purpose:** Track stock movements
- **Types:** In, Out, Adjust, Transfer
- **Fields:** Item, Date, Type, Quantity, Unit Cost, Remarks
- **Features:** Paginated movement list, Excel export
- **API:** `/api/inventory-movements` (index + store)

#### Payroll
- **Page:** `Payroll.vue` — **Route:** `GET /cas/payroll`
- **Purpose:** Semi-monthly payroll processing with PH statutory deductions

**Workflow:**
```
Create Period → Generate Run → Approve → Post to Journal
```

| Action | Status Change | Permission |
|--------|--------------|------------|
| Create Period | → `open` | `payroll.create` |
| Generate Run | → `draft` | `payroll.create` |
| Approve Run | `draft` → `approved` | `payroll.update` |
| Post Run | `approved` → `posted` | `payroll.update` |

**Deduction Calculations:**

| Deduction | Formula | Default Cap |
|-----------|---------|-------------|
| SSS Employee | gross × 4.5% | ₱1,125 |
| PhilHealth Employee | (gross × 5%) / 2 | ₱2,500 |
| Pag-IBIG Employee | gross × 2% | ₱100 |
| Withholding Tax | taxable_pay × rate | (configurable) |

- **Proration:** `days_in_period / 30 × monthly_rate`
- **Maker-Checker:** Approver cannot be the same user who created the run
- **Run Limit:** Only one draft run per period

#### Banking
- **Page:** `Banking.vue` — **Route:** `GET /cas/banking`
- **Purpose:** Bank account management, transactions, statement import, and reconciliation

**Tabs:**
1. **Accounts** — CRUD for bank accounts (bank name, account name/number, type, balance)
2. **Transactions** — Record credit/debit transactions with references
3. **Statement Import** — Paste CSV (date, description, reference, type, amount, balance) → creates statement + statement lines + opens reconciliation
4. **Reconciliation Workbench** — Match statement lines to bank transactions

**Reconciliation Features:**
- **Auto-Suggest Matching:** Scoring algorithm (type match required + amount proximity ≤60pts + date proximity ≤20pts + reference match ≤25pts)
- **Manual Match/Unmatch**
- **Unmatched Reason Tagging**
- **Close/Reopen** — Close only when `|difference| ≤ 0.01`
- **Formula:** `cleared_balance = opening + Σ(signed matched amounts)`; `difference = closing - cleared`

---

### 5.3 Accounting Engine

#### Journal Entries
- **Page:** `Journals.vue` — **Route:** `GET /cas/journals`
- **Purpose:** Core double-entry bookkeeping

**Journal Types:** General, Sales, Purchase, Cash Receipts, Cash Disbursements

**Workflow:**
```
Draft Entry → Post → (optional) Reverse
```

| Action | Status Change | Permission | Constraint |
|--------|--------------|------------|------------|
| Create | → `draft` | `journals.create` | Debits must equal credits |
| Post | `draft` → `posted` | `journals.post` | Maker-checker (poster ≠ creator) |
| Reverse | `posted` → `reversed` | `journals.reverse` | Creates new reversal entry with swapped debits/credits |

**Key Rules:**
- **Balanced Entry:** `round(Σ debits, 2) === round(Σ credits, 2)` enforced on creation
- **Immutability:** Posted entries cannot be deleted — use reversal instead
- **Locking:** `posted_at` and `locked_at` timestamps set on posting
- **Auto-numbering:** `JE-{timestamp}-{seq}` for entry number, `CTL-{timestamp}-{seq}` for control number
- **Event:** Posting fires `JournalEntryPosted` → listener posts to General Ledger

#### E-Invoicing (BIR Compliance)
- **Page:** `EInvoicing.vue` — **Route:** `GET /cas/e-invoicing`
- **Purpose:** Electronic invoice management for BIR compliance

**Workflow:**
```
Draft Invoice → Issue → Transmit to BIR
                  └──→ Cancel
```

| Action | Status Change | Permission |
|--------|--------------|------------|
| Create | → `draft` | `e_invoices.create` |
| Issue | `draft` → `issued` | `e_invoices.issue` |
| Cancel | `issued` → `cancelled` | `e_invoices.cancel` |
| Transmit | `issued` → (transmitted) | `e_invoices.transmit` |
| Print PDF | — | `e_invoices.view` |

**Invoice Types:** Sales, Service, Purchase

**Transmission:** Currently simulated — builds BIR-EIS XML-like payload (seller/buyer/lines/totals, currency PHP) and creates a transmission record with reference number.

#### Ledgers
- **Page:** `Ledgers.vue` — **Route:** `GET /cas/ledgers`
- **Purpose:** View subsidiary ledger postings
- **Features:** Query-based ledger view, dynamic column rendering, Excel export
- **Data Flow:** Automatically populated when journal entries are posted (via `PostJournalEntryToLedger` listener)

---

### 5.4 Financial Reports

- **Page:** `Reports.vue` — **Route:** `GET /cas/reports`
- **Formats:** JSON (on-screen), PDF (DomPDF), Excel (Maatwebsite)

| Report | Service | Description |
|--------|---------|-------------|
| **Trial Balance** | `TrialBalanceService` | Debit/credit totals per account, filtered by date range |
| **Balance Sheet** | `FinancialStatementService` | Assets, Liabilities, Equity net values |
| **Income Statement** | `FinancialStatementService` | Revenue, Expense, Net Income |
| **Journal Book** | `BooksReportService` | All journal entries with lines in date range |
| **General Ledger Book** | `BooksReportService` | All ledger postings with running balance |
| **AR Ledger** | `BooksReportService` | Accounts Receivable subsidiary ledger (by customer) |
| **AP Ledger** | `BooksReportService` | Accounts Payable subsidiary ledger (by supplier) |
| **Customer Ledger** | `BooksReportService` | Customer-specific journal lines |
| **Supplier Ledger** | `BooksReportService` | Supplier-specific journal lines |

**Report Engine:**
- Every report generation creates a `ReportRun` audit record
- Unique reference: `RPT-{TYPE4}-{timestamp}-{seq}`
- Page count: `ceil(rows / 50)`, minimum 1
- PDF rendered via `reports.generic` Blade view
- Excel generated via `ArrayReportExport` class

---

### 5.5 Administration

#### User Management
- **Page:** `Users.vue` — **Route:** `GET /cas/users`
- **Purpose:** Create and list system users
- **Fields:** Name, Email, Password, Role (Admin/Accountant/Auditor), Branch
- **Note:** Self-registration is disabled; only admins create users

#### User Access Control
- **Page:** `UserAccess.vue` — **Route:** `GET /cas/user-access`
- **Purpose:** Assign granular module permissions to users
- **Features:** View/assign roles and permissions per user
- **Protection:** Default admin (`admin@cas.local`) always retains Admin role

#### System Info
- **Page:** `SystemInfo.vue` — **Route:** `GET /cas/system-info`
- **Purpose:** View and update company profile
- **Fields:** Company Name, TIN, Registered Address, Software Version, Database Version, Developer Name, Developer TIN

#### Backups
- **Page:** `Backups.vue` — **Route:** `GET /cas/backups`
- **Purpose:** Database snapshot backup and restore
- **Default Tables:** `accounts`, `journal_entries`, `journal_entry_lines`, `ledgers`
- **Storage:** JSON files in `storage/app/backups/`
- **Warning:** Restore operation truncates target tables before re-inserting

#### Audit Trail
- **Page:** `AuditTrail.vue` — **Route:** `GET /cas/audit-trail`
- **Purpose:** View system audit logs and user activity
- **Two data sources:**
  1. **Audit Logs:** Model-level create/update/delete events (via `AuditObserver`) — stores old/new values
  2. **User Activity Logs:** Route-level activity tracking (via `LogUserActivity` middleware)

#### Dashboard
- **Page:** `Dashboard.vue` — **Route:** `GET /cas`
- **Features:** KPI cards (receivables, payables, P&L, bank balance) + Cash Flow combo chart (bar + line via Chart.js)

---

## 6. Data Models

### Entity Relationship Overview

```
CompanyProfile ─1:N─ Branch ─1:N─┬─ Account (hierarchical, self-referential)
                                   ├─ Customer
                                   ├─ Supplier
                                   ├─ Employee
                                   ├─ BankAccount ─1:N─ BankTransaction
                                   │                 └─ BankStatement ─1:N─ BankStatementLine
                                   │                                   └─ BankReconciliation ─1:N─ BankReconciliationMatch
                                   ├─ InventoryItem ─1:N─ InventoryMovement
                                   ├─ SalesOrder ─1:N─ SalesOrderLine
                                   ├─ PurchaseOrder ─1:N─ PurchaseOrderLine
                                   └─ Invoice ─1:N─┬─ InvoiceLine
                                                    ├─ SalesReceipt
                                                    └─ EInvoiceTransmission

JournalEntry ─1:N─ JournalEntryLine ──→ Account
                                      ──→ Customer (optional subsidiary)
                                      ──→ Supplier (optional subsidiary)

Account ─1:N─ Ledger ←── (populated via PostJournalEntryToLedger listener)

PayrollPeriod ─1:N─ PayrollRun ─1:N─ PayrollRunLine ──→ Employee

User ──→ Branch (optional)
     ──→ Spatie Roles & Permissions
```

### Model Summary (33 Models)

| Model | Key Fields | Soft Delete | Relationships |
|-------|-----------|-------------|---------------|
| **Account** | code, name, type, normal_balance, parent_id | ✅ | Branch, Parent/Children (self), Ledger |
| **AuditLog** | event, auditable_type/id, old/new_values | — | User |
| **Backup** | file_path, status, backup_at, restore_at | — | — |
| **BankAccount** | bank_name, account_name, account_number, type, balance | ✅ | Branch, Transactions |
| **BankReconciliation** | status, opening/closing/cleared balance, difference | — | BankAccount, Statement, Matches |
| **BankReconciliationMatch** | matched_amount | — | Reconciliation, StatementLine, Transaction |
| **BankStatement** | statement_date, opening/closing balance | — | BankAccount, Lines, Reconciliations |
| **BankStatementLine** | transaction_date, type, amount, balance, is_matched | — | Statement |
| **BankTransaction** | transaction_date, type, amount, reference_no | — | BankAccount, Creator |
| **Branch** | code, name, tin, address, is_main | — | CompanyProfile, Accounts |
| **CompanyProfile** | name, tin, address, software/db version, developer info | — | Branches |
| **Customer** | code, name, tin, address, email, phone | ✅ | Branch |
| **EInvoiceTransmission** | provider, status, reference_number, payloads, transmitted_at | — | Invoice |
| **Employee** | employee_no, name, position, department, rate, is_active | ✅ | Branch |
| **InventoryItem** | sku, name, unit, quantity_on_hand, reorder_level | ✅ | Branch, Movements |
| **InventoryMovement** | movement_date, type, quantity, unit_cost | — | InventoryItem, Creator |
| **Invoice** | invoice_number, control_number, type, status, amounts | — | Branch, Customer, Supplier, JournalEntry, Lines, Receipts, Transmissions |
| **InvoiceLine** | description, quantity, unit_price, line_total | — | Invoice |
| **JournalEntry** | entry_number, control_number, journal_type, status, totals | — | Branch, Creator, Approver, Lines |
| **JournalEntryLine** | debit, credit, particulars | — | JournalEntry, Account, Customer, Supplier |
| **Ledger** | posting_date, debit, credit, running_balance, control_number | — | Account |
| **PayrollPeriod** | name, start_date, end_date, pay_date, status | — | PayrollRuns |
| **PayrollRun** | run_number, status, gross/deduction/net totals | — | Period, Lines, Creator, Approver |
| **PayrollRunLine** | gross_amount, deduction_amount, net_amount, breakdown | — | PayrollRun, Employee |
| **PurchaseOrder** | order_number, status, amounts, received_at, billed_at | ✅ | Branch, Supplier, Invoice, Lines, Creator |
| **PurchaseOrderLine** | description, quantity, received_quantity, unit_price | — | PurchaseOrder |
| **ReportRun** | report_type, reference_number, date range, page_count | — | — |
| **SalesOrder** | order_number, status, amounts, confirmed_at | ✅ | Branch, Customer, Invoice, Lines, Creator |
| **SalesOrderLine** | description, quantity, unit_price, line_total | — | SalesOrder |
| **SalesReceipt** | receipt_number, amount, payment_method, reference_no | ✅ | Invoice, Branch, Customer, JournalEntry, Creator |
| **Supplier** | code, name, tin, address, email, phone | ✅ | Branch |
| **User** | name, email, role, branch_id | — | Branch, Spatie Roles |
| **UserLog** | activity, route, method, ip_address, occurred_at | — | User |

### Enums

| Enum | Values |
|------|--------|
| `AccountType` | asset, liability, equity, revenue, expense |
| `InvoiceType` | sales, service, purchase |
| `InvoiceStatus` | draft, issued, cancelled |
| `JournalType` | general, sales, purchase, cash_receipts, cash_disbursements |
| `JournalStatus` | draft, posted, reversed |
| `UserRole` | admin, accountant, auditor |

---

## 7. API Reference

All API endpoints are under the `/api/` prefix and require `web` + `auth` + `verified` middleware.

### Master Data APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/PUT/DELETE | `/api/accounts[/{id}]` | Chart of Accounts CRUD |
| GET/POST/PUT/DELETE | `/api/customers[/{id}]` | Customers CRUD |
| GET | `/api/customers/catalog` | Customer dropdown catalog |
| GET/POST/PUT/DELETE | `/api/suppliers[/{id}]` | Suppliers CRUD |
| GET | `/api/suppliers/catalog` | Supplier dropdown catalog |
| GET/POST/PUT/DELETE | `/api/branches[/{id}]` | Branches CRUD |
| GET/POST/PUT/DELETE | `/api/employees[/{id}]` | Employees CRUD |
| GET/POST/PUT/DELETE | `/api/inventory-items[/{id}]` | Inventory Items CRUD |
| GET/POST | `/api/inventory-movements` | Inventory Movements |

### Sales APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/sales-orders` | List sales orders (filterable by status) |
| POST | `/api/sales-orders` | Create draft sales order |
| PUT | `/api/sales-orders/{id}` | Update draft sales order |
| POST | `/api/sales-orders/{id}/confirm` | Confirm sales order |
| POST | `/api/sales-orders/{id}/convert-to-invoice` | Convert to sales invoice |
| DELETE | `/api/sales-orders/{id}` | Delete draft sales order |

### Purchase APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/purchase-orders` | List purchase orders |
| POST | `/api/purchase-orders` | Create draft purchase order |
| PUT | `/api/purchase-orders/{id}` | Update purchase order |
| POST | `/api/purchase-orders/{id}/receive` | Mark as received |
| POST | `/api/purchase-orders/{id}/convert-to-bill` | Convert to purchase bill |
| DELETE | `/api/purchase-orders/{id}` | Delete draft purchase order |

### Collections APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/collections/receipts` | List collection receipts |
| GET | `/api/collections/catalog` | Available invoices for collection |
| POST | `/api/collections/receipts` | Issue collection receipt |

### Payroll APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/payroll-periods` | List payroll periods |
| POST | `/api/payroll-periods` | Create payroll period |
| GET | `/api/payroll-runs` | List payroll runs |
| GET | `/api/payroll-runs/{id}` | Show run with lines |
| POST | `/api/payroll-runs/generate` | Generate payroll run |
| POST | `/api/payroll-runs/{id}/approve` | Approve run (maker-checker) |
| POST | `/api/payroll-runs/{id}/post` | Post run (closes period) |

### Banking APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/PUT/DELETE | `/api/bank-accounts[/{id}]` | Bank account CRUD |
| GET/POST/DELETE | `/api/bank-transactions[/{id}]` | Bank transactions |
| POST | `/api/banking/statements/import` | Import CSV statement |
| GET | `/api/banking/reconciliations` | List reconciliations |
| GET | `/api/banking/reconciliations/{id}` | Detail with suggestions |
| POST | `/api/banking/reconciliations/{id}/match` | Match line to transaction |
| POST | `/api/banking/reconciliations/{id}/tag-unmatched` | Tag unmatched reason |
| DELETE | `/api/banking/reconciliations/{id}/matches/{matchId}` | Unmatch |
| POST | `/api/banking/reconciliations/{id}/close` | Close reconciliation |
| POST | `/api/banking/reconciliations/{id}/reopen` | Reopen reconciliation |

### Journal Entry APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/journal-entries` | List journal entries |
| POST | `/api/journal-entries` | Create draft entry |
| GET | `/api/journal-entries/{id}` | Show entry with lines |
| POST | `/api/journal-entries/{id}/post` | Post entry (maker-checker) |
| POST | `/api/journal-entries/{id}/reverse` | Reverse posted entry |

### E-Invoicing APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/e-invoices` | List invoices |
| POST | `/api/e-invoices` | Create draft invoice |
| GET | `/api/e-invoices/{id}` | Show invoice |
| GET | `/api/e-invoices/{id}/print` | Download PDF |
| POST | `/api/e-invoices/{id}/issue` | Issue invoice |
| POST | `/api/e-invoices/{id}/cancel` | Cancel invoice |
| POST | `/api/e-invoices/{id}/transmit` | Transmit to BIR |

### Report APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports/trial-balance` | Trial Balance |
| GET | `/api/reports/balance-sheet` | Balance Sheet |
| GET | `/api/reports/income-statement` | Income Statement |
| GET | `/api/reports/journal-book` | Journal Book |
| GET | `/api/reports/general-ledger-book` | General Ledger Book |
| GET | `/api/reports/accounts-receivable-ledger` | AR Ledger |
| GET | `/api/reports/accounts-payable-ledger` | AP Ledger |
| GET | `/api/reports/customer-ledger` | Customer Ledger |
| GET | `/api/reports/supplier-ledger` | Supplier Ledger |

**Query Parameters:** `from_date`, `to_date`, `branch_id`, `format` (json/pdf/excel), `customer_id`, `supplier_id`

### Administration APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/system-info` | System info + company profile |
| PUT | `/api/system-info/company-profile` | Update company profile |
| GET/POST | `/api/system-users` | User management |
| GET | `/api/system-users/catalog` | User dropdown catalog |
| GET | `/api/audit-logs` | Audit log entries |
| GET | `/api/user-activity-logs` | User activity logs |
| GET | `/api/access/modules` | Permission module list |
| GET | `/api/access/catalog` | Users/roles catalog |
| GET | `/api/access/users/{id}` | User's current access |
| POST | `/api/access/users/{id}/assign` | Assign roles/permissions |
| GET/POST | `/api/backups[/{id}/restore]` | Backup management |
| GET | `/api/exports/{module}` | Excel export (17 modules) |

---

## 8. Business Rules & Validations

### Double-Entry Accounting
- **Balanced Entries:** Total debits must equal total credits (validated to 2 decimal places)
- **Immutability:** Posted journal entries cannot be edited or deleted — only reversed
- **Maker-Checker:** The user posting a journal entry cannot be the same user who created it
- **Reversal Chain:** Reversing a posted entry creates a new entry with debits↔credits swapped and marks the original as `reversed`

### Invoice Lifecycle
- **Status Gates:** Issue requires draft; Cancel requires issued; Transmit requires issued
- **Auto-Computation:** `line_total = quantity × unit_price`; `subtotal = Σ(line_total)`; `total = subtotal + vat_amount`
- **Locking:** `issued_at` and `locked_at` set on issuance

### Payroll Processing
- **One Draft Per Period:** Only one draft run allowed per payroll period
- **Period Locking:** Period is locked for update during run generation
- **Maker-Checker:** Approver cannot be the run creator
- **Period Close:** Posting a run closes the payroll period
- **Proration:** `(days_in_period / 30) × monthly_rate`
- **Deduction Caps:** SSS ₱1,125; PhilHealth ₱2,500; Pag-IBIG ₱100

### Bank Reconciliation
- **Matching Rules:** Transaction type must match; amounts must be equal to 2 decimal places
- **No Double-Match:** A statement line can only be matched once
- **Close Threshold:** Reconciliation can only be closed when `|difference| ≤ ₱0.01`
- **Difference Formula:** `clearing_balance = opening + Σ(signed_matched_amounts)`; `difference = closing - cleared`

### Order Processing
- **Sales Orders:** Only draft orders can be edited/deleted; confirmation is irreversible; invoice conversion is idempotent
- **Purchase Orders:** Only draft orders can be deleted; receiving sets all line `received_quantity = quantity`; bill conversion uses received quantities

### Backup & Restore
- **Destructive Restore:** Restore operation truncates target tables before re-inserting all rows
- **Default Scope:** `accounts`, `journal_entries`, `journal_entry_lines`, `ledgers`

### Access Control
- **Default Admin Protection:** `admin@cas.local` always retains the Admin role regardless of permission changes
- **Permission Format:** `{module}.{action}` (e.g., `journals.post`, `e_invoices.transmit`)

---

## 9. Events & Listeners

### JournalEntryPosted Event

| Property | Type | Description |
|----------|------|-------------|
| `journalEntry` | `JournalEntry` (with lines) | The posted journal entry |

**Fired when:** A journal entry transitions from `draft` to `posted` via `JournalEntryService::post()`

### PostJournalEntryToLedger Listener

**Handles:** `JournalEntryPosted`

**Actions:**
1. Calls `LedgerRepositoryInterface::postJournalEntry()` — creates Ledger records for each journal entry line
2. Creates an `AuditLog` record with event `posted`

### AuditObserver

**Registered on:** Account, Customer, Supplier, JournalEntry, Invoice

**Events tracked:**

| Eloquent Event | AuditLog Event | Data Captured |
|----------------|---------------|---------------|
| `created` | `create` | New attributes |
| `updated` | `update` | Old values → changed values |
| `deleted` | `delete` | Original values |

Each record includes: `user_id`, `ip_address`, `user_agent`, `occurred_at`

---

## 10. Permissions Matrix

The system defines **21 modules** with granular actions. Permissions are formatted as `{module}.{action}`.

| Module | view | create | update | delete | Other Actions |
|--------|:----:|:------:|:------:|:------:|---------------|
| **accounts** | ✅ | ✅ | ✅ | ✅ | |
| **customers** | ✅ | ✅ | ✅ | ✅ | |
| **suppliers** | ✅ | ✅ | ✅ | ✅ | |
| **branches** | ✅ | ✅ | ✅ | ✅ | |
| **inventory** | ✅ | ✅ | ✅ | ✅ | |
| **sales** | ✅ | ✅ | ✅ | ✅ | |
| **collections** | ✅ | ✅ | | | |
| **purchases** | ✅ | ✅ | ✅ | ✅ | |
| **hr** | ✅ | ✅ | ✅ | ✅ | |
| **payroll** | ✅ | ✅ | ✅ | | |
| **banking** | ✅ | ✅ | ✅ | ✅ | |
| **journals** | ✅ | ✅ | | | `post`, `reverse` |
| **reports** | ✅ | | | | `export` |
| **ledgers** | ✅ | | | | |
| **backups** | ✅ | ✅ | | | `restore` |
| **system_info** | ✅ | | ✅ | | |
| **e_invoices** | ✅ | ✅ | | | `issue`, `cancel`, `transmit` |
| **users** | ✅ | ✅ | ✅ | | |
| **audit_trail** | ✅ | | | | |
| **user_access** | ✅ | | | | `assign` |

### Role Default Capabilities

| Capability | Admin | Accountant | Auditor |
|------------|:-----:|:----------:|:-------:|
| Full system access | ✅ | | |
| Create/post journals | ✅ | ✅ | |
| View all financial data | ✅ | ✅ | ✅ |
| Manage users | ✅ | | |
| Backup/restore | ✅ | | |
| Delete accounts | ✅ | | |
| Delete posted journals | ❌ | ❌ | ❌ |

---

## 11. Frontend Architecture

### Page Structure

Each CAS module page follows a consistent pattern:

```vue
<script setup lang="ts">
// Imports: Inertia Head, Vue reactivity, composables, utils
// Props: Inertia page props (breadcrumbs, typed data)
// State: reactive({}) for all page state
// Composables: useAuthPermissions(), useCasApi(), useStateNotifications()
// API Functions: CRUD operations via useCasApi()
// Lifecycle: onMounted → load initial data
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Module Name" />
    <div class="space-y-6">
      <SectionCard title="Create Form">...</SectionCard>
      <SectionCard title="Data Table">
        <!-- Search/Filter/Export controls -->
        <!-- Paginated table with inline actions -->
        <!-- Pagination controls -->
      </SectionCard>
    </div>
  </AppLayout>
</template>
```

### Composables (10)

| Composable | Purpose |
|------------|---------|
| `useAuthPermissions` | Permission checking via `can('module.action')` |
| `useCasApi` | JSON API wrapper (GET/POST/PUT/DELETE + CSRF) |
| `useStateNotifications` | Auto-toast on `state.error`/`state.success` changes |
| `useNotifications` | Global toast notification system |
| `useQueryTabSync` | Two-way URL query ↔ active tab sync |
| `useAppearance` | Light/dark/system theme management |
| `useCurrentUrl` | Reactive current URL tracking |
| `useInitials` | Name → initials extraction |
| `useTwoFactorAuth` | 2FA setup flow (QR, manual key, recovery codes) |
| `useUiPermissions` | Extended permission checking with UI overrides |

### Shared Utilities (`lib/utils.ts`)

| Function | Purpose |
|----------|---------|
| `cn(...inputs)` | Tailwind class merging (clsx + tailwind-merge) |
| `toUrl(href)` | Normalize Inertia link href |
| `formatPhDateOnly(value)` | Format date to `MM/DD/YYYY` Philippine timezone |
| `formatPhDateTime(value)` | Format date+time to `MM/DD/YYYY, HH:MM AM/PM` Philippine timezone |
| `formatAmount(value)` | Format number with commas and 2 decimal places (`Intl.NumberFormat('en-PH')`) |

### 21 CAS Pages

| Page | Module |
|------|--------|
| `Dashboard.vue` | KPI dashboard with Chart.js cash flow chart |
| `Accounts.vue` | Chart of Accounts |
| `Branches.vue` | Branch management |
| `Customers.vue` | Customer master |
| `Suppliers.vue` | Supplier master |
| `HR.vue` | Employee master |
| `Inventory.vue` | Items + Movements (tabbed) |
| `Sales.vue` | Sales order lifecycle |
| `Purchases.vue` | Purchase order lifecycle |
| `Collections.vue` | Collection receipts |
| `Payroll.vue` | Payroll periods, runs, lines (tabbed) |
| `Banking.vue` | Accounts, transactions, statements, reconciliation (tabbed) |
| `Journals.vue` | Journal entries |
| `EInvoicing.vue` | E-Invoice management |
| `Ledgers.vue` | Subsidiary ledger viewer |
| `Reports.vue` | Financial report generator |
| `Users.vue` | User management |
| `UserAccess.vue` | Permission assignment |
| `SystemInfo.vue` | Company profile |
| `Backups.vue` | Backup & restore |
| `AuditTrail.vue` | Audit logs & activity |

---

## 12. Deployment & Configuration

### Requirements

- PHP ≥ 8.2 with extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML, ZIP, GD
- MySQL 8.0+
- Node.js ≥ 18 (for frontend build)
- Composer 2.x

### Environment Variables

Key `.env` settings:

```env
APP_NAME="BIR Compliance Accounting System"
APP_ENV=production
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cas_db
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp          # For email verification & password reset
FORTIFY_FEATURES=emailVerification,twoFactorAuthentication
```

### Build & Deployment

```bash
# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Database setup
php artisan migrate --force
php artisan db:seed --class=CasPermissionSeeder    # Seed permissions
php artisan db:seed --class=CasDefaultAdminSeeder   # Create default admin

# Caching (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
```

### Default Admin Credentials

| Field | Value |
|-------|-------|
| Email | `admin@cas.local` |
| Role | Admin |
| Password | (set during seeding) |

> **Note:** The default admin email is protected — it always retains the Admin role even if permissions are modified.

---

*This documentation covers the complete BIR Compliance Accounting System as of February 2026.*
