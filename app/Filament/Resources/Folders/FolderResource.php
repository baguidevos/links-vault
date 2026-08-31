<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders;

use App\Filament\Resources\Folders\Pages\CreateFolder;
use App\Filament\Resources\Folders\Pages\EditFolder;
use App\Filament\Resources\Folders\Pages\ListFolders;
use App\Filament\Resources\Folders\Pages\ViewFolder;
use App\Filament\Resources\Folders\Schemas\FolderForm;
use App\Filament\Resources\Folders\Schemas\FolderInfolist;
use App\Filament\Resources\Folders\Tables\FoldersTable;
use App\Models\Folder;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FolderResource extends Resource
{
    protected static ?string $model = Folder::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Folder;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Dossiers');
    }

    public static function getNavigationLabel(): string
    {
        return __('Dossiers');
    }

    public static function form(Schema $schema): Schema
    {
        return FolderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FolderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoldersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Folder $record */
        $details = [];

        if ($record->category) {
            $details['Catégorie'] = $record->category->name;
        }

        $details['Liens'] = (string) $record->links()->count();

        return $details;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFolders::route('/'),
            // 'create' => CreateFolder::route('/create'),
            'view' => ViewFolder::route('/{record}'),
            'edit' => EditFolder::route('/{record}/edit'),
        ];
    }
}
