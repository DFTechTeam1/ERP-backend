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
    case ValidationError = 422;
    case InternalServerError = 500;

    public function label()
    {
        return match ($this) {
            self::Created => __('global.created'),
            self::Success => __('global.success'),
            self::BadRequest => __('global.badRequest'),
            self::Unauthorized => __('global.unauthorized'),
            self::Forbidden => __('global.forbidden'),
            self::NotFound => __('global.notFound'),
            self::MethodNotAllowed => __('global.methodNotAllowed'),
            self::InternalServerError => __('global.internalServerError'),
            self::ValidationError => __('global.validationError'),
        };
    }
}
