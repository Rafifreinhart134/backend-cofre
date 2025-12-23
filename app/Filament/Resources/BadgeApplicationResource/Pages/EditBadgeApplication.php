<?php

namespace App\Filament\Resources\BadgeApplicationResource\Pages;

use App\Filament\Resources\BadgeApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBadgeApplication extends EditRecord
{
    protected static string $resource = BadgeApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
