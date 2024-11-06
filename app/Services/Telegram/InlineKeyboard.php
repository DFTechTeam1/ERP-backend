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

    public function addItemWithCallbackQuery(string $text, string $callback)
    {
        $items = [
            'text' => $text,
            'callback_data' => $callback
        ];

        array_push(
            $this->keyboard['inline_keyboard'],
            [$items]
        );
    }

    public function addItem(
        string $label,
        string $url = '',
        bool $isWebApp = false,
        string $callBackQuery = ''
    ) {
        $items = [
            'text' => $label,
        ];

        if ($isWebApp) {
            $items['web_app'] = [
                'url' => $url
            ];
        }

        if (!empty($url)) {
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
