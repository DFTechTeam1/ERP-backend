<?php

namespace App\Enums\Entertainment;

enum LogType: string
{
    case createSong = 'create_song';
    case updateSong = 'update_song';
    case deleteSong = 'delete_song';
    case addMoreSong = 'add_more';
    case createJumpBack = 'create_jump_back';
    case taskApproved = 'task_approved';
    case taskRejected = 'task_rejected';
    case taskHold = 'task_hold';
    case taskReported = 'task_reported';
    case taskCompleted = 'task_completed';

    public function message(): string
    {
        return match ($this) {
            self::createSong => __('global.entertainment.create_song'),
            self::updateSong => __('global.entertainment.update_song'),
            self::deleteSong => __('global.entertainment.delete_song'),
            self::addMoreSong => __('global.entertainment.add_more'),
            self::createJumpBack => __('global.entertainment.create_jump_back'),
            self::taskApproved => __('global.entertainment.task_approved'),
            self::taskRejected => __('global.entertainment.task_rejected'),
            self::taskHold => __('global.entertainment.task_hold'),
            self::taskReported => __('global.entertainment.task_reported'),
            self::taskCompleted => __('global.entertainment.task_completed'),
        };
    }

    public function messageParam(): array
    {
        return match($this) {
            self::createSong => ['{user}'],
            self::updateSong => ['{user}'],
            self::deleteSong => ['{user}'],
            self::addMoreSong => ['{user}'],
            self::createJumpBack => ['{user}', '{task}'],
            self::taskApproved => ['{user}', '{task}'],
            self::taskRejected => ['{user}', '{task}'],
            self::taskHold => ['{user}', '{task}'],
            self::taskReported => ['{user}', '{task}'],
            self::taskCompleted => ['{user}', '{task}'],
        };
    }
}
