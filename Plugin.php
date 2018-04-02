<?php namespace Nocio\Passwordless;

use System\Classes\PluginBase;


class Plugin extends PluginBase
{

    /**
     * Registers components
     * @return array
     */
    public function registerComponents()
    {
        return [
            'Nocio\Passwordless\Components\Account' => 'passwordlessAccount'
        ];
    }

    /**
     * Registers mail templates
     * @return array
     */
    public function registerMailTemplates()
    {
        return [
            'nocio.passwordless::mail.login' => 'Passwordless login'
        ];
    }

    public function registerSettings()
    {
    }

    public function registerSchedule($schedule)
    {
        $schedule->call(function () {
            \Nocio\Passwordless\Models\Token::clearExpired();
        })->daily();
    }
}
