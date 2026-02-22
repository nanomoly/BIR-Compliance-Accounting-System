# Standard CAS Architecture (BIR-Focused)

## Overview
This implementation follows Clean Architecture boundaries with Laravel service classes:

- **Controllers**: Thin orchestration only (`app/Http/Controllers/Api`)
- **Form Requests**: Input validation and authorization (`app/Http/Requests/Api`)
- **Services**: Business rules and accounting workflows (`app/Services/Accounting`, `app/Services/Reports`)
- **Repositories**: Data persistence abstraction (`app/Repositories/Contracts`, `app/Repositories/Eloquent`)
- **DTOs**: Strongly typed transport objects (`app/DTOs`)
- **Events/Listeners**: Posting side-effects (`JournalEntryPosted` -> `PostJournalEntryToLedger`)

## Key Compliance Decisions
1. **Immutability after posting**
   - Posted entries are locked (`posted_at`, `locked_at`, status)
   - Posted entries cannot be deleted (policy + model guard)
   - Corrections require reversal entries (`reverse` flow)

2. **Double-entry integrity**
   - Debit and credit totals are validated before save
   - Posting and persistence are wrapped in DB transactions

3. **Auditability**
   - Audit trail records model create/update/delete in `audit_logs`
   - User API activity is logged in `user_logs`
   - Report generation is logged in `report_runs` with reference number and page count

4. **BIR report readiness**
   - Date-range report filters (monthly/quarterly/annually via date ranges)
   - Export formats: JSON/PDF/Excel
   - Printable report header includes company TIN and address
   - Report reference number is system-generated

5. **Multi-branch support**
   - Branch relation across accounts, journals, ledgers, and report runs

## SOLID + Clean Code Notes
- Interfaces are used for repositories and bound via IoC container.
- Services depend on abstractions, not concrete database implementations.
- Validation and authorization concerns are separated from business logic.
- Events keep posting side-effects decoupled from transaction workflows.
