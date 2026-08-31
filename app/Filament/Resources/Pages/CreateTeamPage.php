<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use App\Actions\TeamActions\CreateTeam;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LaravelDaily\FilaTeams\Rules\TeamName;

class CreateTeamPage extends RegisterTenant
{
    protected static ?string $slug = 'new';

    public static function getLabel(): string
    {
        return __('filateams::filateams.pages.create_team.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filateams::filateams.fields.team_name.label'))
                    ->required()
                    ->maxLength(255)
                    ->rules([new TeamName])
                    ->autofocus(),
            ]);
    }

    public function hasLogo(): bool
    {
        return true;
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function getCancelFormAction(): Action
    {
        $user = Auth::user();
        $tenant = $user?->latestTeam ?? $user?->teams()->first();
        $url = $tenant ? Filament::getUrl($tenant) : url('/app');

        return Action::make('cancel')
            ->label(__('Retour'))
            ->icon('heroicon-m-arrow-left')
            ->color('gray')
            ->outlined()
            ->url($url);
    }

    protected function handleRegistration(array $data): Model
    {
        return app(CreateTeam::class)->handle(Auth::user(), $data);
    }
}
