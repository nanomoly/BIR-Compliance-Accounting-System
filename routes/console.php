<?php

use App\Models\CompanyProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bir:package {--company=} {--tin=} {--address=}', function (): void {
    $timestamp = now()->format('Ymd_His');
    $basePath = 'bir-package/'.$timestamp;
    $evidencePath = $basePath.'/evidence';
    $templatesOutputPath = $basePath.'/templates';

    Storage::disk('local')->makeDirectory($evidencePath);
    Storage::disk('local')->makeDirectory($templatesOutputPath);

    $companyProfile = CompanyProfile::query()->first();

    $companyName = $this->option('company') ?: $companyProfile?->name ?: 'N/A';
    $tin = $this->option('tin') ?: $companyProfile?->tin ?: 'N/A';
    $address = $this->option('address') ?: $companyProfile?->registered_address ?: 'N/A';

    $summary = [
        'generated_at' => now()->toIso8601String(),
        'company_name' => $companyName,
        'tin' => $tin,
        'address' => $address,
        'software_version' => $companyProfile?->software_version ?? config('app.version', '1.0.0'),
        'database_version' => $companyProfile?->database_version ?? 'N/A',
        'developer' => $companyProfile?->developer_name ?? 'N/A',
        'included_templates' => [
            'APPLICATION_LETTER_TEMPLATE.md',
            'SYSTEM_DESCRIPTION_TEMPLATE.md',
            'SWORN_STATEMENT_TEMPLATE.md',
            'SOP_BACKUP_RESTORE_TEMPLATE.md',
            'SOP_CHANGE_MANAGEMENT_TEMPLATE.md',
            'UAT_TEST_SCRIPT_TEMPLATE.md',
            'SAMPLE_REPORTS_REQUIRED.md',
            'EVIDENCE_MANIFEST_TEMPLATE.csv',
        ],
    ];

    Storage::disk('local')->put(
        $basePath.'/PACKAGE_SUMMARY.json',
        json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    );

    $checklist = implode(PHP_EOL, [
        '# BIR Registration Package Checklist',
        '',
        '- [ ] Application Letter',
        '- [ ] System Description',
        '- [ ] Sworn Statement (Notarized)',
        '- [ ] Sample Books/Reports (PDF + Excel)',
        '- [ ] UAT Test Results (Signed)',
        '- [ ] Backup & Restore Evidence',
        '- [ ] Audit Trail/User Log Evidence',
        '- [ ] Version / System Information',
        '',
        'Generated: '.now()->toDateTimeString(),
    ]);

    Storage::disk('local')->put($basePath.'/CHECKLIST.md', $checklist);

    $templateFiles = [
        'APPLICATION_LETTER_TEMPLATE.md',
        'SYSTEM_DESCRIPTION_TEMPLATE.md',
        'SWORN_STATEMENT_TEMPLATE.md',
        'SOP_BACKUP_RESTORE_TEMPLATE.md',
        'SOP_CHANGE_MANAGEMENT_TEMPLATE.md',
        'UAT_TEST_SCRIPT_TEMPLATE.md',
        'SAMPLE_REPORTS_REQUIRED.md',
        'EVIDENCE_MANIFEST_TEMPLATE.csv',
    ];

    foreach ($templateFiles as $templateFile) {
        $source = base_path('docs/bir/templates/'.$templateFile);
        if (is_file($source)) {
            $content = file_get_contents($source);
            if ($content !== false) {
                Storage::disk('local')->put($templatesOutputPath.'/'.$templateFile, $content);
            }
        }
    }

    $sampleReportPath = 'reports/sample_trial_balance_output.json';
    if (Storage::disk('local')->exists($sampleReportPath)) {
        Storage::disk('local')->copy($sampleReportPath, $evidencePath.'/sample_trial_balance_output.json');
    }

    $this->info('BIR package generated at: storage/app/private/'.$basePath);
});
