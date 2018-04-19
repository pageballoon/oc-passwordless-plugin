This plugin brings passwordless user authentication to OctoberCMS. Instead of filling username and password, frontend users provide their email address and receive a link with login token that registers and authenticates them on the site. Passwordless authentication is [secure](https://auth0.com/blog/is-passwordless-authentication-more-secure-than-passwords/) and can greatly improve the user experience as it simplifies the registration and login process where many users dread having to fill out forms and go through a rigorous registration process.

**Features**

- Works well with Rainlab.User plugin as well as custom authentication systems
- Login tokens are valid only once and expire after 30 minutes for increased security while being automatically cleaned from the system
- Supports redirection after login
- Optional JSON API to consume user details
- Open source to allow and encourage security inspection
- Developer friendly and highly customizable
- Includes a cookie-token authentication method that can minimize repeated logins

### Additional information

In future, the plugin might be extended to allow for

- Option to allow for change of email
- Improved JSON API
- Support stateless JWT-based authentication
- Backend logins
- ...

If you find this plugin useful, please consider donating to support its further development.

---

The plugin provides an *Account* component that is similiar to Rainlab.User's account component.

### The Account component

The account component provides the main functionality and can be included in any CMS page that should serve as login endpoint.

The Account component has the following properties:

* `model` - [string] specifies the user model the form authenticates (must have an email field). Defaults to `Rainlab\User\Models\User`
* `auth` - [string] specifies a class of facade that manages the authentication state (see below). Defaults to `Rainlab\User\Facades\Auth`
* `mail_template` [string] the email template of the login mail. Defaults to `nocio.passwordless::mail.login`
* `redirect` [dropdown] specifies a page name to redirect to after sign in (can be overwritten, see below)
* `allow_registration` - [checkbox] if disabled, only existing users can request a login
* `api` - [checkbox] if enabled, the component will expose an API endpoint ``?api`` to query the authentication status

The component will display the email login form and -- if the user is logged in -- display account information. Note that the component requires the ajax framework to work.

### The authentication manager

The authentication manager that can be specified in the Account component is a class or facade that manages the user authentication through a standardised API:

- ``login($user) {}`` - signs in `$user`
- ``check() {}`` - checks whether user is authenticated (returns boolean)
- ``getUser() {}`` - returns the authenticated user or ``null`` if not authenticated
- ``logout() {}`` - logs the user out

Available auth managers:

*Rainlab\User\Facades\Auth*

Auth manager provided by the Rainlab.User plugin.

*Nocio\Passwordless\Classes\CookieTokenAuth*

Auth manager that stores authentication state as token in a httpOnly cookie. The user stays authenticated until the cookie is deleted or the token expires. The manager can be used as middleware to protect endpoints that require authentication. The authentication method is particuarly useful for RESTful APIs. Note that the cookie will only be transfered via secure https connections. If you want to allow http connections you can set ``COOKIE_TOKEN_SECURE=true`` in the ``.env`` file.

*Custom manager*

To cater for custom authentication mechanism you can implement your own auth manager that exhibits the given API. If you implemented an auth manager that could be useful to others please consider contributing it in a pull request.

### Return redirections

The Account manager can process ``GET`` redirect requests after login, e.g. ``?redirect=/awesome/redirect/url``. This can be useful to improve the user expirience in the case in which an unauthenticated user accesses a page that requires authentication and is being redirected to the login page. Using ``GET``, the original request location can be stored for after login so that the user is automatically redirected to the page she originally intended to access. Note that GET-redirects overwrite the redirection behaviour that can be defined in the component settings.

### JSON API

If enabled, the Account component exposes and JSON API endpoint that can be consumed by an authenticated user (unauthenticated users will face an 401 Unauthorized. response). Currently, the only route is ``?api=info`` which returns the jsonified user model.

### Support & Contribution

I can only offer limited support but will try to answer questions and feature requests on [GitHub](https://github.com/nocio/oc-passwordless-plugin). I am also happy to accept pull requests, especially for the missing features list on the Plugin details page.

**Please consider donating to support the ongoing development if you find this plugin useful.**
