<?php

namespace App\Filament\Resources\Links\Pages;

use App\Actions\LinkActions\GenerateAiSummaryAction;
use App\Enums\LinkHealthStatus;
use App\Filament\Resources\Links\Actions\ChangeVisibilityAction;
use App\Filament\Resources\Links\Actions\ShareLinkModalAction;
use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use App\Services\ContentDetectionService;
use App\Services\LinkHealthService;
use App\Services\WebPageMetadataService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
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

            Action::make('generate_ai_summary')
                ->label(fn () => $this->record->ai_summary ? __('Régénérer IA ✨') : __('Analyser IA ✨'))
                ->icon('heroicon-m-sparkles')
                ->color('primary')
                ->action(function () {
                    GenerateAiSummaryAction::execute($this->record);
                    $this->record->refresh();
                    Notification::make()
                        ->title(__('Résumé IA généré avec succès !'))
                        ->success()
                        ->send();
                }),

            Action::make('refresh_metadata')
                ->label(__('Actualiser miniature'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $analysis = app(ContentDetectionService::class)->analyze($this->record->url);
                    $meta = $analysis['metadata'] ?? [];
                    $this->record->update([
                        'content_type' => $analysis['type'] ?? $this->record->content_type,
                        'metadata' => array_merge($this->record->getSafeMetadata(), $meta),
                        'thumbnail_url' => $meta['image'] ?? $meta['og_image'] ?? $this->record->thumbnail_url,
                        'favicon_url' => $meta['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($this->record->url),
                    ]);
                    $this->record->refresh();
                    Notification::make()
                        ->title(__('Miniature et métadonnées actualisées !'))
                        ->success()
                        ->send();
                }),

            Action::make('toggle_favorite')
                ->label(fn () => $this->record->is_favorite ? __('Favori ⭐') : __('Favori'))
                ->icon(fn () => $this->record->is_favorite ? 'heroicon-s-star' : 'heroicon-o-star')
                ->color(fn () => $this->record->is_favorite ? 'warning' : 'gray')
                ->action(fn () => $this->toggleFavorite()),

            EditAction::make()
                ->label(__('Modifier')),

            ActionGroup::make([
                Action::make('check_health')
                    ->label(__('Vérifier disponibilité 🩺'))
                    ->icon(TablerIcon::HeartRateMonitor)
                    ->color('info')
                    ->action(function (LinkHealthService $healthService) {
                        $res = $healthService->checkLink($this->record);
                        $this->record->refresh();

                        if ($res['health_status'] === LinkHealthStatus::Healthy) {
                            Notification::make()
                                ->title(__('Lien en ligne (HTTP :status)', ['status' => $res['status_code'] ?? 200]))
                                ->success()
                                ->send();
                        } elseif ($res['health_status'] === LinkHealthStatus::Redirect) {
                            Notification::make()
                                ->title(__('Lien redirigé (HTTP :status)', ['status' => $res['status_code']]))
                                ->body($res['redirect_url'] ? "Cible : {$res['redirect_url']}" : null)
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('Lien mort ou inaccessible'))
                                ->body($res['error'] ?? 'Impossible de joindre le site distant.')
                                ->danger()
                                ->send();
                        }
                    }),

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
