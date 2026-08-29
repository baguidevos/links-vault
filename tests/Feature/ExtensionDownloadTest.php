<?php

test('it can download the chrome extension zip archive', function () {
    $response = $this->get(route('extension.download'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition', 'attachment; filename=links-vault-clipper.zip');
});
