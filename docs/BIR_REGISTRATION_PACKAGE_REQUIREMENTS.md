# BIR CAS Registration Package Requirements (Implementation-Aligned)

## 1) Registration Package Checklist

| Requirement | Purpose | Where in this project | Owner | Status |
|---|---|---|---|---|
| Application / transmittal letter | Formal filing cover | `docs/bir/templates/APPLICATION_LETTER_TEMPLATE.md` | Tax/Compliance | Pending fill-up |
| System description document | Describe CAS modules and controls | `docs/CAS_ARCHITECTURE.md` + `docs/bir/templates/SYSTEM_DESCRIPTION_TEMPLATE.md` | Technical Lead | Draft ready |
| Sworn statement / declaration | Legal declaration of integrity | `docs/bir/templates/SWORN_STATEMENT_TEMPLATE.md` | Authorized Officer | Pending notarization |
| Books and reports samples | Validate BIR print layout and content | API + exports in CAS, sample list in `docs/bir/templates/SAMPLE_REPORTS_REQUIRED.md` | Accounting + QA | In progress |
| User access matrix (RBAC) | Segregation of duties | Roles in `users.role`, policies in `app/Policies` | Security/Admin | Draft ready |
| Audit trail evidence | Traceability and immutability | `audit_logs`, `user_logs`, posting lock | QA/Audit | In progress |
| Backup and restore procedure | Data protection control | `app/Services/Accounting/BackupService.php`, SOP template | Infra/DBA | In progress |
| UAT and test scripts | Demonstrate expected behavior | `docs/bir/templates/UAT_TEST_SCRIPT_TEMPLATE.md` | QA | Pending execution |
| Change management SOP | Controlled software updates | `docs/bir/templates/SOP_CHANGE_MANAGEMENT_TEMPLATE.md` | Engineering Lead | Pending sign-off |
| Version/system information | Software + DB + developer metadata | `/api/system-info` + CAS system page | Technical Lead | Ready |

## 2) How to generate a submission folder

Run:

`php artisan bir:package --company="Your Company" --tin="000-000-000-000" --address="Your Address"`

Output folder:

`storage/app/private/bir-package/YYYYMMDD_HHMMSS/`

Contents include:
- `PACKAGE_SUMMARY.json`
- `CHECKLIST.md`
- `evidence/sample_trial_balance_output.json` (if available)

## 3) Final pre-filing actions

1. Fill all templates under `docs/bir/templates` with company-specific details.
2. Export all required books/reports for representative periods (monthly/quarterly/annual).
3. Print and verify BIR format consistency (headers, TIN, pagination, reference numbers).
4. Execute UAT and archive signed test evidence.
5. Attach notarized sworn statement and any BIR office-specific annexes.
