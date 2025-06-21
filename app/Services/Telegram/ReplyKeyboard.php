<?php

namespace App\Services\Telegram;

class ReplyKeyboard
{
    private $keyboard;

    public function __construct()
    {
        $this->keyboard = [
            'keyboard' => [],
            'is_persistent' => false,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    public function setOneTime(bool $payload)
    {
        $this->keyboard['one_time_keyboard'] = $payload;
    }

    public function setResizeKeyboard(bool $payload)
    {
        $this->keyboard['resize_keyboard'] = $payload;
    }

    public function addPlainButton(string $text)
    {
        $this->keyboard['keyboard'][] = [['text' => $text]];
    }

    public function addWebAppButton(string $url, string $text)
    {
        array_push($this->keyboard['keyboard'], ['text' => $text, 'web_app' => ['url' => $url]]);
    }

    public function render()
    {
        return $this->keyboard;
    }
}
