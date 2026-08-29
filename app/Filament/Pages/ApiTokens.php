<?php

namespace App\Filament\Pages;

use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Key;

    protected static ?string $navigationLabel = 'Clés API & Extension';

    protected static ?string $title = 'Gestion des Clés API';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.api-tokens';

    public ?string $newlyCreatedToken = null;

    public function getHeading(): string|Htmlable
    {
        return 'Clés API & Extension Chrome';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Générez et gérez vos clés d\'accès pour connecter l\'extension Google Chrome et d\'autres applications tierces à votre Vault.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_token')
                ->label('Générer une clé API')
                ->icon(TablerIcon::Plus)
                ->color('primary')
                ->form([
                    TextInput::make('name')
                        ->label('Nom de la clé')
                        ->placeholder('Ex: Extension Chrome, Laptop, Mobile...')
                        ->default('Extension Chrome')
                        ->required()
                        ->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $user = Auth::user();
                    $token = $user->createToken($data['name'])->plainTextToken;

                    $this->newlyCreatedToken = $token;

                    Notification::make()
                        ->title('Clé API créée avec succès')
                        ->body('Veuillez copier votre clé ci-dessous. Elle ne sera plus affichée après avoir quitté cette page.')
                        ->success()
                        ->duration(10000)
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return PersonalAccessToken::query()
                    ->where('tokenable_type', Auth::user()?->getMorphClass())
                    ->where('tokenable_id', Auth::id())
                    ->latest();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nom de la clé')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(TablerIcon::Key),

                TextColumn::make('last_used_at')
                    ->label('Dernière utilisation')
                    ->since()
                    ->placeholder('Jamais utilisée')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Révoquer')
                    ->icon(TablerIcon::Trash)
                    ->modalHeading('Révoquer cette clé API')
                    ->modalDescription('Êtes-vous sûr de vouloir révoquer cette clé ? Toute application (comme l\'extension Chrome) utilisant cette clé ne pourra plus se connecter.')
                    ->modalSubmitActionLabel('Oui, révoquer')
                    ->successNotificationTitle('Clé API révoquée avec succès'),
            ])
            ->emptyStateHeading('Aucune clé API active')
            ->emptyStateDescription('Cliquez sur « Générer une clé API » pour connecter votre extension Chrome.')
            ->emptyStateIcon(TablerIcon::Key);
    }
}
