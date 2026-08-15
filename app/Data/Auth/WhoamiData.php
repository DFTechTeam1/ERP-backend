<?php

namespace App\Data\Auth;

use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * Minimal identity payload returned by GET /api/whoami.
 *
 * Consumers use it as a "prove this bearer token is still valid" probe and
 * to render user chrome (name, email, role flags). Do NOT expand this with
 * heavy relations — every dfengine boot and every heartbeat calls it.
 */
class WhoamiData extends Data
{
    public function __construct(
        public string $uid,
        public ?string $name,
        public ?string $username,
        public ?string $email,
        public bool $isEmployee,
        public bool $isDirector,
        public bool $isProjectManager,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            uid: (string) $user->uid,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            isEmployee: (bool) $user->is_employee,
            isDirector: (bool) $user->is_director,
            isProjectManager: (bool) $user->is_project_manager,
        );
    }
}
