<?php

namespace Spatie\ResponseCache\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Spatie\ResponseCache\ResponseCache;
use Spatie\ResponseCache\Events\CacheMissed;
use Symfony\Component\HttpFoundation\Response;
use Spatie\ResponseCache\Events\ResponseCacheHit;

class CacheResponse
{
    /** @var \Spatie\ResponseCache\ResponseCache */
    protected $responseCache;

    public function __construct(ResponseCache $responseCache)
    {
        $this->responseCache = $responseCache;
    }

    public function handle(Request $request, Closure $next, $lifetimeInSeconds = null): Response
    {
        if ($this->responseCache->enabled($request)) {
            if ($this->responseCache->hasBeenCached($request)) {
                event(new ResponseCacheHit($request));

                $response = $this->responseCache->getCachedResponseFor($request);
                $cachedToken = $this->responseCache->getCachedCsrfTokenFor($request);

                if ($response->getContent()) {
                    $response->setContent(str_replace($cachedToken, csrf_token(), $response->getContent()));
                }

                return $response;
            }
        }

        $response = $next($request);

        if ($this->responseCache->enabled($request)) {
            if ($this->responseCache->shouldCache($request, $response)) {
                $this->responseCache->cacheResponse($request, $response, $lifetimeInSeconds);
            }
        }

        event(new CacheMissed($request));

        return $response;
    }
}
