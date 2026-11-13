<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TimurTurdyev\SimpleCart\Identity\CookieIdentity;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;

/**
 * Issues the cart cookie on the first page view: the response of the first
 * write may be aborted when the visitor navigates away, and the cart is lost.
 */
final readonly class IssueCartCookie
{
    public function __construct(
        private IdentityManager $identities,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $identity = $this->identities->driver();

            if ($identity instanceof CookieIdentity) {
                $identity->issue();
            }
        }

        return $next($request);
    }
}
