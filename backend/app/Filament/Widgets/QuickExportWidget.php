<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickExportWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.quick-export-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;
}
