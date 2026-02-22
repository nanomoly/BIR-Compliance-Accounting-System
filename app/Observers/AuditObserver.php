<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->log('create', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->log('update', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->log('delete', $model, $model->getOriginal(), null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function log(string $event, Model $model, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => (int) $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'occurred_at' => now(),
        ]);
    }
}
