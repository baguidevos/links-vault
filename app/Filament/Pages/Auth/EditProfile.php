<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditProfile extends BaseEditProfile
{
    protected Width|string|null $maxContentWidth = Width::Full;

    protected Width|string|null $maxWidth = Width::Full;

    public function getMaxWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Mon Profil & Paramètres';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Mettez à jour vos informations personnelles, votre langue d\'affichage et la sécurité de votre compte.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['locale'] = filled($data['locale'] ?? null) ? $data['locale'] : 'fr';
        $data['timezone'] = filled($data['timezone'] ?? null) ? $data['timezone'] : 'Europe/Paris';

        return $data;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])->schema([
                    Group::make([
                        Section::make('Informations Personnelles')
                            ->description('Vos coordonnées et préférences générales d\'utilisation de LinksVault.')
                            ->icon(TablerIcon::User)
                            ->schema([
                                Grid::make(2)->schema([
                                    $this->getNameFormComponent()
                                        ->label('Nom complet')
                                        ->prefixIcon(TablerIcon::User),

                                    $this->getEmailFormComponent()
                                        ->label('Adresse email')
                                        ->prefixIcon(TablerIcon::Mail),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('locale')
                                        ->label('Langue de l\'interface')
                                        ->prefixIcon(TablerIcon::Language)
                                        ->options([
                                            'fr' => 'Français 🇫🇷',
                                            'en' => 'English 🇬🇧',
                                        ])
                                        ->required()
                                        ->default('fr')
                                        ->native(false),

                                    Select::make('timezone')
                                        ->label('Fuseau horaire')
                                        ->prefixIcon(TablerIcon::Clock)
                                        ->options([
                                            'Europe/Paris' => 'Europe/Paris (UTC+1/+2)',
                                            'Europe/London' => 'Europe/London (UTC+0/+1)',
                                            'America/New_York' => 'America/New_York (EST)',
                                            'America/Chicago' => 'America/Chicago (CST)',
                                            'America/Los_Angeles' => 'America/Los_Angeles (PST)',
                                            'Africa/Casablanca' => 'Africa/Casablanca',
                                            'Africa/Dakar' => 'Africa/Dakar',
                                            'UTC' => 'UTC (Temps universel)',
                                        ])
                                        ->required()
                                        ->default('Europe/Paris')
                                        ->searchable()
                                        ->native(false),
                                ]),
                            ]),

                        Section::make('Sécurité & Mot de passe')
                            ->description('Modifiez votre mot de passe pour garantir la protection de vos espaces et liens.')
                            ->icon(TablerIcon::Lock)
                            ->schema([
                                $this->getCurrentPasswordFormComponent()
                                    ->label('Mot de passe actuel')
                                    ->helperText('Requis pour valider un changement d\'email ou de mot de passe.')
                                    ->prefixIcon(TablerIcon::Key),

                                Grid::make(2)->schema([
                                    $this->getPasswordFormComponent()
                                        ->label('Nouveau mot de passe')
                                        ->prefixIcon(TablerIcon::Lock),

                                    $this->getPasswordConfirmationFormComponent()
                                        ->label('Confirmer le mot de passe')
                                        ->prefixIcon(TablerIcon::LockCheck),
                                ]),
                            ]),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),

                    Group::make([
                        Section::make('Détails du Compte')
                            ->description('Statut et informations d\'adhésion.')
                            ->icon(TablerIcon::IdBadge2)
                            ->schema([
                                Placeholder::make('user_avatar_summary')
                                    ->label('Identité')
                                    ->content(fn () => new HtmlString(
                                        '<div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">'.
                                        '<div class="w-10 h-10 rounded-full bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-base">'.
                                        strtoupper(substr($this->getUser()->name, 0, 2)).
                                        '</div>'.
                                        '<div><div class="font-semibold text-sm text-gray-900 dark:text-gray-100">'.e($this->getUser()->name).'</div>'.
                                        '<div class="text-xs text-gray-500 dark:text-gray-400">'.e($this->getUser()->email).'</div></div>'.
                                        '</div>'
                                    )),

                                Placeholder::make('role_badge')
                                    ->label('Rôle sur la plateforme')
                                    ->content(fn () => new HtmlString(
                                        $this->getUser()->is_admin
                                            ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-danger-50 text-danger-700 dark:bg-danger-950 dark:text-danger-400">🛡️ Administrateur Global</span>'
                                            : '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-400">👤 Utilisateur Membre</span>'
                                    )),

                                Placeholder::make('member_since')
                                    ->label('Date d\'inscription')
                                    ->content(fn () => $this->getUser()->created_at?->translatedFormat('d F Y (H:i)') ?? 'N/A'),
                            ]),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),
                ]),
            ]);
    }
}
