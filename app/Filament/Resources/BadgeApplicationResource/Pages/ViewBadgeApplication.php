<?php

namespace App\Filament\Resources\BadgeApplicationResource\Pages;

use App\Filament\Resources\BadgeApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBadgeApplication extends ViewRecord
{
    protected static string $resource = BadgeApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
