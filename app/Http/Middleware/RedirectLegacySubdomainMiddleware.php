<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacySubdomainMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Redirect any legacy requests from 'yeni.dosttv.com' permanently (301) to 'dosttv.com'.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($host === 'yeni.dosttv.com' || str_ends_with($host, '.yeni.dosttv.com')) {
            $path = trim($request->getPathInfo(), '/');

            if ($path === 'canli-tv' || $path === 'canli-yayin') {
                $targetUrl = 'https://dosttv.com/canli-yayin';
            } else {
                $targetUrl = 'https://dosttv.com' . $request->getRequestUri();
            }

            return redirect()->to($targetUrl, 301);
        }

        return $next($request);
    }
}
