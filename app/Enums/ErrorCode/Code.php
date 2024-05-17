<?php

namespace App\Enums\ErrorCode;

enum Code: int
{
    case Created = 201;
    case Success = 200;
    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case MethodNotAllowed = 405;
    case InternalServerError = 500;

    public function label()
    {
        return match ($this) {
            static::Created => __('global.created'),
            static::Success => __('global.success'),
            static::BadRequest => __('global.badRequest'),
            static::Unauthorized => __('global.unauthorized'),
            static::Forbidden => __('global.forbidden'),
            static::NotFound => __('global.notFound'),
            static::MethodNotAllowed => __('global.methodNotAllowed'),
            static::InternalServerError => __('global.internalServerError'),
        };
    }
}
