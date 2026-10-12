<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SubscriptionQuotaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use LaravelDaily\FilaTeams\Facades\FilaTeams;
use LaravelDaily\FilaTeams\Models\Team;
use LaravelDaily\FilaTeams\Models\TeamInvitation;
use LaravelDaily\FilaTeams\Notifications\TeamInvitationNotification;
use LaravelDaily\FilaTeams\Rules\UniqueTeamInvitation;

class InvitationsManager extends TableWidget
{
    public int $teamId;

    protected static bool $isDiscovered = false;

    public function getTeam(): Team
    {
        return Team::findOrFail($this->teamId);
    }

    public function table(Table $table): Table
    {
        $team = $this->getTeam();
        $user = Auth::user();

        return $table
            ->query(
                TeamInvitation::query()
                    ->where('team_id', $this->teamId)
                    ->whereNull('accepted_at')
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->with('inviter')
            )
            ->heading(__('filateams::filateams.tables.invitations.heading'))
            ->headerActions([
                Action::make('invite')
                    ->label(__('filateams::filateams.actions.invite_member.label'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('filateams::filateams.fields.email_address.label'))
                            ->email()
                            ->required()
                            ->rules([new UniqueTeamInvitation($team)]),
                        Select::make('role')
                            ->label(__('filateams::filateams.fields.role.label'))
                            ->options(collect(FilaTeams::assignableRoles())->pluck('label', 'value'))
                            ->default(FilaTeams::defaultRole()->value)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($team, $user): void {
                        $quotaService = app(SubscriptionQuotaService::class);
                        $owner = $team->owner ?? $user;

                        if (! $quotaService->canAddTeamMember($team, $owner)) {
                            Notification::make()
                                ->danger()
                                ->title(__('Limite de collaborateurs atteinte'))
                                ->body(__('Votre forfait actuel ne vous permet pas d\'inviter d\'autres membres. Veuillez passer à l\'offre Pro ou Équipe pour agrandir votre équipe.'))
                                ->send();

                            return;
                        }

                        $invitation = TeamInvitation::create([
                            'team_id' => $team->id,
                            'email' => $data['email'],
                            'role' => $data['role'],
                            'invited_by' => $user->id,
                            'expires_at' => now()->addDays(config('filateams.invitation.expires_after_days', 7)),
                        ]);

                        NotificationFacade::route('mail', $data['email'])
                            ->notify(new TeamInvitationNotification($invitation));

                        Notification::make()
                            ->success()
                            ->title(__('filateams::filateams.notifications.invitation_sent.title', ['email' => $data['email']]))
                            ->send();
                    })

                    ->visible(fn () => $user->hasTeamPermission($team, 'invitation:create')),
            ])
            ->columns([
                TextColumn::make('email')
                    ->label(__('filateams::filateams.fields.email.label'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('filateams::filateams.fields.role.label'))
                    ->badge(),
                TextColumn::make('inviter.name')
                    ->label(__('filateams::filateams.fields.invited_by.label')),
                TextColumn::make('expires_at')
                    ->label(__('filateams::filateams.fields.expires.label'))
                    ->dateTime(),
            ])
            ->actions([
                Action::make('cancel')
                    ->label(__('filateams::filateams.actions.cancel_invitation.label'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (TeamInvitation $record): void {
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title(__('filateams::filateams.notifications.invitation_cancelled.title'))
                            ->send();
                    })
                    ->visible(fn () => $user->hasTeamPermission($team, 'invitation:cancel')),
            ])
            ->emptyStateHeading(__('filateams::filateams.tables.invitations.empty_state.heading'))
            ->emptyStateDescription(__('filateams::filateams.tables.invitations.empty_state.description'))
            ->paginated(false);
    }
}
