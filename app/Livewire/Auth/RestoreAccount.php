<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\TeamActions\CreateTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\CloudBackup\VaultRestoreService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;
use ZipArchive;

class RestoreAccount extends Component
{
    use WithFileUploads;

    /**
     * @var mixed
     */
    public $backup_file = null;

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $detectedEmail = null;

    public ?string $detectedName = null;

    public ?string $detectedTeam = null;

    public ?int $detectedLinksCount = null;

    public ?string $parseError = null;

    public bool $isRestoring = false;

    public function mount(): mixed
    {
        if (Auth::check()) {
            return redirect()->to(Filament::getPanel('app')->getUrl());
        }

        return null;
    }

    public function updatedBackupFile(): void
    {
        $this->reset(['detectedEmail', 'detectedName', 'detectedTeam', 'detectedLinksCount', 'parseError']);

        $this->validateOnly('backup_file', [
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'backup_file.mimes' => 'Le fichier sélectionné doit être une archive au format .zip.',
            'backup_file.max' => 'L\'archive ne doit pas dépasser 50 Mo.',
        ]);

        try {
            $zip = new ZipArchive;
            if ($zip->open($this->backup_file->getRealPath()) !== true) {
                $this->parseError = 'Le fichier fourni n\'est pas une archive ZIP valide.';

                return;
            }

            $manifestJson = $zip->getFromName('manifest.json');
            if ($manifestJson === false) {
                $zip->close();
                $this->parseError = 'Archive invalide : fichier manifest.json introuvable dans le ZIP.';

                return;
            }

            $manifest = json_decode($manifestJson, true);
            if (! is_array($manifest) || ($manifest['app'] ?? '') !== 'LinksVault') {
                $zip->close();
                $this->parseError = 'Archive non reconnue comme une sauvegarde officielle LinksVault.';

                return;
            }

            $this->detectedEmail = $manifest['exported_by']['email'] ?? null;
            $this->detectedName = $manifest['exported_by']['name'] ?? 'Utilisateur';
            $this->detectedTeam = $manifest['team']['name'] ?? null;

            $linksJson = $zip->getFromName('links.json');
            if ($linksJson !== false) {
                $linksData = json_decode($linksJson, true);
                $this->detectedLinksCount = is_array($linksData) ? count($linksData) : 0;
            }

            $zip->close();

            if (blank($this->detectedEmail)) {
                $this->parseError = 'Cette sauvegarde ne contient pas d\'adresse email de compte.';
            }
        } catch (Throwable $e) {
            $this->parseError = 'Erreur lors de la lecture de l\'archive : '.$e->getMessage();
        }
    }

    public function restore(): mixed
    {
        $this->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'backup_file.required' => 'Veuillez sélectionner votre fichier de sauvegarde .zip.',
            'backup_file.mimes' => 'Le fichier doit être une archive au format .zip.',
            'backup_file.max' => 'L\'archive ne doit pas dépasser 50 Mo.',
            'password.required' => 'Veuillez définir un mot de passe pour le compte.',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $this->isRestoring = true;

        $tempPath = $this->backup_file->getRealPath();

        $zip = new ZipArchive;
        if ($zip->open($tempPath) !== true) {
            $this->addError('backup_file', 'Le fichier fourni n\'est pas une archive ZIP valide.');
            $this->isRestoring = false;

            return null;
        }

        $manifestJson = $zip->getFromName('manifest.json');
        if ($manifestJson === false) {
            $zip->close();
            $this->addError('backup_file', 'Archive invalide : fichier manifest.json introuvable.');
            $this->isRestoring = false;

            return null;
        }

        $manifest = json_decode($manifestJson, true);
        $zip->close();

        if (! is_array($manifest) || ($manifest['app'] ?? '') !== 'LinksVault') {
            $this->addError('backup_file', 'Archive non reconnue comme une sauvegarde officielle LinksVault.');
            $this->isRestoring = false;

            return null;
        }

        $email = $manifest['exported_by']['email'] ?? null;
        $name = $manifest['exported_by']['name'] ?? 'Utilisateur';

        if (blank($email)) {
            $this->addError('backup_file', 'Cette sauvegarde ne contient pas d\'adresse email valide.');
            $this->isRestoring = false;

            return null;
        }

        // 1. Recherche ou création du compte utilisateur avec le nouveau mot de passe (Option A)
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($this->password),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'password' => Hash::make($this->password),
            ]);
        }

        // 2. Recherche ou création de l'espace de travail (Team)
        $team = $user->personalTeam() ?? $user->teams()->first();
        if (! $team) {
            $createTeam = app(CreateTeam::class);
            $teamName = $manifest['team']['name'] ?? "Espace de {$user->name}";
            $team = $createTeam->handle($user, [
                'name' => $teamName,
                'is_personal' => true,
            ]);
        }

        // 3. Restauration avec fusion des données (Choix 2 : overwrite = false)
        $zipBinaryContent = file_get_contents($tempPath);
        $restoreService = app(VaultRestoreService::class);

        try {
            $results = $restoreService->restoreFromZip(
                zipBinaryContent: $zipBinaryContent,
                targetTeam: $team,
                user: $user,
                overwrite: false,
            );
        } catch (Throwable $e) {
            $this->addError('backup_file', 'Erreur lors de la restauration : '.$e->getMessage());
            $this->isRestoring = false;

            return null;
        }

        // 4. Activation de l'équipe
        $user->switchTeam($team);

        // 5. Connexion directe de l'utilisateur
        Auth::login($user);
        session()->regenerate();

        Notification::make()
            ->title('Compte restauré avec succès !')
            ->body("Bienvenue {$user->name}. {$results['imported_links']} lien(s) importé(s), {$results['skipped_links']} lien(s) déjà présent(s).")
            ->success()
            ->send();

        $redirectUrl = Filament::getPanel('app')->getUrl($team) ?? url('/app');

        return redirect()->to($redirectUrl);
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function render(): View
    {
        return view('livewire.auth.restore-account')
            ->layout('filament-panels::components.layout.simple', [
                'maxWidth' => Width::Medium,
            ]);
    }
}
