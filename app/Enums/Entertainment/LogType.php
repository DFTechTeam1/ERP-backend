<?php

namespace App\Enums\Entertainment;

enum LogType: string
{
    case createSong = 'create_song';
    case updateSong = 'update_song';
    case deleteSong = 'delete_song';
    case addMoreSong = 'add_more';

    public function message(): string
    {
        return match ($this) {
            self::createSong => __('global.create_song'),
            self::updateSong => __('global.update_song'),
            self::deleteSong => __('global.delete_song'),
            self::addMoreSong => __('global.add_more'),
        };
    }

    public function messageParam(): array
    {
        return match($this) {
            self::createSong => ['{user}'],
            self::updateSong => ['{user}'],
            self::deleteSong => ['{user}'],
            self::addMoreSong => ['{user}'],
        };
    }
}
