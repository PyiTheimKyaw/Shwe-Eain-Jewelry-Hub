<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class CommandCenterStripWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 8;

    protected string $view = 'filament.widgets.command-center-strip';

    protected int|string|array $columnSpan = 'full';
}
