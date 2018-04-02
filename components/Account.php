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
            'description' => 'Passwordless login and account manager'
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
                'default'     => ''
            ],
            'allow_registration' => [
                'title' => 'Allow for registration',
                'description' => 'If disabled, only existing users can request a login',
                'type' => 'checkbox',
                'default' => 0
            ]
        ];
    }

    public function getRedirectOptions()
    {
        return [''=>'- refresh page -', '0' => '- no redirect -'] + Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
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
        } catch(ApplicationException $e) {
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
    // Ajax
    //

    /**
     * Sends an authentication email to the user
     * @throws ApplicationException
     * @return October AJAX response
     */
    public function onRequestLogin() {

        // Validate the email
        $email = ['email' => Input::get('email')];
        $validator = Validator::make($email, ['email' => 'required|email']);
        if ($validator->fails()) {
            return Redirect::to($this->currentPageUrl())->withErrors($validator);
        }

        // Get user
        if (! $user = $this->model::where($email)->first()) {
            if ($this->property('allow_registration')) {
                $user = $this->model::create($email);
            } else {
                return Response::json('Sorry, this email is not registered.', 403);
            }
        }

        // Generate token
        $token = Token::generate($user, 30, 'login');
        $base_url = $this->currentPageUrl();
        $authentication_url = $base_url . '?token=' . $token;

        // Send invitation email
        Mail::sendTo(
            $user->email,
            $this->property('mail_template'),
            compact('base_url', 'authentication_url')
        );

        return ['#passwordless-login-form' => $this->renderPartial('@invited', compact('base_url'))];
    }

    /**
     * Signs out
     */
    public function onLogout() {
        $this->auth::logout();

        return Redirect::refresh();
    }
}
