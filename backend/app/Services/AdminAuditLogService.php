<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AdminAuditLogService
{
    /**
     * Record an admin/auditable action without breaking the main flow if audit persistence fails.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        ?Authenticatable $actor = null,
        ?Model $target = null,
        array $context = [],
        string $category = 'admin'
    ): void {
        try {
            $request = request();

            AuditLog::query()->create([
                'actor_user_id' => $actor?->getAuthIdentifier(),
                'category' => $category,
                'action' => $action,
                'target_type' => $target ? $target::class : null,
                'target_id' => $target?->getKey(),
                'context' => $context === [] ? null : $context,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('audit_log.write_failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
