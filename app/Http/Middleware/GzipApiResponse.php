<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GzipApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (!$request->expectsJson() && !$request->is('api/*')) {
            return false;
        }

        if (!str_contains((string) $request->headers->get('Accept-Encoding', ''), 'gzip')) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        $content = $response->getContent();
        if (!is_string($content) || strlen($content) < 1024) {
            return false;
        }

        return true;
    }
}
