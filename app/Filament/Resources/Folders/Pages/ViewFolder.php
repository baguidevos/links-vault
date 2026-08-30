<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Actions\LinkActions\CreateLinkAction;
use App\Enums\FolderVisibility;
use App\Filament\Resources\Folders\Actions\ChangeFolderVisibilityAction;
use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Links\Schemas\LinkForm;
use App\Models\Folder;
use App\Models\Link;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class ViewFolder extends ViewRecord
{
    protected static string $resource = FolderResource::class;

    protected string $view = 'filament.resources.folders.pages.view-folder';

    public string $searchQuery = '';

    public ?string $selectedType = null;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getBreadcrumb(): string
    {
        return $this->record->name;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load(['category', 'user', 'members']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_link')
                ->label(__('Ajouter un lien'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form(LinkForm::getComponents())
                ->fillForm([
                    'folder_id' => $this->record->id,
                    'category_id' => $this->record->category_id,
                    'visibility' => $this->record->visibility instanceof FolderVisibility
                        ? $this->record->visibility->value
                        : (string) $this->record->visibility,
                ])
                ->action(function (array $data) {
                    $data['folder_id'] = $this->record->id;
                    if (empty($data['category_id']) && $this->record->category_id) {
                        $data['category_id'] = $this->record->category_id;
                    }

                    CreateLinkAction::execute($data);

                    Notification::make()
                        ->title(__('Lien ajouté au dossier avec succès'))
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            ChangeFolderVisibilityAction::make()
                ->record($this->record),

            EditAction::make(),

            DeleteAction::make()
                ->successRedirectUrl(FolderResource::getUrl('index')),
        ];
    }

    /**
     * Récupère les liens de ce dossier accessibles par l'utilisateur connecté.
     *
     * @return Collection<int, Link>
     */
    public function getFolderLinks(): Collection
    {
        $user = auth()->user();

        return Link::query()
            ->where('folder_id', $this->record->id)
            ->when($user, fn ($q) => $q->accessibleForUser($user))
            ->when($this->searchQuery, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->searchQuery}%")
                        ->orWhere('url', 'like', "%{$this->searchQuery}%")
                        ->orWhere('description', 'like', "%{$this->searchQuery}%");
                });
            })
            ->when($this->selectedType, fn ($q) => $q->where('content_type', $this->selectedType))
            ->with(['category', 'tags'])
            ->latest()
            ->get();
    }

    /**
     * Calcule les statistiques globales du dossier.
     *
     * @return array<string, mixed>
     */
    public function getFolderStats(): array
    {
        $user = auth()->user();

        $query = Link::query()
            ->where('folder_id', $this->record->id)
            ->when($user, fn ($q) => $q->accessibleForUser($user));

        $totalLinks = (clone $query)->count();
        $totalVisits = (clone $query)->sum('visit_count');
        $favoritesCount = (clone $query)->where('is_favorite', true)->count();

        // Répartition par type de contenu
        $typesBreakdown = (clone $query)
            ->selectRaw('content_type, count(*) as count')
            ->groupBy('content_type')
            ->pluck('count', 'content_type')
            ->toArray();

        return [
            'total_links' => $totalLinks,
            'total_visits' => $totalVisits,
            'favorites_count' => $favoritesCount,
            'types_breakdown' => $typesBreakdown,
        ];
    }

    /**
     * Récupère d'autres dossiers dans la même catégorie ou équipe.
     *
     * @return Collection<int, Folder>
     */
    public function getRelatedFolders(): Collection
    {
        $user = auth()->user();

        return Folder::query()
            ->where('team_id', $this->record->team_id)
            ->where('id', '!=', $this->record->id)
            ->when($user, fn ($q) => $q->accessibleForUser($user))
            ->when(
                $this->record->category_id,
                fn ($q) => $q->where('category_id', $this->record->category_id)
            )
            ->withCount('links')
            ->latest()
            ->take(4)
            ->get();
    }

    /**
     * Bascule l'état favori d'un lien.
     */
    public function toggleLinkFavorite(int $linkId): void
    {
        $link = Link::find($linkId);
        if ($link) {
            $link->update(['is_favorite' => ! $link->is_favorite]);

            Notification::make()
                ->title($link->is_favorite ? __('Ajouté aux favoris') : __('Retiré des favoris'))
                ->success()
                ->send();
        }
    }

    /**
     * Enregistre une visite sur un lien.
     */
    public function recordLinkVisit(int $linkId): void
    {
        $link = Link::find($linkId);
        if ($link) {
            $link->increment('visit_count');
            $link->update(['last_visited_at' => now()]);
        }
    }
}
