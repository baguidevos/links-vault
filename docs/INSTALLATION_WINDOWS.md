# Installation et réparation Windows

## Mise à jour normale

Lorsqu'une mise à jour est disponible, LinksVault la télécharge puis l'installe au redémarrage. Cette méthode est à privilégier : l'application ferme son propre processus avant que Windows ne remplace les fichiers.

## Réparation d'une ancienne installation

Chaque release Windows générée par GitHub Actions contient l'archive `LinksVault-<version>-windows-repair.zip`. Extrayez-la dans un dossier, puis double-cliquez sur `Install-LinksVault.cmd`.

Le lanceur effectue les opérations suivantes :

1. ferme uniquement les processus `linksvault.exe` encore actifs ;
2. lance le désinstalleur existant en mode silencieux ;
3. si le désinstalleur échoue, supprime uniquement le dossier d'installation orphelin `%LOCALAPPDATA%\Programs\linksvault` ;
4. démarre le nouveau fichier `*-setup.exe`.

Les données utilisateur ne sont pas supprimées : le lanceur ne touche ni au stockage NativePHP, ni à `%LOCALAPPDATA%\linksvault-updater`.

## Publication

Le workflow GitHub Actions `Desktop Release` ajoute automatiquement cette archive de réparation à la release, après la publication du setup Windows. Elle contient :

- le setup Windows ;
- `Install-LinksVault.cmd` ;
- `Repair-LinksVaultInstall.ps1`.

Pour une publication locale, utilisez l'auto-updater ou joignez ces trois fichiers dans la même archive avant de la transmettre aux utilisateurs.
