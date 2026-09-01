<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CloudStorageConfig;
use App\Models\GoogleDrive;
use App\Models\Team;
use Filament\Notifications\Notification;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Oauth2;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleDriveAuthController extends Controller
{
    /**
     * Instancie et configure le client OAuth2 Google.
     */
    protected function getGoogleClient(?string $redirectUri = null): Client
    {
        $client = new Client;
        $client->setClientId((string) config('services.google.client_id', env('GOOGLE_CLIENT_ID')));
        $client->setClientSecret((string) config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET')));
        $client->setRedirectUri($redirectUri ?: route('auth.google-drive.callback'));
        $client->addScope([
            Drive::DRIVE_FILE,
            'email',
            'profile',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent select_account');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    /**
     * Redirige l'utilisateur vers l'écran de sélection de compte et consentement Google.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.app.auth.login');
        }

        $clientId = config('services.google.client_id', env('GOOGLE_CLIENT_ID'));
        $clientSecret = config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET'));

        if (empty($clientId) || empty($clientSecret)) {
            Notification::make()
                ->title('Configuration Google manquante')
                ->body('Veuillez renseigner GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET dans votre fichier .env.')
                ->danger()
                ->send();

            return redirect()->back();
        }

        $teamId = $request->query('team_id');
        $team = $teamId ? Team::find($teamId) : ($user->currentTeam ?? $user->personalTeam());
        $teamSlug = $team?->slug ?? 'default';

        $stateData = [
            'user_id' => $user->id,
            'team_id' => $team?->id,
            'team_slug' => $teamSlug,
            'csrf_token' => csrf_token(),
        ];

        $client = $this->getGoogleClient();
        $client->setState(base64_encode(json_encode($stateData)));

        return redirect()->away($client->createAuthUrl());
    }

    /**
     * Traite le retour OAuth de Google et enregistre les jetons pour l'utilisateur et l'équipe.
     */
    public function callback(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('filament.app.auth.login');
        }

        if ($request->has('error')) {
            Notification::make()
                ->title('Connexion Google annulée')
                ->body('Vous avez annulé l\'autorisation Google Drive : '.$request->get('error'))
                ->warning()
                ->send();

            return redirect()->to($this->getBackupPageUrl());
        }

        $code = $request->get('code');
        if (empty($code)) {
            Notification::make()
                ->title('Code d\'autorisation manquant')
                ->danger()
                ->send();

            return redirect()->to($this->getBackupPageUrl());
        }

        // Décoder le state
        $stateRaw = $request->get('state');
        $state = [];
        if ($stateRaw) {
            $decoded = json_decode(base64_decode($stateRaw), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $teamId = $state['team_id'] ?? ($user->currentTeam?->id ?? $user->personalTeam()?->id);
        $teamSlug = $state['team_slug'] ?? ($user->currentTeam?->slug ?? $user->personalTeam()?->slug ?? '');

        try {
            $client = $this->getGoogleClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \RuntimeException($token['error_description'] ?? $token['error']);
            }

            $client->setAccessToken($token);

            // Récupérer l'adresse email du compte Google connecté
            $email = null;
            try {
                $oauth2 = new Oauth2($client);
                $userInfo = $oauth2->userinfo->get();
                $email = $userInfo->getEmail();
            } catch (Throwable) {
                // Fallback via verifyIdToken
                if (! empty($token['id_token'])) {
                    $payload = $client->verifyIdToken($token['id_token']);
                    $email = $payload['email'] ?? null;
                }
            }

            $refreshToken = $token['refresh_token'] ?? null;
            $expiresIn = (int) ($token['expires_in'] ?? 3600);

            // Si pas de refresh_token dans ce flux, conserver l'ancien si existant
            if (! $refreshToken && $teamId) {
                $existingDrive = GoogleDrive::where('team_id', $teamId)->first();
                $refreshToken = $existingDrive?->refresh_token;
            }

            // 1. Sauvegarder dans google_drives
            GoogleDrive::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'team_id' => $teamId,
                    'access_token' => is_array($token) ? json_encode($token) : (string) $token,
                    'refresh_token' => (string) ($refreshToken ?: ''),
                    'expires_at' => now()->addSeconds($expiresIn),
                    'email' => $email,
                ]
            );

            // 2. Activer dans cloud_storage_configs pour l'équipe
            if ($teamId) {
                CloudStorageConfig::updateOrCreate(
                    [
                        'team_id' => $teamId,
                        'provider' => 'google_drive',
                    ],
                    [
                        'is_active' => true,
                        'auto_backup_enabled' => true,
                        'frequency' => 'daily',
                        'retention_count' => 15,
                        'credentials' => [
                            'email' => $email,
                            'folder_name' => 'LinksVault_Backups',
                        ],
                    ]
                );
            }

            Notification::make()
                ->title('✅ Google Drive connecté avec succès !')
                ->body($email ? "Compte Google lié : **{$email}**. Vos sauvegardes sont prêtes !" : 'Votre compte Google Drive est désormais lié à LinksVault.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Log::error('Google Drive OAuth Callback Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Échec de la connexion Google Drive')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return redirect()->to($this->getBackupPageUrl($teamSlug));
    }

    /**
     * Déconnecte le compte Google Drive de l'équipe et de l'utilisateur.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $teamId = $request->input('team_id') ?? ($user?->currentTeam?->id ?? $user?->personalTeam()?->id);
        $teamSlug = $request->input('team_slug') ?? ($user?->currentTeam?->slug ?? $user?->personalTeam()?->slug ?? '');

        if ($user) {
            GoogleDrive::where('user_id', $user->id)->delete();
        }

        if ($teamId) {
            CloudStorageConfig::where('team_id', $teamId)
                ->where('provider', 'google_drive')
                ->update(['is_active' => false]);
        }

        Notification::make()
            ->title('Compte Google Drive déconnecté')
            ->body('Votre compte Google Drive a été dissocié de cet espace.')
            ->info()
            ->send();

        return redirect()->to($this->getBackupPageUrl($teamSlug));
    }

    /**
     * Construit l'URL de retour vers la page des sauvegardes cloud du tenant.
     */
    protected function getBackupPageUrl(?string $teamSlug = null): string
    {
        $user = Auth::user();
        $slug = $teamSlug ?: ($user?->currentTeam?->slug ?? $user?->personalTeam()?->slug);

        if ($slug) {
            return url("/app/{$slug}/manage-cloud-backups");
        }

        return url('/app');
    }
}
