<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\CloudBackup;
use App\Models\CloudStorageConfig;
use App\Services\CloudBackup\CloudStorageManager;
use App\Services\CloudBackup\Connectors\S3CompatibleConnector;
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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    protected static ?string $navigationLabel = 'Sauvegardes Cloud';

    protected static ?string $title = 'Sauvegardes Cloud & Restauration';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.manage-cloud-backups';

    public function getHeading(): string|Htmlable
    {
        return 'Sauvegardes Cloud & Restauration';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Protégez vos liens, dossiers et résumés IA grâce aux sauvegardes automatiques sur Google Drive, AWS S3, Cloudflare R2, Dropbox et stockage local.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure_s3')
                ->label('Configurer S3 / R2')
                ->icon(TablerIcon::BrandAmazon)
                ->color('gray')
                ->slideOver()
                ->modalWidth('lg')
                ->fillForm(function (): array {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $config = CloudStorageConfig::where('team_id', $team?->id)
                        ->where('provider', 's3')
                        ->first();

                    $creds = $config?->credentials ?? [];

                    return [
                        'is_active' => $config?->is_active ?? false,
                        'auto_backup_enabled' => $config?->auto_backup_enabled ?? false,
                        'frequency' => $config?->frequency ?? 'weekly',
                        'retention_count' => $config?->retention_count ?? 10,
                        'key' => $creds['key'] ?? '',
                        'secret' => $creds['secret'] ?? '',
                        'bucket' => $creds['bucket'] ?? '',
                        'region' => $creds['region'] ?? 'auto',
                        'endpoint' => $creds['endpoint'] ?? '',
                        'prefix' => $creds['prefix'] ?? 'linksvault-backups/',
                        'use_path_style_endpoint' => $creds['use_path_style_endpoint'] ?? false,
                    ];
                })
                ->form([
                    Toggle::make('is_active')
                        ->label('Activer le stockage S3 / R2')
                        ->helperText('Permet d\'envoyer les sauvegardes vers ce stockage.')
                        ->default(true),

                    Toggle::make('auto_backup_enabled')
                        ->label('Sauvegardes automatiques programmées')
                        ->helperText('Exécute automatiquement la sauvegarde selon la fréquence choisie.'),

                    Select::make('frequency')
                        ->label('Fréquence de sauvegarde')
                        ->options([
                            'daily' => 'Quotidienne (Tous les jours à 03h00)',
                            'weekly' => 'Hebdomadaire (Tous les lundis)',
                            'monthly' => 'Mensuelle (Le 1er du mois)',
                        ])
                        ->default('weekly'),

                    TextInput::make('retention_count')
                        ->label('Nombre de sauvegardes à conserver')
                        ->numeric()
                        ->default(10)
                        ->helperText('Les sauvegardes plus anciennes seront automatiquement purgées du bucket.'),

                    Section::make('Identifiants Cloud S3 / Cloudflare R2 / MinIO')
                        ->schema([
                            TextInput::make('key')
                                ->label('Access Key ID')
                                ->required()
                                ->password()
                                ->revealable(),

                            TextInput::make('secret')
                                ->label('Secret Access Key')
                                ->required()
                                ->password()
                                ->revealable(),

                            TextInput::make('bucket')
                                ->label('Nom du Bucket')
                                ->required()
                                ->placeholder('Ex: mon-vault-backups'),

                            TextInput::make('region')
                                ->label('Région')
                                ->default('auto')
                                ->placeholder('Ex: us-east-1, eu-west-1, auto'),

                            TextInput::make('endpoint')
                                ->label('Endpoint URL (Optionnel pour Cloudflare R2 / MinIO / Wasabi)')
                                ->placeholder('Ex: https://<account_id>.r2.cloudflarestorage.com')
                                ->url(),

                            TextInput::make('prefix')
                                ->label('Dossier dans le bucket')
                                ->default('linksvault-backups/'),

                            Checkbox::make('use_path_style_endpoint')
                                ->label('Utiliser Path Style Endpoint (requis pour MinIO)'),
                        ]),
                ])
                ->extraModalFooterActions(fn (Action $action): array => [
                    Action::make('test_s3')
                        ->label('Tester la connexion')
                        ->icon(TablerIcon::Plug)
                        ->color('info')
                        ->action(function (array $data) {
                            $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                            $connector = new S3CompatibleConnector((int) $team?->id, [
                                'key' => $data['key'] ?? '',
                                'secret' => $data['secret'] ?? '',
                                'bucket' => $data['bucket'] ?? '',
                                'region' => $data['region'] ?? 'auto',
                                'endpoint' => $data['endpoint'] ?? '',
                                'use_path_style_endpoint' => ! empty($data['use_path_style_endpoint']),
                                'prefix' => $data['prefix'] ?? '',
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
                        'provider' => 's3',
                    ], [
                        'is_active' => ! empty($data['is_active']),
                        'auto_backup_enabled' => ! empty($data['auto_backup_enabled']),
                        'frequency' => $data['frequency'] ?? 'weekly',
                        'retention_count' => (int) ($data['retention_count'] ?? 10),
                        'credentials' => [
                            'key' => $data['key'] ?? '',
                            'secret' => $data['secret'] ?? '',
                            'bucket' => $data['bucket'] ?? '',
                            'region' => $data['region'] ?? 'auto',
                            'endpoint' => $data['endpoint'] ?? '',
                            'prefix' => $data['prefix'] ?? 'linksvault-backups/',
                            'use_path_style_endpoint' => ! empty($data['use_path_style_endpoint']),
                        ],
                    ]);

                    Notification::make()
                        ->title('Configuration S3 / R2 enregistrée avec succès !')
                        ->success()
                        ->send();
                }),

            Action::make('configure_dropbox')
                ->label('Configurer Dropbox')
                ->icon(TablerIcon::BrandDropbox)
                ->color('gray')
                ->slideOver()
                ->modalWidth('md')
                ->fillForm(function (): array {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    $config = CloudStorageConfig::where('team_id', $team?->id)
                        ->where('provider', 'dropbox')
                        ->first();

                    return [
                        'is_active' => $config?->is_active ?? false,
                        'access_token' => $config?->credentials['access_token'] ?? '',
                        'folder' => $config?->credentials['folder'] ?? '/LinksVault_Backups',
                    ];
                })
                ->form([
                    Toggle::make('is_active')
                        ->label('Activer le stockage Dropbox')
                        ->default(true),

                    TextInput::make('access_token')
                        ->label('Jeton d\'accès (Access Token / App Token)')
                        ->required()
                        ->password()
                        ->revealable()
                        ->helperText('Générez un Access Token depuis la console Dropbox Developers.'),

                    TextInput::make('folder')
                        ->label('Dossier distant')
                        ->default('/LinksVault_Backups'),
                ])
                ->action(function (array $data): void {
                    $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                    CloudStorageConfig::updateOrCreate([
                        'team_id' => $team?->id,
                        'provider' => 'dropbox',
                    ], [
                        'is_active' => ! empty($data['is_active']),
                        'credentials' => [
                            'access_token' => $data['access_token'] ?? '',
                            'folder' => $data['folder'] ?? '/LinksVault_Backups',
                        ],
                    ]);

                    Notification::make()
                        ->title('Configuration Dropbox enregistrée avec succès !')
                        ->success()
                        ->send();
                }),

            Action::make('restore_zip_file')
                ->label('Restaurer depuis un fichier .zip')
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
                            'all' => 'Tous les stockages actifs configurés',
                            'local' => 'Stockage local uniquement (Téléchargeable)',
                            's3' => 'AWS S3 / Cloudflare R2',
                            'google_drive' => 'Google Drive',
                            'dropbox' => 'Dropbox',
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
                        $failCount = count($backups) - $successCount;

                        if ($successCount > 0) {
                            Notification::make()
                                ->title('Sauvegarde générée avec succès !')
                                ->body("{$successCount} archive(s) envoyée(s) vers le cloud. Vous pouvez les télécharger ci-dessous.")
                                ->success()
                                ->send();
                        } else {
                            $firstError = $backups[0]->error_message ?? 'Erreur inconnue';
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
                    ->label('Fournisseur')
                    ->badge()
                    ->formatStateUsing(fn (CloudBackup $record) => $record->provider_label)
                    ->color(fn (CloudBackup $record) => $record->provider_color),

                TextColumn::make('filename')
                    ->label('Fichier Archive')
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
                                    ->title('Impossible de récupérer le fichier depuis le stockage distant.')
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
                        ->label('Restaurer ce Vault')
                        ->icon(TablerIcon::Restore)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmer la restauration du Vault')
                        ->modalDescription('Cette action va importer l\'intégralité des liens, dossiers et tags contenus dans cette archive.')
                        ->form([
                            Checkbox::make('overwrite')
                                ->label('Écraser / Mettre à jour les liens déjà présents'),
                        ])
                        ->action(function (CloudBackup $record, array $data, CloudStorageManager $storageManager, VaultRestoreService $restoreService): void {
                            $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                            $user = Auth::user();
                            $connector = $storageManager->getConnector($team, $record->provider, $user);
                            $content = $connector->download($record->remote_path ?: $record->filename);

                            if (empty($content)) {
                                Notification::make()
                                    ->title('Fichier de sauvegarde introuvable sur le cloud.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $res = $restoreService->restoreFromZip($content, $team, $user, ! empty($data['overwrite']));

                                Notification::make()
                                    ->title('Restauration effectuée avec succès !')
                                    ->body("{$res['imported_links']} liens importés, {$res['folders_created']} dossiers créés.")
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Erreur lors de la restauration')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    DeleteAction::make()
                        ->label('Supprimer')
                        ->icon(TablerIcon::Trash)
                        ->before(function (CloudBackup $record, CloudStorageManager $storageManager): void {
                            try {
                                $team = Filament::getTenant() ?? Auth::user()->personalTeam();
                                $connector = $storageManager->getConnector($team, $record->provider, Auth::user());
                                if (! empty($record->remote_path)) {
                                    $connector->delete($record->remote_path);
                                }
                            } catch (Throwable) {
                                // Ignorer les erreurs de suppression distante
                            }
                        }),
                ])
                    ->icon(TablerIcon::DotsVertical)
                    ->color('gray')
                    ->tooltip('Actions'),
            ])
            ->emptyStateHeading('Aucune sauvegarde Cloud enregistrée')
            ->emptyStateDescription('Cliquez sur « 🚀 Sauvegarder maintenant » pour créer votre première archive sécurisée.')
            ->emptyStateIcon(TablerIcon::CloudUpload);
    }
}
