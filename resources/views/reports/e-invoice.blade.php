<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper($invoice->invoice_type->value) }} {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h2, h3, p { margin: 0; }
        .section { margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #111; padding: 6px; text-align: left; }
        .right { text-align: right; }
        .grid-2 { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid-2 td { width: 50%; vertical-align: top; border: 1px solid #111; padding: 6px; }
        .label { font-size: 10px; color: #333; }
        .value { margin-top: 2px; font-weight: 600; }
        .note { margin-top: 8px; font-size: 10px; }
    </style>
</head>
<body>
    <h2>{{ $company?->name ?? 'Company' }}</h2>
    <p>TIN: {{ $company?->tin ?? 'N/A' }}</p>
    <p>Address: {{ $company?->registered_address ?? 'N/A' }}</p>

    <div class="section">
        <h3>
            @if ($invoice->invoice_type->value === 'purchase')
                PURCHASE INVOICE
            @elseif ($invoice->invoice_type->value === 'service')
                SERVICE INVOICE
            @else
                SALES INVOICE
            @endif
        </h3>
        <p>Invoice No.: {{ $invoice->invoice_number }}</p>
        <p>Control No.: {{ $invoice->control_number }}</p>
        <p>Status: {{ strtoupper($invoice->status->value) }}</p>
        <p>Invoice Date: {{ optional($invoice->invoice_date)->format('Y-m-d') }}</p>
        <p>Due Date: {{ optional($invoice->due_date)->format('Y-m-d') ?? 'N/A' }}</p>
        <p>Transmission Ref.: {{ optional($invoice->transmissions->sortByDesc('id')->first())->reference_number ?? 'N/A' }}</p>
        <p>PTU/ATP Ref.: N/A</p>
    </div>

    <table class="grid-2">
        <tr>
            <td>
                <p class="label">SELLER NAME</p>
                <p class="value">{{ $company?->name ?? 'N/A' }}</p>
                <p class="label">SELLER TIN</p>
                <p class="value">{{ $company?->tin ?? 'N/A' }}</p>
                <p class="label">SELLER ADDRESS</p>
                <p class="value">{{ $company?->registered_address ?? 'N/A' }}</p>
                <p class="label">BRANCH</p>
                <p class="value">{{ $invoice->branch?->code ?? 'N/A' }} - {{ $invoice->branch?->name ?? 'N/A' }}</p>
            </td>
            <td>
                <p class="label">BUYER/SUPPLIER NAME</p>
                <p class="value">{{ $invoice->customer?->name ?? $invoice->supplier?->name ?? 'N/A' }}</p>
                <p class="label">BUYER/SUPPLIER TIN</p>
                <p class="value">{{ $invoice->customer?->tin ?? $invoice->supplier?->tin ?? 'N/A' }}</p>
                <p class="label">BUYER/SUPPLIER ADDRESS</p>
                <p class="value">{{ $invoice->customer?->address ?? $invoice->supplier?->address ?? 'N/A' }}</p>
                <p class="label">BUYER/SUPPLIER EMAIL</p>
                <p class="value">{{ $invoice->customer?->email ?? $invoice->supplier?->email ?? 'N/A' }}</p>
            </td>
        </tr>
    </table>

    <div class="section">
        <p><strong>Terms:</strong> {{ $invoice->due_date ? 'Due on '.optional($invoice->due_date)->format('Y-m-d') : 'N/A' }}</p>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="right">{{ number_format((float) $line->quantity, 2) }}</td>
                        <td class="right">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="right">{{ number_format((float) $line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <table>
            <tbody>
                <tr>
                    <td>Vatable Sales</td>
                    <td class="right">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td>VAT-Exempt Sales</td>
                    <td class="right">0.00</td>
                </tr>
                <tr>
                    <td>Zero-Rated Sales</td>
                    <td class="right">0.00</td>
                </tr>
                <tr>
                    <td>VAT Amount</td>
                    <td class="right">{{ number_format((float) $invoice->vat_amount, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Total Amount Due</strong></td>
                    <td class="right"><strong>{{ number_format((float) $invoice->total_amount, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <p>Remarks: {{ $invoice->remarks ?? 'N/A' }}</p>
        <p class="note">Compliance Note: This layout is system-aligned to BIR e-invoice style fields and must be finalized against the officially issued Annex template version for your enrolled taxpayer profile.</p>
    </div>
</body>
</html>
