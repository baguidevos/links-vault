<?php

namespace App\Enums;

enum ContentType: string
{
    case Youtube = 'youtube';
    case YoutubePlaylist = 'youtube_playlist';
    case GoogleDoc = 'google_doc';
    case GoogleSlides = 'google_slides';
    case GoogleSheet = 'google_sheet';
    case GoogleForm = 'google_form';
    case GoogleDrive = 'google_drive';
    case Article = 'article';
    case Pdf = 'pdf';
    case Image = 'image';
    case Other = 'other';

    public static function normalize(string|self|null $type, ?string $fallback = null): self
    {
        if ($type instanceof self) {
            return $type;
        }

        if (empty($type)) {
            return self::tryFrom((string) $fallback) ?? self::Other;
        }

        $clean = strtolower(trim($type));

        if ($clean === 'drive') {
            return self::GoogleDrive;
        }

        return self::tryFrom($clean)
            ?? self::tryFrom((string) $fallback)
            ?? self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube',
            self::YoutubePlaylist => 'YouTube Playlist',
            self::GoogleDoc => 'Google Doc',
            self::GoogleSlides => 'Google Slides',
            self::GoogleSheet => 'Google Sheet',
            self::GoogleForm => 'Google Form',
            self::GoogleDrive => 'Google Drive',
            self::Article => 'Article',
            self::Pdf => 'PDF',
            self::Image => 'Image',
            self::Other => 'Other',
        };
    }
}
