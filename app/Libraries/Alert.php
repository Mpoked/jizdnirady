<?php

namespace App\Libraries;

/**
 * Příprava alertů pro view (ref. 08 §4.5)
 */
class Alert
{
    private \Config\Alert $config;

    public function __construct()
    {
        $this->config = config('Alert');
    }

    /**
     * @param bool   $status true = success, false = danger
     * @param string $type   typ operace podle Config\Alert (login, logout, filter …)
     *
     * @return object se dvěma atributy - text hlášky v message a třída v class
     */
    public function makeMessage($status, $type)
    {
        $result = new \stdClass();
        if ($status) {
            $result->class = 'success';
        } else {
            $result->class = 'danger';
        }
        $result->message = $this->config->alertMessage[$type . $result->class] ?? '';

        return $result;
    }

    /**
     * Prázdný alert - layout nic nezobrazí
     */
    public function empty()
    {
        return (object) ['message' => '', 'class' => ''];
    }
}
