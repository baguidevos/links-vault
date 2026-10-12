<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\AppEcosystemSetting;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageEcosystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::DeviceDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Écosystème & Téléchargements';

    protected static ?string $title = 'Gestion de l\'Écosystème LinksVault';

    protected static ?string $slug = 'ecosystem-settings';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->is_admin || $user->email === 'webmaster@gmail.com'));
    }

    public function getHeading(): string|Htmlable
    {
        return 'Écosystème Desktop, Extensions & Mobile';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Configurez les versions, URLs de téléchargement officielles (GitHub Releases, Stores) et statuts pour l\'application Desktop, les extensions navigateurs et applications mobiles.';
    }

    public function mount(): void
    {
        $settings = AppEcosystemSetting::current();
        $this->form->fill($settings->toArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])->schema([
                    Group::make([
                        Section::make('Application Desktop (Windows, macOS, Linux)')
                            ->description('Liens des releases GitHub, exécutables et version active.')
                            ->icon(TablerIcon::DeviceDesktop)
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('desktop_is_active')
                                        ->label('Activer la distribution Desktop')
                                        ->helperText('Affiche les boutons et liens Desktop sur la landing page.')
                                        ->default(true),

                                    TextInput::make('desktop_version')
                                        ->label('Version Desktop active')
                                        ->placeholder('1.1.1')
                                        ->required()
                                        ->prefixIcon(TablerIcon::Tag),
                                ]),

                                TextInput::make('desktop_windows_url')
                                    ->label('Lien téléchargement Windows (.exe)')
                                    ->placeholder('https://github.com/baguidevos/links-vault/releases/download/v1.1.1/LinksVault-1.1.1-setup.exe')
                                    ->prefixIcon(TablerIcon::BrandWindows)
                                    ->helperText('Lien direct vers le setup installateur Windows GitHub Release.')
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('desktop_mac_url')
                                        ->label('Lien macOS (.dmg / .zip)')
                                        ->placeholder('https://github.com/.../LinksVault-mac.dmg')
                                        ->prefixIcon(TablerIcon::BrandApple),

                                    TextInput::make('desktop_linux_url')
                                        ->label('Lien Linux (.AppImage / .deb)')
                                        ->placeholder('https://github.com/.../LinksVault-linux.AppImage')
                                        ->prefixIcon(TablerIcon::BrandUbuntu),
                                ]),

                                Textarea::make('desktop_release_notes')
                                    ->label('Notes de mise à jour / Message Desktop')
                                    ->rows(3)
                                    ->placeholder('Nouveautés de la version 1.1.1...')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Extensions Navigateurs (Chrome & Firefox)')
                            ->description('Téléchargements et intégrations pour Google Chrome & Mozilla Firefox.')
                            ->icon(TablerIcon::Browser)
                            ->schema([
                                // Google Chrome
                                Section::make('Google Chrome / Navigateurs Chromium')
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Toggle::make('extension_chrome_is_active')
                                                ->label('Extension Chrome disponible')
                                                ->default(true),

                                            TextInput::make('extension_chrome_version')
                                                ->label('Version Chrome')
                                                ->placeholder('1.0.0')
                                                ->prefixIcon(TablerIcon::Tag),
                                        ]),

                                        TextInput::make('extension_chrome_url')
                                            ->label('Lien Chrome Web Store ou Archive ZIP')
                                            ->placeholder('https://chromewebstore.google.com/detail/...')
                                            ->prefixIcon(TablerIcon::BrandChrome)
                                            ->helperText('Laissez vide pour utiliser le téléchargement ZIP direct (/extension/download).')
                                            ->columnSpanFull(),
                                    ]),

                                // Mozilla Firefox
                                Section::make('Mozilla Firefox')
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Toggle::make('extension_firefox_is_active')
                                                ->label('Extension Firefox disponible')
                                                ->helperText('Active le statut "Disponible" au lieu de "Bientôt disponible"')
                                                ->default(false),

                                            TextInput::make('extension_firefox_version')
                                                ->label('Version Firefox')
                                                ->placeholder('1.0.0')
                                                ->prefixIcon(TablerIcon::Tag),
                                        ]),

                                        TextInput::make('extension_firefox_url')
                                            ->label('Lien Mozilla Add-ons (AMO)')
                                            ->placeholder('https://addons.mozilla.org/fr/firefox/addon/linksvault/')
                                            ->prefixIcon(TablerIcon::BrandFirefox)
                                            ->helperText('Lien vers la fiche officielle sur Mozilla Firefox Add-ons.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),

                    Group::make([
                        Section::make('Applications Mobiles (À Venir)')
                            ->description('Configuration des futures applications mobiles Android & iOS.')
                            ->icon(TablerIcon::DeviceMobile)
                            ->schema([
                                TextInput::make('mobile_notice')
                                    ->label('Bandeau d\'annonce Mobile')
                                    ->placeholder('Bientôt disponible sur Google Play & App Store')
                                    ->prefixIcon(TablerIcon::Bell),

                                // Android
                                Section::make('Android (Google Play / APK)')
                                    ->compact()
                                    ->schema([
                                        Toggle::make('mobile_android_is_active')
                                            ->label('Application Android disponible')
                                            ->default(false),

                                        TextInput::make('mobile_android_version')
                                            ->label('Version Android')
                                            ->placeholder('1.0.0'),

                                        TextInput::make('mobile_android_url')
                                            ->label('Lien Google Play Store / APK')
                                            ->placeholder('https://play.google.com/store/apps/details?id=...')
                                            ->prefixIcon(TablerIcon::BrandAndroid),
                                    ]),

                                // iOS
                                Section::make('Apple iOS (App Store / TestFlight)')
                                    ->compact()
                                    ->schema([
                                        Toggle::make('mobile_ios_is_active')
                                            ->label('Application iOS disponible')
                                            ->default(false),

                                        TextInput::make('mobile_ios_version')
                                            ->label('Version iOS')
                                            ->placeholder('1.0.0'),

                                        TextInput::make('mobile_ios_url')
                                            ->label('Lien App Store / TestFlight')
                                            ->placeholder('https://apps.apple.com/app/...')
                                            ->prefixIcon(TablerIcon::BrandApple),
                                    ]),
                            ]),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer les modifications')
                ->icon(TablerIcon::Check)
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_landing')
                ->label('Voir la page d\'accueil')
                ->icon(TablerIcon::ExternalLink)
                ->color('gray')
                ->url('/')
                ->openUrlInNewTab(),

            Action::make('test_desktop_download')
                ->label('Tester le lien Windows (.exe)')
                ->icon(TablerIcon::Download)
                ->color('primary')
                ->url(route('desktop.download'))
                ->openUrlInNewTab(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = AppEcosystemSetting::current();
        $settings->update($data);

        Notification::make()
            ->title('Paramètres écosystème mis à jour')
            ->body('Les configurations pour Desktop, Extensions et Mobiles ont été enregistrées avec succès.')
            ->success()
            ->send();
    }
}
