<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BadgeApplicationResource\Pages;
use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class BadgeApplicationResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Badge Applications';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Badge Application';

    protected static ?string $pluralModelLabel = 'Badge Applications';

    public static function getEloquentQuery(): Builder
    {
        // Only show users who have applied for badges (badge_status is not null)
        return parent::getEloquentQuery()->whereNotNull('badge_status');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\ImageEntry::make('avatar_url')
                            ->label('Avatar')
                            ->disk('public')
                            ->height(100),
                    ])->columns(2),

                Forms\Components\Section::make('Application Details')
                    ->schema([
                        Forms\Components\Textarea::make('badge_application_reason')
                            ->label('Why they want to be a creator')
                            ->disabled()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('badge_is_culinary_creator')
                            ->label('Is Culinary Creator')
                            ->disabled(),
                        Forms\Components\Placeholder::make('badge_applied_at')
                            ->label('Application Date')
                            ->content(fn ($record) => $record->badge_applied_at?->format('d M Y, H:i')),
                    ])->columns(2),

                Forms\Components\Section::make('Review')
                    ->schema([
                        Forms\Components\Select::make('badge_status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('badge_rejection_reason')
                            ->label('Rejection Reason')
                            ->visible(fn ($get) => $get('badge_status') === 'rejected')
                            ->required(fn ($get) => $get('badge_status') === 'rejected')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->disk('public'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('badge_application_reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->wrap(),
                Tables\Columns\IconColumn::make('badge_is_culinary_creator')
                    ->label('Culinary')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('badge_applied_at')
                    ->label('Applied At')
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('badge_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('badge_status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
                Tables\Filters\TernaryFilter::make('badge_is_culinary_creator')
                    ->label('Culinary Creator')
                    ->placeholder('All applicants')
                    ->trueLabel('Yes')
                    ->falseLabel('No'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Badge Application')
                    ->modalDescription('Are you sure you want to approve this badge application?')
                    ->visible(fn ($record) => $record->badge_status === 'pending')
                    ->action(function (User $record) {
                        $record->update([
                            'badge_status' => 'approved',
                            'account_type' => 'creator',
                            'show_badge' => true, // Ensure badge is visible when approved
                        ]);

                        // Send push notification
                        try {
                            $pushService = app(PushNotificationService::class);
                            $pushService->sendToUser(
                                $record,
                                'Badge Approved!',
                                'Selamat! Permohonan badge creator Anda telah disetujui.',
                                ['type' => 'badge_approved']
                            );
                        } catch (\Exception $e) {
                            \Log::error('Failed to send badge approval notification: ' . $e->getMessage());
                        }

                        Notification::make()
                            ->title('Badge application approved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Badge Application')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for rejecting this application...'),
                    ])
                    ->visible(fn ($record) => $record->badge_status === 'pending')
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'badge_status' => 'rejected',
                            'badge_rejection_reason' => $data['rejection_reason'],
                        ]);

                        // Send push notification
                        try {
                            $pushService = app(PushNotificationService::class);
                            $pushService->sendToUser(
                                $record,
                                'Badge Application Rejected',
                                'Permohonan badge creator Anda ditolak. Alasan: ' . $data['rejection_reason'],
                                [
                                    'type' => 'badge_rejected',
                                    'reason' => $data['rejection_reason']
                                ]
                            );
                        } catch (\Exception $e) {
                            \Log::error('Failed to send badge rejection notification: ' . $e->getMessage());
                        }

                        Notification::make()
                            ->title('Badge application rejected')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('badge_applied_at', 'desc');
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
            'index' => Pages\ListBadgeApplications::route('/'),
            'view' => Pages\ViewBadgeApplication::route('/{record}'),
            'edit' => Pages\EditBadgeApplication::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('badge_status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
