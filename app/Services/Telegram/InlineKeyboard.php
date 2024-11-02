<?php

namespace App\Services\Telegram;

class InlineKeyboard {
    private $keyboard;

    public function __construct()
    {
        $this->keyboard = [
            'inline_keyboard' => []
        ];
    }

    public function addItem(string $label, string $url, bool $isWebApp = false)
    {
        $items = [
            'text' => $label,
        ];

        if ($isWebApp) {
            $items['web_app'] = [
                'url' => $url
            ];
        } else {
            $items['url'] = $url;
        }

        array_push(
            $this->keyboard['inline_keyboard'],
            [$items]
        );
    }


    public function render()
    {
        return $this->keyboard;
    }
}
