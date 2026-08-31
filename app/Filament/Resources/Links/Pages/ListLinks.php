<?php

namespace App\Filament\Resources\Links\Pages;

use App\Actions\LinkActions\CreateLinkAction;
use App\Filament\Resources\Links\LinkResource;
use App\Filament\Resources\Links\Schemas\LinkForm;
use App\Models\Folder;
use App\Models\Link;
use App\Services\BookmarksExportService;
use App\Services\BookmarksImportService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    public string $viewMode = 'grid';

    public function mount(): void
    {
        parent::mount();
        $this->viewMode = session('links_view_mode', 'grid');
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'table' : 'grid';
        session(['links_view_mode' => $this->viewMode]);
        $this->resetTable();
    }

    public function toggleFavoriteFromCard(int $linkId): void
    {
        $link = Link::find($linkId);
        if ($link) {
            $link->update([
                'is_favorite' => ! $link->is_favorite,
            ]);

            Notification::make()
                ->title($link->is_favorite ? __('Ajouté aux favoris ⭐') : __('Retiré des favoris'))
                ->success()
                ->send();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('Liens');
    }

    public function getBreadcrumb(): ?string
    {
        return __('Liste');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Tous vos liens sauvegardés, organisés et enrichis par IA.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_view')
                ->label(fn () => $this->viewMode === 'grid' ? __('Vue Table') : __('Vue Grille'))
                ->icon(fn () => $this->viewMode === 'grid' ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
                ->color('gray')
                ->outlined()
                ->action(fn () => $this->toggleViewMode()),

            ActionGroup::make([
                Action::make('export_html')
                    ->label(__('Format HTML (Navigateurs/Chrome)'))
                    ->icon('heroicon-o-document-text')
                    ->action(function (BookmarksExportService $exporter) {
                        $user = auth()->user();
                        $tenant = Filament::getTenant();
                        $teamId = $tenant ? $tenant->id : $user->current_team_id;
                        $content = $exporter->exportToHtml($user, (int) $teamId);

                        return response()->streamDownload(
                            fn () => print ($content),
                            'linksvault-bookmarks-'.now()->format('Y-m-d').'.html',
                            ['Content-Type' => 'text/html; charset=UTF-8']
                        );
                    }),

                Action::make('export_json')
                    ->label(__('Format JSON'))
                    ->icon('heroicon-o-code-bracket')
                    ->action(function (BookmarksExportService $exporter) {
                        $user = auth()->user();
                        $tenant = Filament::getTenant();
                        $teamId = $tenant ? $tenant->id : $user->current_team_id;
                        $content = $exporter->exportToJson($user, (int) $teamId);

                        return response()->streamDownload(
                            fn () => print ($content),
                            'linksvault-bookmarks-'.now()->format('Y-m-d').'.json',
                            ['Content-Type' => 'application/json']
                        );
                    }),

                Action::make('export_csv')
                    ->label(__('Format CSV (Excel)'))
                    ->icon('heroicon-o-table-cells')
                    ->action(function (BookmarksExportService $exporter) {
                        $user = auth()->user();
                        $tenant = Filament::getTenant();
                        $teamId = $tenant ? $tenant->id : $user->current_team_id;
                        $content = $exporter->exportToCsv($user, (int) $teamId);

                        return response()->streamDownload(
                            fn () => print ($content),
                            'linksvault-bookmarks-'.now()->format('Y-m-d').'.csv',
                            ['Content-Type' => 'text/csv; charset=UTF-8']
                        );
                    }),
            ])
                ->label(__('Exporter'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->button(),

            Action::make('import_bookmarks')
                ->label(__('Importer'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->outlined()
                ->modalHeading(__('Importer des signets'))
                ->modalDescription(__('Compatible avec les fichiers de favoris Chrome, Firefox, Safari, Edge, Pocket, Raindrop.io, JSON ou CSV.'))
                ->modalIcon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label(__('Fichier de signets'))
                        ->acceptedFileTypes(['text/html', 'application/json', 'text/csv', 'text/plain', 'text/xml'])
                        ->disk('local')
                        ->directory('temp-imports')
                        ->required(),

                    Select::make('folder_id')
                        ->label(__('Dossier de destination par défaut (optionnel)'))
                        ->placeholder(__('Créer selon l\'arborescence du fichier'))
                        ->options(function () {
                            $user = auth()->user();
                            if (! $user) {
                                return [];
                            }

                            return Folder::query()
                                ->accessibleForUser($user)
                                ->pluck('name', 'id');
                        })
                        ->searchable(),

                    Toggle::make('generate_ai_summary')
                        ->label(__('Générer les résumés IA en arrière-plan'))
                        ->helperText(__('Lance l\'analyse IA en tâche de fond pour chaque lien importé.'))
                        ->default(false),
                ])
                ->action(function (array $data, BookmarksImportService $importer) {
                    $user = auth()->user();
                    $tenant = Filament::getTenant();
                    $teamId = (int) ($tenant ? $tenant->id : $user->current_team_id);
                    $relativeFile = $data['file'];

                    // Récupération multi-environnements (Web, Desktop NativePHP, Storage local/public)
                    $content = null;
                    $disk = Storage::disk('local');

                    if ($disk->exists($relativeFile)) {
                        $content = $disk->get($relativeFile);
                        $disk->delete($relativeFile);
                    } elseif (Storage::disk('public')->exists($relativeFile)) {
                        $content = Storage::disk('public')->get($relativeFile);
                        Storage::disk('public')->delete($relativeFile);
                    } elseif (file_exists($relativeFile)) {
                        $content = file_get_contents($relativeFile);
                        @unlink($relativeFile);
                    } elseif (file_exists(storage_path('app/'.$relativeFile))) {
                        $content = file_get_contents(storage_path('app/'.$relativeFile));
                        @unlink(storage_path('app/'.$relativeFile));
                    } elseif (file_exists(storage_path('app/private/'.$relativeFile))) {
                        $content = file_get_contents(storage_path('app/private/'.$relativeFile));
                        @unlink(storage_path('app/private/'.$relativeFile));
                    }

                    $ext = pathinfo($relativeFile, PATHINFO_EXTENSION);

                    $res = $importer->importFromContent(
                        (string) ($content ?? ''),
                        $ext,
                        $user,
                        $teamId,
                        ! empty($data['folder_id']) ? (int) $data['folder_id'] : null,
                        ! empty($data['generate_ai_summary'])
                    );

                    Notification::make()
                        ->title(__('Importation terminée !'))
                        ->body(__(':imported liens importés, :folders dossiers créés, :skipped doublons ignorés.', [
                            'imported' => $res['imported_count'],
                            'folders' => $res['folders_created'],
                            'skipped' => $res['skipped_count'],
                        ]))
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->form(LinkForm::getComponents())
                ->label(__('Nouveau lien'))
                ->icon(TablerIcon::Plus)
                ->action(function (array $data) {
                    CreateLinkAction::execute($data);
                }),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('create_link')
                ->label(__('Nouveau lien'))
                ->icon(TablerIcon::Plus)
                ->url(LinkResource::getUrl('create'))
                ->color('primary'),
        ];
    }

    public function notifications(): void
    {
        Notification::make()
            ->title(__('Lien créé avec succès'))
            ->success()
            ->send();
    }
}
