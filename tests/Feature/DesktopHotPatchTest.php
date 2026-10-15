<?php

declare(strict_types=1);

use App\Livewire\AppVersionUpdater;
use App\Services\DesktopHotPatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('desktop make patch command runs successfully between tags', function () {
    $tempOutput = storage_path('app/releases/test-patch.zip');
    if (File::exists($tempOutput)) {
        File::delete($tempOutput);
    }

    $this->artisan('desktop:make-patch', [
        'from_tag' => 'v1.1.3',
        'to_tag' => 'v1.1.4',
        '--output' => $tempOutput,
    ])->assertSuccessful();

    expect(File::exists($tempOutput))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($tempOutput))->toBeTrue();

    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    expect($manifest)->toBeArray()
        ->and($manifest['from_version'])->toBe('1.1.3')
        ->and($manifest['to_version'])->toBe('1.1.4')
        ->and(count($manifest['files']))->toBeGreaterThan(0);

    $zip->close();
    File::delete($tempOutput);
});

test('desktop hot patch service detects patch asset from github release', function () {
    $service = new DesktopHotPatchService;

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.1.5',
            'html_url' => 'https://github.com/baguidevos/links-vault/releases/tag/v1.1.5',
            'assets' => [
                [
                    'name' => 'LinksVault-1.1.5-setup.exe',
                    'browser_download_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.1.5/LinksVault-1.1.5-setup.exe',
                    'size' => 155000000,
                ],
                [
                    'name' => 'patch-1.1.5.zip',
                    'browser_download_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.1.5/patch-1.1.5.zip',
                    'size' => 450000,
                ],
            ],
        ], 200),
    ]);

    $patch = $service->checkForPatch('1.1.4');

    expect($patch)->not->toBeNull()
        ->and($patch['version'])->toBe('1.1.5')
        ->and($patch['patch_url'])->toBe('https://github.com/baguidevos/links-vault/releases/download/v1.1.5/patch-1.1.5.zip')
        ->and($patch['is_hot_patch'])->toBeTrue()
        ->and($patch['patch_size_human'])->toBe('439.5 Ko');
});

test('desktop hot patch service returns null if no patch asset exists', function () {
    $service = new DesktopHotPatchService;

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.1.5',
            'assets' => [
                [
                    'name' => 'LinksVault-1.1.5-setup.exe',
                    'browser_download_url' => 'https://example.com/setup.exe',
                    'size' => 155000000,
                ],
            ],
        ], 200),
    ]);

    $patch = $service->checkForPatch('1.1.4');
    expect($patch)->toBeNull();
});

test('desktop hot patch service applies safe patch correctly', function () {
    $service = new DesktopHotPatchService;

    $tempZip = storage_path('app/patch_temp/test_apply.zip');
    File::makeDirectory(dirname($tempZip), 0755, true, true);

    $testFileRel = 'app/test_dummy_patch_file.php';
    $targetFile = base_path($testFileRel);

    $zip = new ZipArchive;
    $zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $manifest = [
        'app' => 'com.linksvault.desktop',
        'from_version' => '1.1.4',
        'to_version' => '1.1.5',
        'has_migrations' => false,
        'files' => [
            ['path' => $testFileRel],
        ],
        'deleted_files' => [],
    ];
    $zip->addFromString('manifest.json', json_encode($manifest));
    $zip->addFromString($testFileRel, '<?php echo "hot patched";');
    $zip->close();

    $result = $service->applyPatch($tempZip);

    expect($result['success'])->toBeTrue()
        ->and($result['version'])->toBe('1.1.5')
        ->and(File::exists($targetFile))->toBeTrue()
        ->and(File::get($targetFile))->toBe('<?php echo "hot patched";')
        ->and(Cache::get('nativephp_installed_patch_version'))->toBe('1.1.5');

    // Cleanup
    if (File::exists($targetFile)) {
        File::delete($targetFile);
    }
});

test('desktop hot patch service rejects unsafe paths with traversal or outside allowed prefixes', function () {
    $service = new DesktopHotPatchService;

    $tempZip = storage_path('app/patch_temp/unsafe_test.zip');
    File::makeDirectory(dirname($tempZip), 0755, true, true);

    $zip = new ZipArchive;
    $zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $manifest = [
        'from_version' => '1.1.4',
        'to_version' => '1.1.5',
        'files' => [
            ['path' => '.env'],
        ],
        'deleted_files' => [],
    ];
    $zip->addFromString('manifest.json', json_encode($manifest));
    $zip->addFromString('.env', 'HACKED=true');
    $zip->close();

    expect(fn () => $service->applyPatch($tempZip))->toThrow(RuntimeException::class);

    File::delete($tempZip);
});

test('app version updater component detects and displays hot patch button', function () {
    config()->set('nativephp.version', '1.1.4');

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.1.5',
            'assets' => [
                [
                    'name' => 'patch-1.1.5.zip',
                    'browser_download_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.1.5/patch-1.1.5.zip',
                    'size' => 350000,
                ],
            ],
        ], 200),
    ]);

    Livewire::test(AppVersionUpdater::class)
        ->call('checkForUpdates')
        ->assertSet('patchAvailable.version', '1.1.5')
        ->assertSee('Installer le patch v1.1.5')
        ->assertSee('341.8 Ko');
});
