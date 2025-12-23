<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Report;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->chart([7, 12, 15, 18, 22, 25, 28]),

            Stat::make('Total Videos', Video::count())
                ->description('Uploaded videos')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('primary')
                ->chart([10, 15, 12, 20, 18, 25, 30]),

            Stat::make('Total Comments', Comment::count())
                ->description('User comments')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart([5, 8, 10, 12, 15, 18, 20]),

            Stat::make('Pending Reports', Report::where('status', 'pending')->count())
                ->description('Need review')
                ->descriptionIcon('heroicon-m-flag')
                ->color('warning')
                ->chart([2, 3, 1, 4, 2, 5, 3]),
        ];
    }
}
