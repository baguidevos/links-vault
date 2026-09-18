<?php

namespace App\Console\Commands;

use App\Actions\LinkActions\CreateLinkAction;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                } elseif (preg_match('#^GET /context(?:\?team_id=(\d+))?#i', $firstLine, $contextMatches)) {
                    $requestedTeamId = ! empty($contextMatches[1]) ? (int) $contextMatches[1] : null;
                    $this->handleContextRequest($client, $requestedTeamId);
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

    protected function handleContextRequest($client, ?int $requestedTeamId = null)
    {
        try {
            $user = User::first();
            if (! $user) {
                $this->sendJsonResponse($client, [
                    'user' => null,
                    'teams' => [],
                    'categories' => [],
                    'folders' => [],
                    'tags' => [],
                ]);

                return;
            }

            $teams = collect();
            if (method_exists($user, 'teams')) {
                try {
                    $teams = $user->teams()->get(['teams.id', 'teams.name', 'teams.slug', 'teams.is_personal']);
                } catch (\Throwable $e) {
                }
            }

            $teamId = $requestedTeamId ?: ($user->current_team_id ?? $teams->first()?->id);

            $categoriesQuery = Category::query();
            if ($teamId) {
                $categoriesQuery->where('team_id', $teamId);
            } else {
                $categoriesQuery->where('user_id', $user->id);
            }
            $categories = $categoriesQuery
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'uuid', 'team_id', 'name', 'slug', 'color', 'icon']);

            $foldersQuery = Folder::query();
            if ($teamId) {
                $foldersQuery->where('team_id', $teamId);
            } else {
                $foldersQuery->where('user_id', $user->id);
            }
            $folders = $foldersQuery
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'uuid', 'team_id', 'category_id', 'name', 'slug', 'color', 'icon']);

            $tagsQuery = Tag::query();
            if ($teamId) {
                $tagsQuery->where('team_id', $teamId);
            } else {
                $tagsQuery->where('user_id', $user->id);
            }
            $tags = $tagsQuery
                ->orderBy('name')
                ->get(['id', 'uuid', 'team_id', 'name', 'slug']);

            $this->sendJsonResponse($client, [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'current_team_id' => $teamId,
                'teams' => $teams,
                'categories' => $categories,
                'folders' => $folders,
                'tags' => $tags,
            ]);
        } catch (\Throwable $e) {
            Log::error('Extension IPC context error: '.$e->getMessage());
            $this->sendJsonResponse($client, ['status' => 'error', 'message' => $e->getMessage()], 500);
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

            // Resolve folder_id
            $folderId = null;
            if (! empty($data['folder_id'])) {
                $folderId = Folder::where('id', $data['folder_id'])->value('id');
            }
            if (! $folderId && ! empty($data['folder_uuid'])) {
                $folderId = Folder::where('uuid', $data['folder_uuid'])->value('id');
            }
            if (! $folderId && ! empty($data['folder_name'])) {
                $folderId = Folder::where('user_id', $user->id)->where('name', $data['folder_name'])->value('id');
            }

            // Resolve category_id
            $categoryId = null;
            if (! empty($data['category_id'])) {
                $categoryId = Category::where('id', $data['category_id'])->value('id');
            }
            if (! $categoryId && ! empty($data['category_uuid'])) {
                $categoryId = Category::where('uuid', $data['category_uuid'])->value('id');
            }
            if (! $categoryId && ! empty($data['category_name'])) {
                $categoryId = Category::where('user_id', $user->id)->where('name', $data['category_name'])->value('id');
            }
            if (! $categoryId && $folderId) {
                $folder = Folder::find($folderId);
                if ($folder && $folder->category_id) {
                    $categoryId = $folder->category_id;
                }
            }

            $urlHash = hash('sha256', $data['url']);
            $existing = Link::where('user_id', $user->id)
                ->where('url_hash', $urlHash)
                ->first();

            if ($existing) {
                $updates = [];
                if ($folderId !== null && $existing->folder_id !== $folderId) {
                    $updates['folder_id'] = $folderId;
                }
                if ($categoryId !== null && $existing->category_id !== $categoryId) {
                    $updates['category_id'] = $categoryId;
                }
                if (! empty($data['title']) && empty($existing->title)) {
                    $updates['title'] = $data['title'];
                }
                if (! empty($data['description']) && empty($existing->description)) {
                    $updates['description'] = $data['description'];
                }
                if (isset($data['is_favorite'])) {
                    $updates['is_favorite'] = (bool) $data['is_favorite'];
                }

                if (! empty($updates)) {
                    $existing->update($updates);
                }

                if (! empty($data['tags']) && is_array($data['tags'])) {
                    $tagIds = [];
                    foreach ($data['tags'] as $tagName) {
                        $tagName = trim((string) $tagName);
                        if (! empty($tagName)) {
                            $tag = Tag::firstOrCreate([
                                'user_id' => $user->id,
                                'team_id' => $existing->team_id,
                                'slug' => Str::slug($tagName),
                            ], [
                                'name' => $tagName,
                            ]);
                            $tagIds[] = $tag->id;
                        }
                    }
                    if (! empty($tagIds)) {
                        $existing->tags()->syncWithoutDetaching($tagIds);
                    }
                }

                $this->sendJsonResponse($client, [
                    'status' => 'saved',
                    'message' => 'Lien déjà présent, dossier mis à jour avec succès !',
                    'is_duplicate' => true,
                    'link_id' => $existing->id,
                ]);

                if ($this->output) {
                    $this->info("Existing link updated via Extension IPC: {$data['url']} (Folder ID: {$folderId})");
                }

                return;
            }

            $teamId = $data['team_id'] ?? ($user->current_team_id ?? $user->personalTeam()?->id);

            // Create the link using the action
            $linkData = [
                'url' => $data['url'],
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'user_id' => $user->id,
                'team_id' => $teamId,
                'folder_id' => $folderId,
                'category_id' => $categoryId,
                'tags' => $data['tags'] ?? [],
                'content_type' => $data['content_type'] ?? null,
                'favicon_url' => $data['favicon_url'] ?? null,
                'thumbnail_url' => $data['thumbnail_url'] ?? null,
                'is_favorite' => ! empty($data['is_favorite']),
                'generate_ai_summary' => ! empty($data['generate_ai_summary']),
                'visibility' => 'private',
            ];

            CreateLinkAction::execute($linkData);

            $this->sendJsonResponse($client, ['status' => 'saved', 'message' => 'Link saved successfully']);
            if ($this->output) {
                $this->info("Link saved via Extension IPC: {$data['url']} (Folder ID: {$folderId})");
            }
        } catch (\Throwable $e) {
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
        $response .= "Access-Control-Allow-Private-Network: true\r\n";
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
        $response .= "Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With\r\n";
        $response .= "Access-Control-Allow-Private-Network: true\r\n";
        $response .= "Connection: close\r\n\r\n";

        fwrite($client, $response);
    }

    protected function sendResponse($client, int $statusCode, string $statusText, string $body)
    {
        $response = "HTTP/1.1 $statusCode $statusText\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Access-Control-Allow-Private-Network: true\r\n";
        $response .= "Content-Type: text/plain\r\n";
        $response .= 'Content-Length: '.strlen($body)."\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $body;

        fwrite($client, $response);
    }
}
