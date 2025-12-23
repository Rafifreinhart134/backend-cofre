<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoModerationResource\Pages;
use App\Filament\Resources\VideoModerationResource\RelationManagers;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VideoModerationResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Content Moderation';

    protected static ?string $navigationLabel = 'Video Moderation';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Video';

    protected static ?string $pluralModelLabel = 'Videos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->disabled()
                    ->label('User ID'),
                Forms\Components\TextInput::make('s3_url')
                    ->disabled()
                    ->label('Video URL'),
                Forms\Components\TextInput::make('thumbnail_url')
                    ->disabled()
                    ->label('Thumbnail URL'),
                Forms\Components\Textarea::make('menu_data')
                    ->disabled()
                    ->label('Menu Data'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->disabled()
                    ->label('Status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Thumbnail')
                    ->size(100),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('menu_data')
                    ->label('Menu Data')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Video $record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()
                            ->success()
                            ->title('Video disetujui')
                            ->body('Video telah disetujui dan akan tampil di aplikasi.')
                            ->send();
                    })
                    ->visible(fn (Video $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Video $record) {
                        $record->update(['status' => 'rejected']);
                        Notification::make()
                            ->success()
                            ->title('Video ditolak')
                            ->body('Video telah ditolak dan tidak akan tampil di aplikasi.')
                            ->send();
                    })
                    ->visible(fn (Video $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoModerations::route('/'),
            'view' => Pages\ViewVideoModeration::route('/{record}'),
        ];
    }
}
