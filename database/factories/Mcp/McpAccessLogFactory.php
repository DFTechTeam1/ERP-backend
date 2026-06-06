<?php

namespace Database\Factories\Mcp;

use App\Models\Mcp\McpAccessLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpAccessLog>
 */
class McpAccessLogFactory extends Factory
{
    protected $model = McpAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([200, 201, 401, 422, 500]);

        return [
            'user_id' => null,
            'user_email' => $this->faker->safeEmail(),
            'user_name' => $this->faker->userName(),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT']),
            'route_uri' => 'mcp/'.$this->faker->randomElement([
                'production/project/deals',
                'finance/insight',
                'production/project/customer/search',
            ]),
            'route_name' => null,
            'status_code' => $status,
            'is_success' => $status >= 200 && $status < 300,
            'parameters' => ['keyword' => $this->faker->word()],
            'response_message' => 'success',
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'duration_ms' => $this->faker->numberBetween(10, 2000),
            'accessed_at' => now(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => ['status_code' => 200, 'is_success' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status_code' => 500, 'is_success' => false]);
    }
}
