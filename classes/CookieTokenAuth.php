<?php

namespace Nocio\Passwordless\Classes;

use Cookie;
use Closure;
use Nocio\Passwordless\Models\Token;
use October\Rain\Exception\ApplicationException;

class CookieTokenAuth
{

    const COOKIE_NAME = 'auth_token';

    /**
     * @param $user
     * @param int $expires Minutes, default = 60 * 24 * 30 = 1 month
     */
    public static function login($user, $expires = 43200) {
        $token = Token::generate($user, $expires, 'auth');
        Cookie::queue(
		self::COOKIE_NAME, 
		$token, 
		$expires,
		'/', '',
		env('COOKIE_TOKEN_SECURE', true) ? true : false, // secure
            	true // httpOnly
	);
    }

    public static function check() {
        return ! is_null(self::getUser());
    }

    public static function getUser() {
        if (! $token = Cookie::get(self::COOKIE_NAME)) {
            return null;
        }

        try {
            return Token::parse($token, false, 'auth');
        } catch (ApplicationException $e) {
            return null;
        }
    }

    public static function logout() {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return app(\Illuminate\Cookie\Middleware\EncryptCookies::class)->handle($request, function ($request) use ($next) {
            try {
                if (! $token = Cookie::get(self::COOKIE_NAME)) {
                    throw new ApplicationException('No token provided');
                }
                Token::parse($token, false, 'auth');
            } catch(ApplicationException $e) {
                return response('Unauthorized. ' . $e->getMessage(), 401);
            }

            return $next($request);
        });
    }

}
