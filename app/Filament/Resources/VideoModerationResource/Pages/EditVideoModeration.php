<?php

namespace App\Filament\Resources\VideoModerationResource\Pages;

use App\Filament\Resources\VideoModerationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVideoModeration extends EditRecord
{
    protected static string $resource = VideoModerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
