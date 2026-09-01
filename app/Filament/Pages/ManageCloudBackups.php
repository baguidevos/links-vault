<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\CloudBackup;
use App\Models\CloudStorageConfig;
use App\Services\CloudBackup\CloudStorageManager;
use App\Services\CloudBackup\Connectors\GoogleDriveConnector;
use App\Services\CloudBackup\VaultBackupService;
use App\Services\CloudBackup\VaultRestoreService;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ManageCloudBackups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::CloudUpload;

    protected static ?string $navigationLabel = 'Sauvegardes & Google Drive';

    protected static ?string $title = 'Sauvegardes Google Drive & Stockage Local';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.manage-cloud-backups';

    public function getHeading(): string|Htmlable
    {
        return 'Sauvegardes Google Drive & Restauration';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Protégez l\'ensemble de vos liens, dossiers et métadonnées en automatisant vos sauvegardes sur Google Drive et sur stockage local.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure_google_drive')
                ->label('Configurer Google Drive')
                ->icon(TablerIcon::BrandGoogleDrive)
                ->color('primary')
                ->slideOver()
                ->modalWidth('lg')
                ->fillForm(function (): array {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $config = CloudStorageConfig::where('team_id', $team?->id)
                        ->where('provider', 'google_drive')
                        ->first();

                    $creds = $config?->credentials ?? [];

                    return [
                        'is_active' => $config?->is_active ?? true,
                        'auto_backup_enabled' => $config?->auto_backup_enabled ?? true,
                        'frequency' => $config?->frequency ?? 'daily',
                        'retention_count' => $config?->retention_count ?? 15,
                        'client_id' => $creds['client_id'] ?? config('services.google.client_id', ''),
                        'client_secret' => $creds['client_secret'] ?? config('services.google.client_secret', ''),
                        'refresh_token' => $creds['refresh_token'] ?? '',
                        'folder_name' => $creds['folder_name'] ?? 'LinksVault_Backups',
                    ];
                })
                ->form([
                    Toggle::make('is_active')
                        ->label('Activer la destination Google Drive')
                        ->helperText('Permet d\'envoyer automatiquement et manuellement les sauvegardes vers Google Drive.')
                        ->default(true),

                    Toggle::make('auto_backup_enabled')
                        ->label('Sauvegardes automatiques programmées')
                        ->helperText('Exécute automatiquement la sauvegarde selon la fréquence choisie.')
                        ->default(true),

                    Grid::make(2)->schema([
                        Select::make('frequency')
                            ->label('Fréquence de sauvegarde')
                            ->options([
                                'daily' => 'Quotidienne (Tous les jours à 03h00)',
                                'weekly' => 'Hebdomadaire (Tous les lundis)',
                                'monthly' => 'Mensuelle (Le 1er du mois)',
                            ])
                            ->default('daily'),

                        TextInput::make('retention_count')
                            ->label('Nombre de sauvegardes à conserver')
                            ->numeric()
                            ->default(15)
                            ->helperText('Les archives plus anciennes seront purgées automatiquement.'),
                    ]),

                    Section::make('Identifiants & Configuration Google Drive')
                        ->description('Configurez vos accès OAuth2 Google Drive ou utilisez le compte lié.')
                        ->icon(TablerIcon::Key)
                        ->schema([
                            TextInput::make('folder_name')
                                ->label('Nom du dossier racine sur Google Drive')
                                ->default('LinksVault_Backups')
                                ->helperText('Le dossier sera créé automatiquement s\'il n\'existe pas encore.')
                                ->required(),

                            TextInput::make('client_id')
                                ->label('Google Client ID')
                                ->placeholder('123456789-abc.apps.googleusercontent.com')
                                ->helperText('Laissé vide pour utiliser la configuration globale du projet.'),

                            TextInput::make('client_secret')
                                ->label('Google Client Secret')
                                ->password()
                                ->revealable(),

                            TextInput::make('refresh_token')
                                ->label('Refresh Token OAuth2 (Optionnel)')
                                ->password()
                                ->revealable()
                                ->helperText('Permet à LinksVault de renouveler les accès automatiquement.'),
                        ]),
                ])
                ->extraModalFooterActions(fn (Action $action): array => [
                    Action::make('test_gdrive')
                        ->label('Tester la connexion Drive')
                        ->icon(TablerIcon::Plug)
                        ->color('info')
                        ->action(function (array $data) {
                            $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                            $connector = new GoogleDriveConnector((int) $team?->id, Auth::user(), [
                                'client_id' => $data['client_id'] ?? '',
                                'client_secret' => $data['client_secret'] ?? '',
                                'refresh_token' => $data['refresh_token'] ?? '',
                                'folder_name' => $data['folder_name'] ?? 'LinksVault_Backups',
                            ]);

                            $res = $connector->testConnection();
                            if ($res['success']) {
                                Notification::make()->title($res['message'])->success()->send();
                            } else {
                                Notification::make()->title($res['message'])->danger()->send();
                            }
                        }),
                ])
                ->action(function (array $data): void {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    CloudStorageConfig::updateOrCreate([
                        'team_id' => $team?->id,
                        'provider' => 'google_drive',
                    ], [
                        'is_active' => ! empty($data['is_active']),
                        'auto_backup_enabled' => ! empty($data['auto_backup_enabled']),
                        'frequency' => $data['frequency'] ?? 'daily',
                        'retention_count' => (int) ($data['retention_count'] ?? 15),
                        'credentials' => [
                            'client_id' => $data['client_id'] ?? '',
                            'client_secret' => $data['client_secret'] ?? '',
                            'refresh_token' => $data['refresh_token'] ?? '',
                            'folder_name' => $data['folder_name'] ?? 'LinksVault_Backups',
                        ],
                    ]);

                    Notification::make()
                        ->title('Configuration Google Drive enregistrée avec succès !')
                        ->success()
                        ->send();
                }),

            Action::make('configure_local')
                ->label('Sauvegarde Locale')
                ->icon(TablerIcon::DeviceFloppy)
                ->color('gray')
                ->slideOver()
                ->modalWidth('md')
                ->fillForm(function (): array {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $config = CloudStorageConfig::where('team_id', $team?->id)
                        ->where('provider', 'local')
                        ->first();

                    return [
                        'is_active' => $config?->is_active ?? true,
                        'auto_backup_enabled' => $config?->auto_backup_enabled ?? true,
                        'frequency' => $config?->frequency ?? 'weekly',
                        'retention_count' => $config?->retention_count ?? 10,
                    ];
                })
                ->form([
                    Toggle::make('is_active')
                        ->label('Activer la sauvegarde locale sur disque')
                        ->default(true),

                    Toggle::make('auto_backup_enabled')
                        ->label('Sauvegardes automatiques locales')
                        ->default(true),

                    Select::make('frequency')
                        ->label('Fréquence')
                        ->options([
                            'daily' => 'Quotidienne (03h00)',
                            'weekly' => 'Hebdomadaire',
                            'monthly' => 'Mensuelle',
                        ])
                        ->default('weekly'),

                    TextInput::make('retention_count')
                        ->label('Nombre de sauvegardes locales à conserver')
                        ->numeric()
                        ->default(10),
                ])
                ->action(function (array $data): void {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    CloudStorageConfig::updateOrCreate([
                        'team_id' => $team?->id,
                        'provider' => 'local',
                    ], [
                        'is_active' => ! empty($data['is_active']),
                        'auto_backup_enabled' => ! empty($data['auto_backup_enabled']),
                        'frequency' => $data['frequency'] ?? 'weekly',
                        'retention_count' => (int) ($data['retention_count'] ?? 10),
                        'credentials' => [],
                    ]);

                    Notification::make()
                        ->title('Configuration du stockage local enregistrée !')
                        ->success()
                        ->send();
                }),

            Action::make('restore_zip_file')
                ->label('Restaurer (.zip)')
                ->icon(TablerIcon::Restore)
                ->color('warning')
                ->form([
                    FileUpload::make('zip_file')
                        ->label('Archive ZIP LinksVault')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                        ->disk('local')
                        ->directory('temp-restores')
                        ->required()
                        ->helperText('Sélectionnez un fichier .zip de sauvegarde généré par LinksVault.'),

                    Checkbox::make('overwrite')
                        ->label('Écraser / Mettre à jour les liens existants s\'ils existent déjà'),
                ])
                ->action(function (array $data, VaultRestoreService $restoreService): void {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $user = Auth::user();

                    $filePath = $data['zip_file'];
                    $content = Storage::disk('local')->get($filePath);
                    Storage::disk('local')->delete($filePath);

                    if (empty($content)) {
                        Notification::make()->title('Fichier vide ou introuvable.')->danger()->send();

                        return;
                    }

                    try {
                        $res = $restoreService->restoreFromZip($content, $team, $user, ! empty($data['overwrite']));

                        Notification::make()
                            ->title('Restauration terminée avec succès !')
                            ->body("{$res['imported_links']} liens importés, {$res['folders_created']} dossiers créés, {$res['skipped_links']} doublons ignorés.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Échec de la restauration')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('create_backup_now')
                ->label('🚀 Sauvegarder maintenant')
                ->icon(TablerIcon::CloudUpload)
                ->color('primary')
                ->form([
                    Radio::make('destination')
                        ->label('Destination de la sauvegarde')
                        ->options([
                            'all' => 'Google Drive & Stockage Local',
                            'google_drive' => 'Google Drive uniquement',
                            'local' => 'Stockage local uniquement (Téléchargeable)',
                        ])
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data, VaultBackupService $backupService): void {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $user = Auth::user();

                    $provider = $data['destination'] === 'all' ? null : $data['destination'];

                    try {
                        $backups = $backupService->backupTeam($team, $provider, $user);

                        $successCount = count(array_filter($backups, fn ($b) => $b->status === 'completed'));

                        if ($successCount > 0) {
                            Notification::make()
                                ->title('Sauvegarde générée avec succès !')
                                ->body("{$successCount} archive(s) enregistrée(s) avec succès. Vous pouvez la télécharger ci-dessous.")
                                ->success()
                                ->send();
                        } else {
                            $firstError = $backups[0]->error_message ?? 'Erreur inconnue lors de l\'envoi vers le stockage.';
                            Notification::make()
                                ->title('Échec de la sauvegarde')
                                ->body($firstError)
                                ->danger()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors de la sauvegarde')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $team = Filament::getTenant() ?? Auth::user()?->personalTeam();
        $teamId = $team?->id ?? 0;

        return $table
            ->query(function () use ($teamId): Builder {
                return CloudBackup::query()
                    ->where('team_id', $teamId)
                    ->latest();
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold')
                    ->icon(TablerIcon::Calendar),

                TextColumn::make('provider')
                    ->label('Destination')
                    ->badge()
                    ->formatStateUsing(fn (CloudBackup $record) => $record->provider_label)
                    ->color(fn (CloudBackup $record) => $record->provider_color),

                TextColumn::make('filename')
                    ->label('Nom de l\'Archive')
                    ->searchable()
                    ->copyable()
                    ->icon(TablerIcon::FileZip),

                TextColumn::make('file_size')
                    ->label('Taille')
                    ->formatStateUsing(fn (CloudBackup $record) => $record->formatted_size)
                    ->sortable(),

                TextColumn::make('links_count')
                    ->label('Liens')
                    ->icon(TablerIcon::Link)
                    ->alignCenter(),

                TextColumn::make('folders_count')
                    ->label('Dossiers')
                    ->icon(TablerIcon::Folder)
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Réussie ✓',
                        'failed' => 'Échec ✗',
                        'in_progress' => 'En cours...',
                        default => $state,
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('download')
                        ->label('Télécharger (.zip)')
                        ->icon(TablerIcon::Download)
                        ->color('primary')
                        ->action(function (CloudBackup $record, CloudStorageManager $storageManager) {
                            $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                            $connector = $storageManager->getConnector($team, $record->provider, Auth::user());
                            $content = $connector->download($record->remote_path ?: $record->filename);

                            if (empty($content)) {
                                Notification::make()
                                    ->title('Impossible de récupérer le fichier depuis le stockage.')
                                    ->danger()
                                    ->send();

                                return null;
                            }

                            return response()->streamDownload(
                                fn () => print ($content),
                                $record->filename,
                                ['Content-Type' => 'application/zip']
                            );
                        }),

                    Action::make('restore_this')
                        ->label('Restaurer cette archive (1-clic)')
                        ->icon(TablerIcon::Restore)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(fn (CloudBackup $record) => "Restaurer le Vault depuis l'archive : {$record->filename}")
                        ->modalDescription('Cette action analysera le contenu du fichier .zip et restaurera l\'intégralité des liens, dossiers, catégories et tags.')
                        ->form([
                            Checkbox::make('overwrite')
                                ->label('Écraser / Mettre à jour les liens déjà existants'),
                        ])
                        ->action(function (CloudBackup $record, array $data, CloudStorageManager $storageManager, VaultRestoreService $restoreService): void {
                            $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                            $user = Auth::user();

                            try {
                                $connector = $storageManager->getConnector($team, $record->provider, $user);
                                $content = $connector->download($record->remote_path ?: $record->filename);

                                if (empty($content)) {
                                    throw new \RuntimeException('Le fichier archive est inaccessible ou vide.');
                                }

                                $res = $restoreService->restoreFromZip($content, $team, $user, ! empty($data['overwrite']));

                                Notification::make()
                                    ->title('Restauration 1-clic réussie !')
                                    ->body("{$res['imported_links']} liens restaurés, {$res['folders_created']} dossiers créés.")
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Échec de la restauration')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    DeleteAction::make()
                        ->label('Supprimer l\'archive')
                        ->before(function (CloudBackup $record, CloudStorageManager $storageManager) {
                            try {
                                $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                                $connector = $storageManager->getConnector($team, $record->provider, Auth::user());
                                if (! empty($record->remote_path)) {
                                    $connector->delete($record->remote_path);
                                }
                            } catch (Throwable) {
                                // Continue deleting record even if remote file is already removed
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Aucune sauvegarde pour le moment')
            ->emptyStateDescription('Cliquez sur "Sauvegarder maintenant" pour créer votre première archive Google Drive ou Stockage Local.')
            ->emptyStateIcon(TablerIcon::CloudUpload);
    }
}
