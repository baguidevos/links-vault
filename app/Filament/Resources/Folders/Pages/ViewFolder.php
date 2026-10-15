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
use App\Services\SubscriptionQuotaService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
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
                ->disabled(fn () => auth()->user() && ! app(SubscriptionQuotaService::class)->canCreateLink(auth()->user(), $this->record->team ?? Filament::getTenant()))
                ->tooltip(fn () => (auth()->user() && ! app(SubscriptionQuotaService::class)->canCreateLink(auth()->user(), $this->record->team ?? Filament::getTenant())) ? __('Limite de liens atteinte pour votre plan.') : null)
                ->schema(LinkForm::getComponents())
                ->fillForm([
                    'folder_id' => $this->record->id,
                    'category_id' => $this->record->category_id,
                    'team_id' => $this->record->team_id,
                    'visibility' => $this->record->visibility instanceof FolderVisibility
                        ? $this->record->visibility->value
                        : (string) $this->record->visibility,
                ])
                ->action(function (array $data) {
                    $data['folder_id'] = $this->record->id;
                    $data['team_id'] = $this->record->team_id ?? Filament::getTenant()?->id;
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

            Action::make('add_existing_links')
                ->label(__('Ajouter des liens existants'))
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->modalHeading(__('Ajouter des liens existants au dossier'))
                ->modalDescription(__('Cochez les liens de cet espace auxquels vous avez accès pour les déplacer ou les rattacher à ce dossier.'))
                ->modalSubmitActionLabel(__('Ajouter au dossier'))
                ->modalWidth('xl')
                ->slideOver()
                ->visible(fn () => auth()->user() ? $this->record->canBeEditedBy(auth()->user()) : false)
                ->form([
                    CheckboxList::make('link_ids')
                        ->label(__('Liens disponibles'))
                        ->options(function () {
                            return $this->getAvailableLinksQuery()
                                ->latest()
                                ->get()
                                ->mapWithKeys(function (Link $link) {
                                    return [$link->id => $link->title ?: $link->url];
                                })
                                ->toArray();
                        })
                        ->descriptions(function () {
                            return $this->getAvailableLinksQuery()
                                ->latest()
                                ->get()
                                ->mapWithKeys(function (Link $link) {
                                    $parts = [];
                                    try {
                                        $host = parse_url($link->url, PHP_URL_HOST);
                                        if (! empty($host)) {
                                            $parts[] = $host;
                                        }
                                    } catch (\Throwable) {
                                        // Ignore en cas d'erreur de décryptage
                                    }

                                    if ($link->folder) {
                                        $parts[] = '📂 '.$link->folder->name;
                                    } else {
                                        $parts[] = '📄 '.__('Sans dossier');
                                    }

                                    if ($link->category) {
                                        $parts[] = '🏷️ '.$link->category->name;
                                    }

                                    return [$link->id => implode(' • ', $parts)];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->bulkToggleable()
                        ->required()
                        ->columns(1)
                        ->helperText(function () {
                            $count = $this->getAvailableLinksQuery()->count();
                            if ($count === 0) {
                                return __('Aucun lien disponible à ajouter dans cet espace.');
                            }

                            return __(':count lien(s) éligible(s) dans cet espace hors de ce dossier.', ['count' => $count]);
                        }),
                ])
                ->action(function (array $data) {
                    $linkIds = $data['link_ids'] ?? [];
                    if (empty($linkIds)) {
                        return;
                    }

                    $linksToUpdate = $this->getAvailableLinksQuery()
                        ->whereIn('id', $linkIds)
                        ->get();

                    $count = 0;
                    foreach ($linksToUpdate as $link) {
                        $link->update([
                            'folder_id' => $this->record->id,
                        ]);
                        $count++;
                    }

                    Notification::make()
                        ->title(__(':count lien(s) ajouté(s) au dossier avec succès', ['count' => $count]))
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
     * Récupère la requête des liens existants éligibles à être rattachés à ce dossier.
     */
    public function getAvailableLinksQuery(): Builder
    {
        $user = auth()->user();
        $tenant = Filament::getTenant() ?? $this->record->team;

        return Link::query()
            ->when($tenant, fn (Builder $q) => $q->where('team_id', $tenant->id))
            ->where(function (Builder $q) {
                $q->whereNull('folder_id')
                    ->orWhere('folder_id', '!=', $this->record->id);
            })
            ->when($user, fn (Builder $q) => $q->accessibleForUser($user))
            ->with(['folder', 'category']);
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

    /**
     * Retire un lien de ce dossier (le remet sans dossier).
     */
    public function removeLinkFromFolder(int $linkId): void
    {
        $user = auth()->user();
        if (! $user || ! $this->record->canBeEditedBy($user)) {
            Notification::make()
                ->title(__('Vous n\'avez pas les droits pour modifier ce dossier'))
                ->danger()
                ->send();

            return;
        }

        $link = Link::query()
            ->where('id', $linkId)
            ->where('folder_id', $this->record->id)
            ->when($user, fn (Builder $q) => $q->accessibleForUser($user))
            ->first();

        if ($link) {
            $link->update(['folder_id' => null]);

            Notification::make()
                ->title(__('Lien retiré du dossier avec succès'))
                ->success()
                ->send();

            $this->record->refresh();
        }
    }
}
