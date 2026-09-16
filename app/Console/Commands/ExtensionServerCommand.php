<?php

namespace App\Console\Commands;

use App\Actions\LinkActions\CreateLinkAction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtensionServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'extension:server {--port=41234}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a lightweight local HTTP server for the Chrome Extension to communicate with the Desktop App.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $port = $this->option('port');
        $host = '127.0.0.1';

        $socket = stream_socket_server("tcp://$host:$port", $errno, $errstr);

        if (! $socket) {
            $this->error("Failed to start extension IPC server: $errstr ($errno)");
            Log::error("Extension IPC server failed to start: $errstr");

            return Command::FAILURE;
        }

        $this->info("Extension IPC server listening on http://$host:$port");
        Log::info("Extension IPC server started on port $port");

        while (true) {
            // Non-blocking accept if we want to do other things, but blocking is fine here in a dedicated process.
            $client = @stream_socket_accept($socket, -1);
            if ($client) {
                $request = '';
                // Read headers first
                while (! feof($client)) {
                    $request .= fgets($client, 1024);
                    if (str_ends_with($request, "\r\n\r\n")) {
                        break;
                    }
                }

                $lines = explode("\r\n", $request);
                $firstLine = $lines[0] ?? '';

                // Read body if Content-Length is present
                $body = '';
                if (preg_match('/Content-Length:\s*(\d+)/i', $request, $matches)) {
                    $length = (int) $matches[1];
                    if ($length > 0) {
                        $body = fread($client, $length);
                    }
                }

                if (str_starts_with($firstLine, 'GET /ping')) {
                    $this->sendJsonResponse($client, ['status' => 'ok', 'app' => 'LinksVault']);
                } elseif (str_starts_with($firstLine, 'POST /save')) {
                    $this->handleSaveRequest($client, $body);
                } elseif (str_starts_with($firstLine, 'OPTIONS')) {
                    $this->sendCorsHeaders($client);
                } else {
                    $this->sendResponse($client, 404, 'Not Found', '404 Not Found');
                }

                fclose($client);
            }
        }
    }

    protected function handleSaveRequest($client, $body)
    {
        $data = json_decode($body, true);
        if (! $data || empty($data['url'])) {
            $this->sendJsonResponse($client, ['status' => 'error', 'message' => 'Invalid payload or missing URL'], 400);

            return;
        }

        try {
            // Get the first user (Desktop app is typically single user)
            $user = User::first();
            if (! $user) {
                $this->sendJsonResponse($client, ['status' => 'error', 'message' => 'No user found in the application'], 403);

                return;
            }

            // Create the link using the action
            $linkData = [
                'url' => $data['url'],
                'title' => $data['title'] ?? null,
                'user_id' => $user->id,
                'team_id' => $user->current_team_id ?? $user->personalTeam()?->id,
                'visibility' => 'private',
            ];

            CreateLinkAction::execute($linkData);

            $this->sendJsonResponse($client, ['status' => 'saved', 'message' => 'Link saved successfully']);
            $this->info('Link saved via Extension IPC: '.$data['url']);
        } catch (\Exception $e) {
            Log::error('Extension IPC save error: '.$e->getMessage());
            $this->sendJsonResponse($client, ['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    protected function sendJsonResponse($client, array $data, int $statusCode = 200)
    {
        $statusText = $statusCode === 200 ? 'OK' : ($statusCode === 400 ? 'Bad Request' : 'Error');
        $body = json_encode($data);

        $response = "HTTP/1.1 $statusCode $statusText\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Content-Type: application/json\r\n";
        $response .= 'Content-Length: '.strlen($body)."\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $body;

        fwrite($client, $response);
    }

    protected function sendCorsHeaders($client)
    {
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
        $response .= "Access-Control-Allow-Headers: Content-Type\r\n";
        $response .= "Connection: close\r\n\r\n";

        fwrite($client, $response);
    }

    protected function sendResponse($client, int $statusCode, string $statusText, string $body)
    {
        $response = "HTTP/1.1 $statusCode $statusText\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Content-Type: text/plain\r\n";
        $response .= 'Content-Length: '.strlen($body)."\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $body;

        fwrite($client, $response);
    }
}
