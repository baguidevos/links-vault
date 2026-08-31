<?php

namespace App\Filament\Resources\Links\Pages;

use App\Filament\Resources\Links\Actions\ChangeVisibilityAction;
use App\Filament\Resources\Links\Actions\ShareLinkModalAction;
use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ViewLink extends ViewRecord
{
    protected static string $resource = LinkResource::class;

    protected string $view = 'filament.resources.links.pages.view-link';

    public function getTitle(): string|Htmlable
    {
        return Str::limit($this->record->title ?: $this->record->url, 45);
    }

    public function getBreadcrumbs(): array
    {
        return [
            LinkResource::getUrl('index') => __('Liens'),
            '#' => Str::limit($this->record->title ?: $this->record->url, 30),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load(['tags', 'category', 'folder', 'members']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_external')
                ->label(__('Ouvrir'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => $this->record->url, shouldOpenInNewTab: true)
                ->action(fn () => $this->recordVisit()),

            Action::make('toggle_favorite')
                ->label(fn () => $this->record->is_favorite ? __('Favori ⭐') : __('Favori'))
                ->icon(fn () => $this->record->is_favorite ? 'heroicon-s-star' : 'heroicon-o-star')
                ->color(fn () => $this->record->is_favorite ? 'warning' : 'gray')
                ->action(fn () => $this->toggleFavorite()),

            EditAction::make()
                ->label(__('Modifier')),

            ActionGroup::make([
                ShareLinkModalAction::make()
                    ->record($this->record),

                ChangeVisibilityAction::make()
                    ->record($this->record),

                DeleteAction::make()
                    ->successRedirectUrl(LinkResource::getUrl('index')),
            ])
                ->label(__('Plus'))
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Récupère les liens similaires dans la même équipe/catégorie.
     *
     * @return Collection<int, Link>
     */
    public function getSimilarLinks(): Collection
    {
        return Link::query()
            ->where('team_id', $this->record->team_id)
            ->where('id', '!=', $this->record->id)
            ->when(
                $this->record->category_id,
                fn ($query) => $query->where('category_id', $this->record->category_id),
                fn ($query) => $query->latest()
            )
            ->with(['category'])
            ->latest()
            ->take(3)
            ->get();
    }

    /**
     * Bascule le statut favori du lien.
     */
    public function toggleFavorite(): void
    {
        $this->record->update([
            'is_favorite' => ! $this->record->is_favorite,
        ]);

        $this->record->refresh();

        Notification::make()
            ->title($this->record->is_favorite ? __('Ajouté aux favoris') : __('Retiré des favoris'))
            ->success()
            ->send();
    }

    /**
     * Enregistre une visite sur le lien.
     */
    public function recordVisit(): void
    {
        $this->record->increment('visit_count');
        $this->record->update(['last_visited_at' => now()]);
        $this->record->refresh();
    }
}
