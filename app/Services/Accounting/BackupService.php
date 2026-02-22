<?php

namespace App\Services\Accounting;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    /**
     * @param  array<int, string>  $tables
     */
    public function create(int $userId, array $tables = ['accounts', 'journal_entries', 'journal_entry_lines', 'ledgers']): Backup
    {
        $snapshot = [];

        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->get()->toArray();
        }

        $filename = 'backups/cas-backup-'.now()->format('YmdHis').'.json';
        Storage::disk('local')->put($filename, json_encode($snapshot, JSON_PRETTY_PRINT));

        return Backup::query()->create([
            'requested_by' => $userId,
            'file_path' => $filename,
            'status' => 'completed',
            'backup_at' => now(),
        ]);
    }

    public function restore(int $backupId): Backup
    {
        $backup = Backup::query()->findOrFail($backupId);

        if (! Storage::disk('local')->exists($backup->file_path)) {
            throw new RuntimeException('Backup file not found.');
        }

        $content = Storage::disk('local')->get($backup->file_path);
        /** @var array<string, array<int, object>> $data */
        $data = json_decode($content, false, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data): void {
            foreach ($data as $table => $rows) {
                DB::table($table)->truncate();
                foreach ($rows as $row) {
                    DB::table($table)->insert((array) $row);
                }
            }
        });

        $backup->restore_at = now();
        $backup->save();

        return $backup;
    }
}
