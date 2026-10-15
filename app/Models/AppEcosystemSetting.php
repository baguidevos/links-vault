<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppEcosystemSetting extends Model
{
    /**
     * @var string
     */
    protected $table = 'app_ecosystem_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'desktop_windows_url',
        'desktop_mac_url',
        'desktop_linux_url',
        'desktop_version',
        'desktop_is_active',
        'desktop_release_notes',
        'extension_chrome_url',
        'extension_chrome_is_active',
        'extension_chrome_version',
        'extension_firefox_url',
        'extension_firefox_is_active',
        'extension_firefox_version',
        'mobile_android_url',
        'mobile_android_is_active',
        'mobile_android_version',
        'mobile_ios_url',
        'mobile_ios_is_active',
        'mobile_ios_version',
        'mobile_notice',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desktop_is_active' => 'boolean',
            'extension_chrome_is_active' => 'boolean',
            'extension_firefox_is_active' => 'boolean',
            'mobile_android_is_active' => 'boolean',
            'mobile_ios_is_active' => 'boolean',
        ];
    }

    /**
     * Obtenir l'instance unique de configuration écosystème (ou créer celle par défaut).
     */
    public static function current(): self
    {
        try {
            /** @var self|null $settings */
            $settings = static::query()->first();

            if ($settings) {
                return $settings;
            }

            return static::query()->create([
                'desktop_windows_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.1.6/LinksVault-1.1.6-setup.exe',
                'desktop_mac_url' => 'https://github.com/baguidevos/links-vault/releases/latest',
                'desktop_linux_url' => null,
                'desktop_version' => '1.1.6',
                'desktop_is_active' => true,
                'desktop_release_notes' => 'Release officielle v1.1.6 pour Windows et Mac.',
                'extension_chrome_url' => null,
                'extension_chrome_is_active' => true,
                'extension_chrome_version' => '1.0.0',
                'extension_firefox_url' => null,
                'extension_firefox_is_active' => false,
                'extension_firefox_version' => '1.0.0',
                'mobile_android_url' => null,
                'mobile_android_is_active' => false,
                'mobile_android_version' => '1.0.0',
                'mobile_ios_url' => null,
                'mobile_ios_is_active' => false,
                'mobile_ios_version' => '1.0.0',
                'mobile_notice' => 'Bientôt disponible sur Google Play & App Store',
            ]);
        } catch (\Throwable) {
            return new static([
                'desktop_windows_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.1.6/LinksVault-1.1.6-setup.exe',
                'desktop_mac_url' => 'https://github.com/baguidevos/links-vault/releases/latest',
                'desktop_linux_url' => null,
                'desktop_version' => '1.1.6',
                'desktop_is_active' => true,
                'extension_chrome_url' => null,
                'extension_chrome_is_active' => true,
                'extension_chrome_version' => '1.0.0',
                'extension_firefox_url' => null,
                'extension_firefox_is_active' => false,
                'extension_firefox_version' => '1.0.0',
                'mobile_android_url' => null,
                'mobile_android_is_active' => false,
                'mobile_android_version' => '1.0.0',
                'mobile_ios_url' => null,
                'mobile_ios_is_active' => false,
                'mobile_ios_version' => '1.0.0',
                'mobile_notice' => 'Bientôt disponible sur Google Play & App Store',
            ]);
        }
    }

    /**
     * Résoudre l'URL de téléchargement Windows Desktop effective.
     */
    public function getEffectiveDesktopWindowsUrl(): string
    {
        return filled($this->desktop_windows_url)
            ? (string) $this->desktop_windows_url
            : 'https://github.com/baguidevos/links-vault/releases/download/v1.1.1/LinksVault-1.1.1-setup.exe';
    }

    /**
     * Résoudre l'URL de l'extension Chrome effective.
     */
    public function getEffectiveExtensionChromeUrl(): string
    {
        return filled($this->extension_chrome_url)
            ? (string) $this->extension_chrome_url
            : route('extension.download');
    }

    /**
     * Résoudre l'URL de l'extension Firefox effective.
     */
    public function getEffectiveExtensionFirefoxUrl(): ?string
    {
        return filled($this->extension_firefox_url)
            ? (string) $this->extension_firefox_url
            : null;
    }
}
