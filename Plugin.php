<?php namespace Nocio\Passwordless;

use System\Classes\PluginBase;


class Plugin extends PluginBase
{

    /**
     * Component details
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'nocio.passwordless::lang.plugin.name',
            'description' => 'nocio.passwordless::lang.plugin.description',
            'icon'        => 'oc-icon-key',
            'homepage'    => 'https://github.com/nocio/oc-passwordless-plugin'
        ];
    }

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

    public function registerSchedule($schedule)
    {
        $schedule->call(function () {
            \Nocio\Passwordless\Models\Token::clearExpired();
        })->daily();
    }
}
