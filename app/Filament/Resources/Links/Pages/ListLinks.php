<?php

namespace App\Filament\Resources\Links\Pages;

use App\Actions\LinkActions\CreateLinkAction;
use App\Filament\Resources\Links\LinkResource;
use App\Filament\Resources\Links\Schemas\LinkForm;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

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
