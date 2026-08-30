<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;
}
