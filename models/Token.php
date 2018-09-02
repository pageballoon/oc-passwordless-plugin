<?php

namespace Nocio\Passwordless\Models;

use Model;
use Hash;
use Carbon\Carbon;
use October\Rain\Exception\ApplicationException;

class Token extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'nocio_passwordless_tokens';

    /**
     * Fillable fields for the model.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $dates = ['expires'];

    public $morphTo = [
        'user' => []
    ];

    /**
     * Validates against a token
     * @param type $token
     * @return type
     */
    public function validate($token) {
        return Hash::check($token, $this->token);
    }

    /**
     * Generates and returns a new token for a user object
     *
     * @param  $user
     * @param $expires Minutes or Carbon instance
     * @return $token
     */
    public static function generate($user, $expires = 10, $scope = 'default')
    {
        $identifier = str_random(12);
        $token = str_random(48);

        if (is_numeric($expires)) {
            $expires = Carbon::now()->addMinutes($expires);
        }

        static::create([
            'user_type' => get_class($user),
            'user_id' => $user->id,
            'identifier' => $identifier,
            'token' => Hash::make($token),
            'expires' => $expires,
            'scope' => $scope
        ]);

        return $identifier . '-' . $token;
    }

    /**
     * Validates token and returns token user object
     * @param $raw_token
     * @param $delete bool If true token will be removed after parsing
     * @param $scope mixed The name scope of the token
     * @throws ApplicationException Invalid token
     * @return Authenticated user object
     */
    public static function parse($raw_token, $delete = false, $scope = null) {
        // unserialize if serialized
        $unserialized_token = @unserialize($raw_token, ["allowed_classes" => false]);
        if ($unserialized_token === false) {
            $unserialized_token = $raw_token;
        }
        // ensure correct format
        $clean_token = preg_replace("/[^A-Za-z0-9\- ]/", '', $unserialized_token);

        list($identifier, $token) = explode('-', $clean_token);

        if (!$login_token = self::lookup($identifier)->scope($scope)->valid()->first()) {
            throw new ApplicationException('Token expired or not existent. Try to login again.');
        }

        if (!$login_token->validate($token)) {
            throw new ApplicationException('Token invalid');
        }

        if (!$user = $login_token->user()->first()) {
            throw new ApplicationException('User does not exist');
        }

        if ($delete) {
            $login_token->delete();
        }

        return $user;
    }

    /**
     * Clears all expired unused tokens
     * @return int Number of deleted expired tokens
     */
    public static function clearExpired() {
        return self::expired()->delete();
    }

    public function scopeScope($query, $scope = null) {
        if (! $scope) {
            return $query;
        }
        return $query->where('scope', $scope);
    }

    public function scopeLookup($query, $identifier) {
        return $query->where('identifier', $identifier);
    }

    public function scopeExpired($query) {
        return $query->where('expires', '<', Carbon::now());
    }

    public function scopeValid($query) {
        return $query->where('expires', '>=', Carbon::now());
    }
}
