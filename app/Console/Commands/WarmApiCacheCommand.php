<?php

namespace App\Console\Commands;

use App\Http\Controllers\GiftController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\PostFeedController;
use App\Http\Controllers\ReelsFeedController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\WalletController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WarmApiCacheCommand extends Command
{
    protected $signature = 'cache:warm {--stop-on-error : Stop warming if an endpoint fails}';

    protected $description = 'Warm hot API caches for feeds, rooms, gifts, and music';

    public function handle(): int
    {
        $warmers = [
            ['label' => 'posts feed', 'controller' => PostFeedController::class, 'method' => 'feed', 'uri' => '/api/v1/posts/feed', 'query' => ['page' => 1]],
            ['label' => 'reels feed', 'controller' => ReelsFeedController::class, 'method' => 'feed', 'uri' => '/api/v1/reels/feed', 'query' => ['page' => 1]],
            ['label' => 'reels trending', 'controller' => ReelsFeedController::class, 'method' => 'trending', 'uri' => '/api/v1/reels/trending', 'query' => []],
            ['label' => 'reels discover', 'controller' => ReelsFeedController::class, 'method' => 'discover', 'uri' => '/api/v1/reels/discover', 'query' => []],
            ['label' => 'rooms list', 'controller' => RoomController::class, 'method' => 'index', 'uri' => '/api/v1/rooms', 'query' => []],
            ['label' => 'gifts list', 'controller' => WalletController::class, 'method' => 'gifts', 'uri' => '/api/v1/gifts', 'query' => []],
            ['label' => 'music trending', 'controller' => MusicController::class, 'method' => 'trending', 'uri' => '/api/v1/music/trending', 'query' => []],
        ];

        foreach ($warmers as $warmer) {
            try {
                $response = $this->invokeController($warmer['controller'], $warmer['method'], $warmer['uri'], $warmer['query']);

                if ($response->getStatusCode() >= 400) {
                    $this->error(sprintf('Warm failed for %s [%d]', $warmer['label'], $response->getStatusCode()));
                    if ($this->option('stop-on-error')) {
                        return self::FAILURE;
                    }

                    continue;
                }

                $this->info(sprintf('Warmed %s [%d]', $warmer['label'], $response->getStatusCode()));
            } catch (\Throwable $e) {
                $this->error(sprintf('Warm exception for %s: %s', $warmer['label'], $e->getMessage()));
                if ($this->option('stop-on-error')) {
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }

    private function invokeController(string $controllerClass, string $method, string $uri, array $query): Response
    {
        $request = Request::create($uri, 'GET', $query);
        $request->setUserResolver(static fn () => null);

        app()->instance('request', $request);

        /** @var Response $response */
        $response = app()->call([app($controllerClass), $method], [
            'request' => $request,
        ]);

        return $response;
    }
}
