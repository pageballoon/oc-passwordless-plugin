<?php

namespace Nocio\Passwordless\Components;


use Cms\Classes\ComponentBase;
use October\Rain\Exception\ApplicationException;
use Nocio\Passwordless\Models\Token;
use Cms\Classes\Page;
use Input;
use Cookie;
use Redirect;
use Validator;
use Mail;
use Response;

class Account extends ComponentBase
{

    /**
     * @var Authentication manager
     */
    protected $auth;

    /**
     * @var Authentication model
     */
    protected $model;

    /**
     * Component details
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name' => 'Account',
            'description' => 'Passwordless login and account manager',
            'graphql' => true
        ];
    }

    /**
     * Register properties
     * @return array
     */
    public function defineProperties()
    {
        return [
            'model' => [
                'title' => 'Auth model',
                'description' => 'User model the form authenticates',
                'type' => 'string',
                'required' => true,
                'default' => 'Rainlab\User\Models\User'
            ],
            'auth' => [
                'title' => 'Auth provider',
                'description' => 'Class or facade that manages the auth state',
                'type' => 'string',
                'default' => 'Rainlab\User\Facades\Auth'
            ],
            'mail_template' => [
                'title' => 'Login mail template',
                'description' => 'The mail template that will be send to the user',
                'type' => 'string',
                'default' => 'nocio.passwordless::mail.login'
            ],
            'redirect' => [
                'title'       => 'Redirect to',
                'description' => 'Page name to redirect to after sign in',
                'type'        => 'dropdown',
                'default'     => '',
                'graphql' => false
            ],
            'allow_registration' => [
                'title' => 'Allow for registration',
                'description' => 'If disabled, only existing users can request a login',
                'type' => 'checkbox',
                'default' => 0
            ],
            'api' => [
                'title' => 'Enable API',
                'description' => 'Component will expose API endpoint \'?api\' to query the authentication status',
                'type' => 'checkbox',
                'default' => 0,
                'graphql' => false
            ]
        ];
    }

    public function getRedirectOptions()
    {
        return [''=>'- refresh page -', '0' => '- no redirect -'] + Page::sortBy('baseFileName')->lists('baseFileName', 'url');
    }

    public function init() {
        $this->auth = $this->property('auth');
        if (!class_exists($this->auth)) {
            throw new ApplicationException(
                "The auth manager '$this->auth' could not be found. " .
                "Please check the component settings."
            );
        }

        $this->model = '\\' . $this->property('model');
        if (!class_exists($this->model)) {
            throw new ApplicationException(
                "The user model '$this->model' could not be found. " .
                "Please check the component settings."
            );
        }
    }

    public function onRun() {
        if ($response = $this->api()) {
            return $response;
        }

        if ($response = $this->login()) {
            return $response;
        }

        if ($redirect = Input::get('redirect')) {
            Cookie::queue('passwordless_redirect', $redirect, 60 * 24);
        }

        $this->page['user'] = $this->user();
    }

    public function login() {
        if ($this->auth::check()) {
            return false;
        }

        if (! $token = Input('token')) {
            return false;
        }

        try {
            $user = Token::parse($token, true, 'login');
            $this->auth::login($user);
            $this->page['error'] = false;
            return $this->processRedirects();
        } catch(\Exception $e) {
            $this->page['error'] =  $e->getMessage();
        }
    }

    public function processRedirects() {
        if ($intended = Cookie::get('passwordless_redirect')) {
            Cookie::queue(Cookie::forget('passwordless_redirect'));
            // make redirection host safe
            $url = parse_url(urldecode($intended));
            return Redirect::to(url($url['path']));
        }

        switch($default = $this->property('redirect')) {
            case '0': break;
            case '': return Redirect::to($this->currentPageUrl());
            default: return Redirect::to($default);
        }

    }

    public function sendLoginEmail($user, $base_url) {
        // Generate token
        $token = Token::generate($user, 30, 'login');
        $authentication_url = $base_url . '?token=' . $token;
        $email = $user->email;

        // Send invitation email
        Mail::queue(
            $this->property('mail_template'),
            compact('base_url', 'authentication_url'),
            function ($message) use ($email) {
                $message->to($email);
            }
        );
    }

    //
    // Properties
    //

    /**
     * Returns the logged in user, if available
     */
    public function user()
    {
        if (!$this->auth::check()) {
            return null;
        }

        return $this->auth::getUser();
    }


    //
    // API
    //

    public function api() {
        if (! $this->property('api')) {
            return false;
        }

        if (! $query = Input::get('api')) {
            return false;
        }

        if (! $this->auth::check()) {
            return response('Unauthorized. ', 401);
        }

        switch ($query) {
            case 'info':
                return $this->apiInfo();
                break;
            default:
                return false;
        }
    }

    public function apiInfo() {
        $user = $this->auth::getUser();

        return Response::json([
            'data' => $user
        ]);
    }

    /**
     * Returns a random string
     */
    public function generateRandomString($length = 64, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        if ($length < 1) {
            throw new \RangeException("Length must be a positive integer");
        }
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces []= $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    //
    // Ajax
    //

    /**
     * Sends an authentication email to the user
     * @return array October AJAX response
     */
    public function onRequestLogin() {
        $base_url = $this->currentPageUrl();

        // Validate the email
        $email = ['email' => Input::get('email')];
        $validator = Validator::make($email, ['email' => 'required|email']);
        if ($validator->fails()) {
            return Redirect::to($this->currentPageUrl())->withErrors($validator);
        }


        // Get user
        if (! $user = $this->model::where($email)->first()) {
            if ($this->property('allow_registration')) {
                // $user = $this->model::create($email);

                $random_string = $this->generateRandomString();
                /*
                $user = $this->auth::register([
                    'email' => Input::get('email'),
                    'password' => $random_string,
                    'password_confirmation' => $random_string,
                ], true); // force auto activation
                */
                $user = $this->model::create([
                  'email' => Input::get('email'),
                  'password' => $random_string,
                  'password_confirmation' => $random_string
                ]);
                $user->attemptActivation($user->activation_code);

            } else {
                return ['#passwordless-login-form' => $this->renderPartial('@invited', compact('base_url'))];
            }
        }


        $this->sendLoginEmail($user, $base_url);

        return ['#passwordless-login-form' => $this->renderPartial('@invited', compact('base_url'))];
    }

    /**
     * Signs out
     */
    public function onLogout() {
        $this->auth::logout();

        return Redirect::refresh();
    }

    //
    // GraphQL
    //

    public function resolvePasswordlessUser() {
        return $this->auth::getUser();
    }

    public function resolvePasswordlessLogout() {
        if ($user = $this->auth::getUser()) {
            $this->auth::logout();
            return $user;
        }

        return null;
    }

    public function resolvePasswordlessLoginRequest($root, $args) {
        $email = ['email' => $args['email']];
        $validator = Validator::make($email, ['email' => 'required|email']);
        if ($validator->fails()) {
            return 1; // invalid email
        }

        if (! $user = $this->model::where($email)->first()) {
            if ($this->property('allow_registration')) {
                $user = $this->model::create($email);
            } else {
                return 2; // email not registered
            }
        }

        $this->sendLoginEmail($user, url($args['endpoint']));

        return 0; // success
    }

    public function resolvePasswordlessLogin($root, $args) {
        try {
            $user = Token::parse($args['token'], true, 'login');
            $this->auth::login($user);
            return $user;
        } catch(\Exception $e) {
            return null;
        }
    }

}
