<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

#[Signature('vault:encrypt-links')]
#[Description('Chiffre les URLs des liens existants en base de données qui sont encore en clair.')]
class EncryptExistingLinksCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $links = DB::table('links')->select('id', 'url')->orderBy('id')->get();

        if ($links->isEmpty()) {
            $this->info('Aucun lien trouvé dans la base de données.');

            return self::SUCCESS;
        }

        $this->info("Analyse de {$links->count()} lien(s)...");

        $encryptedCount = 0;
        $alreadyEncryptedCount = 0;

        $bar = $this->output->createProgressBar($links->count());
        $bar->start();

        foreach ($links as $link) {
            if (empty($link->url)) {
                $bar->advance();

                continue;
            }

            try {
                Crypt::decryptString($link->url);
                $alreadyEncryptedCount++;
            } catch (DecryptException) {
                $encryptedUrl = Crypt::encryptString($link->url);
                DB::table('links')->where('id', $link->id)->update([
                    'url' => $encryptedUrl,
                ]);
                $encryptedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Opération terminée avec succès :');
        $this->line("- Liens nouvellement chiffrés : <comment>{$encryptedCount}</comment>");
        $this->line("- Liens déjà chiffrés : <comment>{$alreadyEncryptedCount}</comment>");

        return self::SUCCESS;
    }
}
