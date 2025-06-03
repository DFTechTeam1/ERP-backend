<?php

namespace App\Services;

use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TestProjectConstructor extends TestCase
{
    public static function setMock(
        $testCase,
        $class,
        $method = [
            [
                'method' => 'list',
                'times' => 'once',
                'return' => [],
            ],
        ]
    ) {
        return $testCase->instance(
            $class,
            Mockery::mock($class, function (MockInterface $mock) use ($method) {
                foreach ($method as $methodData) {
                    $mockData = $mock->shouldReceive($methodData['method']);
                    if ($methodData['times'] == 'once') {
                        $mockData->once();
                    } elseif (gettype($methodData['times']) == 'integer') {
                        $mockData->atMost($methodData['times']);
                    }
                    $mockData->andReturn($methodData['return']);
                }
            })
        );
    }
}
