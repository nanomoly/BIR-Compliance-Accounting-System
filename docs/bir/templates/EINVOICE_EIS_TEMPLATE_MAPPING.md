# E-Invoice EIS Template Mapping (System Baseline)

This document maps the system's transmitted payload to a practical BIR EIS-style structure.

## Current Transmit Payload Shape

- `document_type`
- `invoice_number`
- `control_number`
- `invoice_date`
- `due_date`
- `currency`
- `seller`:
  - `name`
  - `tin`
  - `address`
  - `branch_code`
  - `branch_name`
- `buyer`:
  - `name`
  - `tin`
  - `address`
  - `email`
- `line_items[]`:
  - `description`
  - `quantity`
  - `unit_price`
  - `line_total`
- `totals`:
  - `subtotal`
  - `vat_amount`
  - `grand_total`
- `remarks`

## Source Fields in System

- Seller data: `company_profiles`, `branches`
- Buyer data: `customers` / `suppliers`
- Invoice headers: `invoices`
- Invoice lines: `invoice_lines`

## Note on "Latest Official Template"

The exact latest BIR EIS technical file (portal-distributed template/schema) must still be verified against your enrolled taxpayer profile and current BIR release package.

Once you provide that official file, this mapping can be finalized field-by-field (including strict code lists, mandatory flags, and exact naming conventions).
