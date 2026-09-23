<?php

namespace App\Http\Middleware;

use App\Models\Audience;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarquerAudiencesRatees
{
    public function handle(Request $request, Closure $next): Response
    {
        Audience::marquerRatees();

        return $next($request);
    }
}
