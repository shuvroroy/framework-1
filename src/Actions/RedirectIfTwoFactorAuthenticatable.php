<?php

namespace Shopper\Framework\Actions;

use function in_array;
use Illuminate\Http\Request;
use Shopper\Framework\Shopper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Shopper\Framework\Services\TwoFactor\LoginRateLimiter;
use Shopper\Framework\Services\TwoFactor\TwoFactorAuthenticatable;

class RedirectIfTwoFactorAuthenticatable
{
    /**
     * The guard implementation.
     *
     * @var \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected $guard;

    /**
     * The login rate limiter instance.
     *
     * @var \Shopper\Framework\Services\TwoFactor\LoginRateLimiter
     */
    protected $limiter;

    /**
     * Create a new controller instance.
     */
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter)
    {
        $this->guard = $guard;
        $this->limiter = $limiter;
    }

    /**
     * Handle the incoming request.
     *
     * @param callable $next
     *
     * @throws ValidationException
     */
    public function handle(Request $request, $next)
    {
        $user = $this->validateCredentials($request);

        if (optional($user)->two_factor_secret &&
            in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    /**
     * Attempt to validate the incoming credentials.
     *
     * @throws ValidationException
     */
    protected function validateCredentials(Request $request)
    {
        $model = $this->guard->getProvider()->getModel();

        return tap($model::where(Shopper::username(), $request->{Shopper::username()})->first(), function ($user) use ($request) {
            if (! $user || ! Hash::check($request->password, $user->password)) {
                $this->throwFailedAuthenticationException($request);
            }
        });
    }

    /**
     * Throw a failed authentication validation exception.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function throwFailedAuthenticationException(Request $request)
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([Shopper::username() => [trans('auth.failed')], ]);
    }

    /**
     * Get the two factor authentication enabled response.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function twoFactorChallengeResponse(Request $request, $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->filled('remember'),
        ]);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('shopper.two-factor.login');
    }
}
