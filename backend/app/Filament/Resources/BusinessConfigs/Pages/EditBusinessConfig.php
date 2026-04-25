<?php

namespace App\Filament\Resources\BusinessConfigs\Pages;

use App\Filament\Resources\BusinessConfigs\BusinessConfigResource;
use App\Services\AdminAuditLogService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBusinessConfig extends EditRecord
{
    protected static string $resource = BusinessConfigResource::class;

    protected function afterSave(): void
    {
        app(AdminAuditLogService::class)->log(
            action: 'admin.business_config.updated',
            actor: Auth::user(),
            target: $this->record,
            category: 'admin',
            context: [
                'default_deposit_percentage' => (float) $this->record->default_deposit_percentage,
                'payment_at_risk_hours' => (int) $this->record->payment_at_risk_hours,
                'order_at_risk_hours' => (int) $this->record->order_at_risk_hours,
                'currency_refresh_interval_minutes' => (int) $this->record->currency_refresh_interval_minutes,
            ],
        );
    }
}
