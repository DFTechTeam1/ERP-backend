<?php
it('probe store error', function () {
    $r = (new \Modules\Company\Services\ProjectClassService)->store(['name'=>'ProbeX','color'=>'#111','reward'=>1]);
    dump($r['message']);
    expect(true)->toBeTrue();
});
