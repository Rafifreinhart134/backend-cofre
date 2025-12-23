<?php

namespace App\Filament\Resources\VideoModerationResource\Pages;

use App\Filament\Resources\VideoModerationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideoModerations extends ListRecords
{
    protected static string $resource = VideoModerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
