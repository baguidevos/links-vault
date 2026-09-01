<?php

use App\Ai\Agents\TagFinderAgent;
use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\ExtensionDownloadController;
use App\Http\Controllers\GlmController;
use App\Http\Controllers\GoogleDriveAuthController;
use App\Http\Controllers\LinkShareController;
use App\Http\Controllers\LinkVisitController;
use App\Services\GlmService;
use App\Services\WebPageMetadataService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/login', '/app/login')->name('login');

// Téléchargement du package de l'extension Chrome
Route::get('/extension/download', ExtensionDownloadController::class)
    ->name('extension.download');

Route::get('/test-email', function () {
    $to = request('to', 'test@example.com');

    try {
        Mail::raw('Ceci est un email de test envoyé avec succès depuis votre application Laravel !', function ($message) use ($to) {
            $message->to($to)
                ->subject('Test d\'envoi d\'email - '.config('app.name'));
        });

        return response()->json([
            'status' => 'success',
            'message' => "Email de test envoyé avec succès à : {$to}",
            'config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => "Échec de l'envoi de l'email : ".$e->getMessage(),
        ], 500);
    }
});

// Routes pour GLM API
Route::prefix('glm')->group(function () {
    Route::post('/chat', [GlmController::class, 'simpleChat'])->name('glm.chat');
    Route::post('/chat/history', [GlmController::class, 'chatWithHistory'])->name('glm.chat.history');
    Route::post('/chat/stream', [GlmController::class, 'chatStream'])->name('glm.chat.stream');
});

// Route de tracking pour les liens partagés
Route::get('/share/{token}', [LinkShareController::class, 'redirect'])
    ->name('links.share.redirect');

// Route de tracking et redirection directe pour les liens
Route::get('/links/{link}/visit', LinkVisitController::class)
    ->name('links.visit');

Route::get('/team-invitations/{code}/accept', AcceptInvitationController::class)
    ->middleware(['web', 'signed'])
    ->name('filateams.invitations.accept');

// Routes OAuth Google Drive
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/auth/google-drive/redirect', [GoogleDriveAuthController::class, 'redirect'])
        ->name('auth.google-drive.redirect');
    Route::get('/auth/google-drive/callback', [GoogleDriveAuthController::class, 'callback'])
        ->name('auth.google-drive.callback');
    Route::post('/auth/google-drive/disconnect', [GoogleDriveAuthController::class, 'disconnect'])
        ->name('auth.google-drive.disconnect');
});

Route::get('/test', function () {

    // $response = app(GlmService::class)->chatSimple('Hello');
    // $answer = app(GlmService::class)->extractResponse($response);

    // dump($answer, $response);

    $process = Process::run('youtube_transcript_api ulJTCVm3wXo');

    $output = $process->output();

    // supprimer b""" au début et """ à la fin
    $output = preg_replace('/^b"""|"""$/', '', trim($output));

    $json = str_replace("'", '"', $output);
    $data = json_decode($json, true);
    $fullText = '';

    $webpageData = (new WebPageMetadataService)->fetchMetadata('https://docs.google.com/document/d/11uc-no4tXCTS9QA7USLGFiyIiOAjx6BvEyR7g1EpL0Q/edit?usp=sharing');

    dump($webpageData);
    // foreach ($data[0] as $segment) {
    //     $fullText .= $segment['text'] . ' ';
    // }

    // $fullText = trim($fullText);

    // echo $fullText;

    $agent = new TagFinderAgent;
    $agentReponse = $agent->prompt('trouve les tags pour une description', provider: 'nvidia', model: 'minimaxai/minimax-m2.7');

    dump($output, $data);

    dd($agentReponse->text);

    // return view('welcome');
    // return $agent->then(function (StreamedAgentResponse $response) {
    //         // $response->text, $response->events, $response->usage...

    //         dd($response->text);
    //     });;
});
