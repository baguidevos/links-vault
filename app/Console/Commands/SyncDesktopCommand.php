<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sync\DesktopSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('desktop:sync {--user= : ID ou email de l\'utilisateur}')]
#[Description('Synchronise la base locale Desktop avec l\'instance Web LinksVault')]
class SyncDesktopCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DesktopSyncService $syncService): int
    {
        $this->info('Lancement de la synchronisation Desktop ↔ Web...');

        $userQuery = User::query();
        $userOption = $this->option('user');

        if ($userOption) {
            if (is_numeric($userOption)) {
                $userQuery->where('id', (int) $userOption);
            } else {
                $userQuery->where('email', $userOption);
            }
        }

        $users = $userQuery->get();

        if ($users->isEmpty()) {
            $this->warn('Aucun utilisateur trouvé pour la synchronisation.');

            return self::FAILURE;
        }

        $hasError = false;
        foreach ($users as $user) {
            $this->line("Synchronisation pour : {$user->name} ({$user->email})...");
            $result = $syncService->sync($user);

            if ($result['success'] ?? false) {
                $this->info('  [OK] '.($result['message'] ?? 'Synchronisé'));
                if (isset($result['stats'])) {
                    $this->table(
                        ['Entité', 'Modifications appliquées'],
                        collect($result['stats'])->map(fn ($count, $key) => [ucfirst($key), $count])->all()
                    );
                }
            } else {
                $this->error('  [ERREUR] '.($result['error'] ?? 'Échec inconnu'));
                $hasError = true;
            }
        }

        return $hasError ? self::FAILURE : self::SUCCESS;
    }
}
