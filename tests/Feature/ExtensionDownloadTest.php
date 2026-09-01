<?php

test('it can download the chrome extension zip archive', function () {
    $response = $this->get(route('extension.download'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition', 'attachment; filename=links-vault-clipper.zip');
});

test('it can run clipper:package artisan command to generate store package', function () {
    $this->artisan('clipper:package')
        ->assertSuccessful();

    $zipPath = base_path('dist/links-vault-clipper-v1.0.0.zip');
    expect(file_exists($zipPath))->toBeTrue();
});
