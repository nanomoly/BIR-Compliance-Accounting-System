# Full Accounting System Backlog (Execution Blueprint)

## Goal
Build from current CAS MVP into a complete accounting system with strong controls, traceability, and PH compliance readiness.

---

## Priority Legend
- **P0** = Critical foundation (must-have)
- **P1** = High value (needed for production maturity)
- **P2** = Optimization and scale

---

## EPIC 1 (P0): Inventory Ledger & Costing

### Scope
- Inventory item master enhancements
- Stock movement ledger (in/out/adjustment/transfer)
- Costing method support (start with **moving average**, optionally FIFO in phase 2)
- Inventory valuation reports

### Database
- `inventory_movements`
  - `inventory_item_id`, `movement_date`, `movement_type`, `qty_in`, `qty_out`, `unit_cost`, `reference_type`, `reference_id`, `created_by`
- `inventory_item_cost_layers` (if FIFO)

### API
- `GET /api/inventory-movements`
- `POST /api/inventory-movements`
- `POST /api/inventory-movements/adjust`
- `GET /api/reports/inventory-valuation`

### UI
- Inventory movements page
- Adjustment form with approval flag
- Item stock card view
- Inventory valuation report with export

### Acceptance Criteria
- Every stock change creates immutable movement row
- On-hand quantity always equals movement aggregation
- Valuation report reconciles with inventory GL control account

---

## EPIC 2 (P0): Sales Lifecycle (Order to Cash)

### Scope
- Sales Order (SO)
- Delivery/fulfillment state
- Invoice generation from SO
- Receipt posting
- AR balance tracking

### Database
- `sales_orders`, `sales_order_lines`
- `sales_receipts`
- linkage fields on invoice tables

### API
- `GET/POST/PUT /api/sales-orders`
- `POST /api/sales-orders/{id}/confirm`
- `POST /api/sales-orders/{id}/invoice`
- `POST /api/sales-receipts`

### UI
- Sales Orders page
- SO details + line items
- Convert SO to invoice action
- Receipt posting modal

### Acceptance Criteria
- SO to invoice conversion preserves quantities and totals
- Receipts reduce AR and post corresponding journal entries
- AR aging updates from receipts and credit notes

---

## EPIC 3 (P0): Purchase Lifecycle (Procure to Pay)

### Scope
- Purchase Requisition / Purchase Order
- Goods receipt tracking
- Supplier bill posting
- Payment processing
- AP tracking and aging

### Database
- `purchase_orders`, `purchase_order_lines`
- `goods_receipts`, `goods_receipt_lines`
- `supplier_bills`, `supplier_payments`

### API
- `GET/POST/PUT /api/purchase-orders`
- `POST /api/purchase-orders/{id}/receive`
- `POST /api/purchase-orders/{id}/bill`
- `POST /api/supplier-payments`

### UI
- PO list/details
- Goods receipt form
- Bill-from-PO action
- Supplier payment form

### Acceptance Criteria
- 3-way status visibility (PO, receipt, bill)
- AP ledger reflects bills/payments exactly
- Over-billing/over-receipt controls enforce tolerance

---

## EPIC 4 (P0): Payroll Engine (HR to GL)

### Scope
- Payroll period setup
- Earnings/deductions templates
- Employee payroll run generation
- Payroll posting to journals

### Database
- `payroll_periods`
- `payroll_runs`
- `payroll_run_lines`
- `payroll_components`

### API
- `GET/POST /api/payroll-periods`
- `POST /api/payroll-runs/generate`
- `POST /api/payroll-runs/{id}/approve`
- `POST /api/payroll-runs/{id}/post`

### UI
- Payroll periods page
- Payroll run generator
- Payroll register review
- Post-to-GL action

### Acceptance Criteria
- Payroll run totals equal posted GL entries
- Approved runs are immutable except reversal flow
- Employee-level breakdown traceable and exportable

---

## EPIC 5 (P0): Bank Reconciliation

### Scope
- Bank statement import (CSV)
- Match internal transactions to statement lines
- Unmatched handling and adjustment entries
- Reconciliation close per period/account

### Database
- `bank_statements`
- `bank_statement_lines`
- `bank_reconciliations`
- `bank_reconciliation_matches`

### API
- `POST /api/banking/statements/import`
- `GET /api/banking/reconciliation`
- `POST /api/banking/reconciliation/{id}/match`
- `POST /api/banking/reconciliation/{id}/close`

### UI
- Statement upload screen
- Reconciliation workbench (matched/unmatched)
- Close reconciliation action

### Acceptance Criteria
- Closing balance reconciliation must equal statement ending balance
- Closed reconciliation cannot be edited without reopen permission
- All unmatched lines are explicitly tagged with reason

---

## EPIC 6 (P1): Period-End Closing & Controls

### Scope
- Fiscal periods table
- Close/reopen process
- Posting locks by date/period
- Adjusting entry workflow

### Acceptance Criteria
- No posting allowed to closed period
- Reopen is role-restricted and audited
- Period checklist completion required before close

---

## EPIC 7 (P1): Approval Workflow (Maker-Checker)

### Scope
- Approval policies for critical documents (SO/PO/Bills/Payroll/Journals)
- Pending approvals queue
- Approve/reject with reason

### Acceptance Criteria
- User cannot approve own transaction (configurable)
- Approval trail retained and exportable

---

## EPIC 8 (P1): Tax and PH Reporting Hardening

### Scope
- VAT schedule reports
- Expanded withholding reports
- BIR evidence package generator improvements

### Acceptance Criteria
- Reports reproducible by period/branch
- Output aligns with required filing templates

---

## EPIC 9 (P2): Fixed Assets

### Scope
- Asset register
- Depreciation schedules
- Disposal and gain/loss recognition

---

## EPIC 10 (P2): Advanced Analytics & Forecasting

### Scope
- AR/AP aging dashboard
- Cash flow projection
- Branch profitability and trend analytics

---

## Cross-Cutting Requirements (All Epics)

### Security
- RBAC enforced in API + UI
- MFA support for admin roles
- Full audit logs for create/update/delete/approve/post actions

### Data Integrity
- DB transactions for multi-step posting
- Idempotency keys for financial mutations
- Validation for balance checks and duplicate prevention

### Compliance Evidence
- UAT scripts per epic
- SOP updates per process
- Export samples and signed validation logs

### Performance
- Add indexes on all foreign keys/date fields used in reports
- Paginate all list endpoints
- Async exports for large result sets (future queue job)

---

## Suggested Delivery Plan

### Wave 1 (8–10 weeks)
1. EPIC 1 Inventory Ledger & Costing
2. EPIC 2 Sales Lifecycle
3. EPIC 3 Purchase Lifecycle
4. EPIC 5 Bank Reconciliation (MVP)

### Wave 2 (6–8 weeks)
1. EPIC 4 Payroll Engine
2. EPIC 6 Period-End Closing
3. EPIC 7 Approval Workflow

### Wave 3 (4–6 weeks)
1. EPIC 8 Tax Hardening
2. EPIC 9 Fixed Assets
3. EPIC 10 Analytics

---

## Definition of Done (System-Level)
- Transactions flow from source documents to journals and ledgers with traceability
- Period lock and approval controls are enforced
- AR/AP, inventory, payroll, and banking reconcile with GL
- Compliance documents and UAT evidence are complete and signed
