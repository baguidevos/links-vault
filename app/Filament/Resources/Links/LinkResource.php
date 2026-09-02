<?php

namespace App\Filament\Resources\Links;

use App\Filament\Resources\Links\Pages\CreateLink;
use App\Filament\Resources\Links\Pages\EditLink;
use App\Filament\Resources\Links\Pages\ListLinks;
use App\Filament\Resources\Links\Pages\ViewLink;
use App\Filament\Resources\Links\Schemas\LinkForm;
use App\Filament\Resources\Links\Schemas\LinkInfolist;
use App\Filament\Resources\Links\Tables\LinksTable;
use App\Models\Link;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LinkResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::LinkFilled;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Lien');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Liens');
    }

    public static function getNavigationLabel(): string
    {
        return __('Liens');
    }

    public static function form(Schema $schema): Schema
    {
        return LinkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LinkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'objective', 'content_type'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['category', 'folder', 'tags']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Link $record */
        $details = [];

        if ($record->content_type) {
            $details['Type'] = $record->content_type->label();
        }

        if ($record->url) {
            $details['URL'] = Str::limit($record->url, 45);
        }

        if ($record->folder) {
            $details['Dossier'] = $record->folder->name;
        }

        if ($record->category) {
            $details['Catégorie'] = $record->category->name;
        }

        return $details;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinks::route('/'),
            'create' => CreateLink::route('/create'),
            'view' => ViewLink::route('/{record}'),
            'edit' => EditLink::route('/{record}/edit'),
        ];
    }
}
