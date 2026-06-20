<?php

/**
 * Unit tests get NO database and NO transaction — keep them pure so they stay
 * instant. Test plain classes, value objects, Data DTOs, helpers, etc. here.
 */
it('runs pure logic without touching the database', function () {
    expect(1 + 1)->toBe(2);
});
