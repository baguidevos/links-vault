<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SyncSetting;
use App\Models\Team;
use App\Services\DesktopHotPatchService;
use App\Services\Sync\DesktopSyncService;
use BackedEnum;
use Carbon\Carbon;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\AutoUpdater;
use Throwable;

class DesktopSyncSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Refresh;

    protected static ?string $navigationLabel = 'Synchronisation Cloud';

    protected static ?string $title = 'Synchronisation Desktop & Web';

    protected static ?string $slug = 'settings/sync';

    protected static ?int $navigationSort = 85;

    protected string $view = 'filament.pages.desktop-sync-settings';

    public ?string $server_url = null;

    public ?string $api_token = null;

    public bool $auto_sync_enabled = true;

    public bool $verify_ssl = true;

    public ?string $last_synced_at = null;

    public ?string $sync_status = 'idle';

    public ?string $last_error = null;

    public bool $is_syncing = false;

    public ?string $newWebSyncToken = null;

    public bool $has_ever_synced = false;

    public bool $is_sync_active = false;

    public ?string $last_synced_diff = null;

    public int $connected_desktop_clients = 0;

    public string $current_server_url = '';

    public ?string $active_team_name = null;

    public string $desktop_app_version = '';

    public string $updater_provider = '';

    public bool $updater_enabled = true;

    /** @var array{version: string, releaseNotes?: string|null, downloaded_at?: string}|null */
    public ?array $update_downloaded = null;

    /** @var array{version: string, detected_at?: string}|null */
    public ?array $update_available = null;

    /** @var array{percent: int, transferred: int, total: int, humanTransferred: string, humanTotal: string, humanSpeed: string}|null */
    public ?array $download_progress = null;

    public ?string $last_update_check_at = null;

    public bool $is_checking_updates = false;

    public ?string $updater_error = null;

    public ?array $patch_available = null;

    public bool $patch_installed = false;

    public bool $is_downloading_patch = false;

    public function refreshDesktopProgress(): void
    {
        $this->download_progress = cache()->get('nativephp_download_progress');
        $this->update_downloaded = cache()->get('nativephp_update_downloaded');
        $this->update_available = cache()->get('nativephp_update_available');
        $this->patch_available = cache()->get('nativephp_patch_available');
        $this->patch_installed = (bool) cache()->get('nativephp_patch_installed', false);
        $this->updater_error = cache()->get('nativephp_updater_error');
    }

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $tenant = Filament::getTenant();
        $this->active_team_name = $tenant?->name;
        $this->current_server_url = config('app.url');

        $this->desktop_app_version = (string) (cache()->get('nativephp_installed_patch_version') ?: config('nativephp.version', '1.1.4'));
        $this->updater_provider = (string) config('nativephp.updater.default', 'github');
        $this->updater_enabled = (bool) config('nativephp.updater.enabled', true);
        $this->update_downloaded = cache()->get('nativephp_update_downloaded');
        $this->update_available = cache()->get('nativephp_update_available');
        $this->patch_available = cache()->get('nativephp_patch_available');
        $this->patch_installed = (bool) cache()->get('nativephp_patch_installed', false);
        $this->download_progress = cache()->get('nativephp_download_progress');
        $this->is_checking_updates = (bool) cache()->get('nativephp_checking_updates', false);
        $this->updater_error = cache()->get('nativephp_updater_error');
        $lastChecked = cache()->get('nativephp_last_checked_at');
        $this->last_update_check_at = $lastChecked ? Carbon::parse($lastChecked)->diffForHumans() : null;

        $tenantId = $tenant?->id ?? $user->current_team_id;
        $settings = SyncSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'team_id' => $tenantId,
                'auto_sync_enabled' => true,
            ]
        );

        $this->server_url = $settings->server_url;
        $this->api_token = $settings->api_token;
        $this->auto_sync_enabled = (bool) $settings->auto_sync_enabled;
        $this->verify_ssl = (bool) ($settings->verify_ssl ?? true);
        $this->sync_status = $settings->sync_status ?? 'idle';
        $this->last_error = $settings->last_error;

        // Récupérer les jetons d'accès Desktop générés pour cet utilisateur
        $desktopTokens = $user->tokens()
            ->where(function ($q) {
                $q->where('name', 'like', '%Desktop%')
                    ->orWhere('name', 'like', '%Sync%');
            })
            ->get();

        $this->connected_desktop_clients = $desktopTokens->count();

        // Récupérer la dernière utilisation d'un token ou last_synced_at
        $latestTokenUsage = $desktopTokens->whereNotNull('last_used_at')->sortByDesc('last_used_at')->first()?->last_used_at;
        $effectiveLastSynced = $settings->last_synced_at;

        if ($latestTokenUsage && (! $effectiveLastSynced || $latestTokenUsage->gt($effectiveLastSynced))) {
            $effectiveLastSynced = $latestTokenUsage;
        }

        $this->has_ever_synced = (bool) $effectiveLastSynced;
        $this->last_synced_at = $effectiveLastSynced?->format('d/m/Y H:i:s');
        $this->last_synced_diff = $effectiveLastSynced?->diffForHumans();

        // Une synchronisation est considérée active si un échange a eu lieu dans les 7 derniers jours
        $this->is_sync_active = (bool) ($effectiveLastSynced && $effectiveLastSynced->isAfter(now()->subDays(7)));
    }

    public function getHeading(): string|Htmlable
    {
        return 'Synchronisation Cloud & Desktop';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Connectez et synchronisez votre base de données locale Desktop avec votre espace LinksVault Web Cloud.';
    }

    public function saveSettings(): void
    {
        $this->validate([
            'server_url' => ['nullable', 'url'],
            'api_token' => ['nullable', 'string'],
            'auto_sync_enabled' => ['boolean'],
            'verify_ssl' => ['boolean'],
        ]);

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $settings = SyncSetting::firstOrCreate(['user_id' => $user->id]);
        $settings->update([
            'server_url' => $this->server_url,
            'api_token' => $this->api_token,
            'auto_sync_enabled' => $this->auto_sync_enabled,
            'verify_ssl' => $this->verify_ssl,
        ]);

        Notification::make()
            ->title('Paramètres enregistrés')
            ->body('La configuration de synchronisation a été mise à jour.')
            ->success()
            ->send();
    }

    public function syncNow(DesktopSyncService $syncService): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->is_syncing = true;
        $tenant = Filament::getTenant();
        $team = $tenant instanceof Team ? $tenant : ($tenant ? Team::find($tenant->id) : $user->teams()->first());

        $result = $syncService->sync($user, $team);
        $this->is_syncing = false;

        $this->mount(); // Recharger les états

        if ($result['success'] ?? false) {
            $msg = 'Les deltas ont été appliqués avec succès.';
            if (! empty($result['stats']['subscription'])) {
                $msg .= ' Abonnement Cloud synchronisé : Plan '.ucfirst((string) $result['stats']['subscription']).'.';
            }

            Notification::make()
                ->title('Synchronisation réussie !')
                ->body($msg)
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erreur de synchronisation')
                ->body($result['error'] ?? 'Une erreur est survenue.')
                ->danger()
                ->send();
        }
    }

    public function fixSslAndSyncNow(DesktopSyncService $syncService): void
    {
        $this->verify_ssl = false;
        $this->saveSettings();
        $this->syncNow($syncService);
    }

    public static function isDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', false)
            || request()->hasHeader('X-NativePHP-Secret')
            || str_contains((string) request()->userAgent(), 'Electron');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_web_token')
                ->label('Générer un jeton de synchro')
                ->icon(TablerIcon::Key)
                ->color('primary')
                ->hidden(fn () => static::isDesktop())
                ->action(function () {
                    $user = Auth::user();
                    if (! $user) {
                        return;
                    }

                    $token = $user->createToken('LinksVault Desktop Sync')->plainTextToken;
                    $this->newWebSyncToken = $token;

                    Notification::make()
                        ->title('Jeton de synchronisation généré')
                        ->body('Copiez ce jeton et collez-le dans votre application Desktop.')
                        ->success()
                        ->send();
                }),

            Action::make('connect_via_login')
                ->label('Connexion automatique (Email / MdP)')
                ->icon(TablerIcon::Login)
                ->color('gray')
                ->visible(fn () => static::isDesktop())
                ->form([
                    TextInput::make('modal_server_url')
                        ->label('URL de votre instance Web')
                        ->placeholder('https://votre-domaine.com')
                        ->default($this->server_url ?? config('app.url'))
                        ->required()
                        ->url(),
                    TextInput::make('modal_email')
                        ->label('Adresse email du compte Web')
                        ->email()
                        ->required(),
                    TextInput::make('modal_password')
                        ->label('Mot de passe')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $serverUrl = rtrim($data['modal_server_url'], '/');
                        $httpClient = Http::acceptJson();
                        if (! $this->verify_ssl) {
                            $httpClient = $httpClient->withoutVerifying();
                        }

                        try {
                            $response = $httpClient->post("{$serverUrl}/api/auth/token", [
                                'email' => $data['modal_email'],
                                'password' => $data['modal_password'],
                                'device_name' => 'LinksVault Desktop (Auto-Sync)',
                            ]);
                        } catch (Throwable $e) {
                            $msg = $e->getMessage();
                            if (str_contains($msg, 'cURL error 60') || str_contains($msg, 'untrusted root') || str_contains($msg, 'certificate verify failed')) {
                                $this->verify_ssl = false;
                                $response = Http::withoutVerifying()->acceptJson()->post("{$serverUrl}/api/auth/token", [
                                    'email' => $data['modal_email'],
                                    'password' => $data['modal_password'],
                                    'device_name' => 'LinksVault Desktop (Auto-Sync)',
                                ]);
                            } else {
                                throw $e;
                            }
                        }

                        if (! $response->successful()) {
                            throw new \RuntimeException($response->json('message') ?? 'Identifiants invalides.');
                        }

                        $token = $response->json('token');
                        $this->server_url = $serverUrl;
                        $this->api_token = $token;
                        $this->saveSettings();

                        Notification::make()
                            ->title('Connexion réussie')
                            ->body('Votre application Desktop est maintenant liée au serveur Web.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Échec de la connexion')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function checkForDesktopUpdates(?DesktopHotPatchService $patchService = null): void
    {
        try {
            $this->is_checking_updates = true;
            cache()->put('nativephp_checking_updates', true, now()->addMinutes(2));
            cache()->forget('nativephp_updater_error');

            $patchService ??= app(DesktopHotPatchService::class);

            // 1. Recherche prioritaire d'un Hot-Patch léger
            $patch = $patchService->checkForPatch($this->desktop_app_version);
            if ($patch) {
                $this->patch_available = $patch;
                cache()->put('nativephp_patch_available', $patch, now()->addHours(6));

                $now = now()->toIso8601String();
                cache()->put('nativephp_last_checked_at', $now, now()->addDays(1));
                $this->is_checking_updates = false;

                Notification::make()
                    ->title("Mise à jour rapide v{$patch['version']} disponible !")
                    ->body("Un patch léger ({$patch['patch_size_human']}) ne contenant que les fichiers modifiés est prêt.")
                    ->info()
                    ->send();

                $this->mount();

                return;
            }

            $isDesktop = static::isDesktop();

            if ($isDesktop && class_exists(AutoUpdater::class)) {
                try {
                    AutoUpdater::checkForUpdates();
                } catch (Throwable) {
                    // Non bloquant en mode dev
                }
            }

            // 2. Vérification auprès de GitHub Releases standard
            $response = Http::withHeaders([
                'User-Agent' => 'LinksVault-App',
                'Accept' => 'application/vnd.github+json',
            ])->timeout(5)->get('https://api.github.com/repos/baguidevos/links-vault/releases/latest');

            $now = now()->toIso8601String();
            cache()->put('nativephp_last_checked_at', $now, now()->addDays(1));

            if ($response->successful()) {
                $releaseData = $response->json();
                $remoteTag = ltrim((string) ($releaseData['tag_name'] ?? ''), 'v');
                $localVersion = ltrim($this->desktop_app_version, 'v');

                if (version_compare($remoteTag, $localVersion, '>')) {
                    cache()->put('nativephp_update_available', [
                        'version' => $remoteTag,
                        'detected_at' => $now,
                        'html_url' => $releaseData['html_url'] ?? null,
                    ], now()->addHours(6));

                    Notification::make()
                        ->title("Nouvelle version v{$remoteTag} disponible !")
                        ->body('Une nouvelle version est disponible sur GitHub Releases. Le téléchargement se fait automatiquement sur Desktop.')
                        ->info()
                        ->send();
                } else {
                    Notification::make()
                        ->title('LinksVault est à jour')
                        ->body("Vous disposez déjà de la version la plus récente (v{$this->desktop_app_version}).")
                        ->success()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Recherche de mise à jour effectuée')
                    ->body("Version actuelle de l'application : v{$this->desktop_app_version}.")
                    ->info()
                    ->send();
            }

            $this->mount();
        } catch (Throwable) {
            $this->is_checking_updates = false;
            Notification::make()
                ->title('Vérification effectuée')
                ->body("Version actuelle : v{$this->desktop_app_version}.")
                ->info()
                ->send();
        } finally {
            $this->is_checking_updates = false;
            $this->mount();
        }
    }

    public function downloadAndApplyDesktopPatch(DesktopHotPatchService $patchService): void
    {
        if (! $this->patch_available) {
            return;
        }

        $this->is_downloading_patch = true;

        try {
            $zipPath = $patchService->downloadPatch(
                $this->patch_available['patch_url'],
                $this->patch_available['version']
            );

            $result = $patchService->applyPatch($zipPath);

            $this->desktop_app_version = $result['version'];
            $this->patch_installed = true;
            $this->patch_available = null;
            cache()->put('nativephp_patch_installed', true, now()->addDays(7));
            cache()->forget('nativephp_patch_available');

            Notification::make()
                ->title("Patch v{$result['version']} installé avec succès !")
                ->body("{$result['files_count']} fichiers mis à jour. Redémarrez l'application pour appliquer les changements.")
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erreur lors de l’application du patch')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->is_downloading_patch = false;
            $this->mount();
        }
    }

    public function relaunchDesktopApp(DesktopHotPatchService $patchService): void
    {
        cache()->forget('nativephp_patch_installed');
        $patchService->relaunch();
    }

    public function installDesktopUpdate(): void
    {
        if (! static::isDesktop()) {
            return;
        }

        try {
            cache()->forget('nativephp_update_downloaded');

            if (class_exists(AutoUpdater::class)) {
                AutoUpdater::quitAndInstall();
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erreur lors de l\'installation')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
