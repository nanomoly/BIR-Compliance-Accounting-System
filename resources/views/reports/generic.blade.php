<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .header { margin-bottom: 16px; }
        .header h2, .header p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px; text-align: left; }
        .meta { margin: 8px 0 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $company?->name ?? 'Company' }}</h2>
        <p>TIN: {{ $company?->tin ?? 'N/A' }}</p>
        <p>Address: {{ $company?->registered_address ?? 'N/A' }}</p>
        <p>{{ $title }}</p>
    </div>

    <div class="meta">
        <p>Report Ref #: {{ $referenceNumber }}</p>
        <p>Page 1 of {{ $pageCount }}</p>
    </div>

    @if (!empty($rows))
        <table>
            <thead>
                <tr>
                    @foreach (array_keys($rows[0]) as $head)
                        <th>{{ str_replace('_', ' ', strtoupper($head)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No records found for selected period.</p>
    @endif
</body>
</html>
