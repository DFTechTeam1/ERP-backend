<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Geocoding {
	private $key;

	private $url;

	public function __construct()
	{
		$this->key = config('app.geoapify');

		$this->url = 'https://api.geoapify.com/v1/geocode';
	}

    public function getPlaceName(array $locations)
    {
        $response = Http::get($this->url . "/reverse?lat=" . $locations['lat'] . '&lon=' . $locations['lon'] . '&type=street&format=json&apiKey=' . $this->key);
        Log::info($response->body());
        $output = [];
        if ($response->successful()) {
            $output = [
                'street' => $response->json()['results'][0]['formatted'],
            ];
        }

        return $output;
    }

	public function getCoordinate(string $placeName)
	{
		$response = Http::get($this->url . '/search?apiKey=' . $this->key . '&text=' . $placeName);

		$json = $response->json();

		$output = [];
	    if (
	        (isset($json['features'])) &&
	        (count($json['features']) > 0)
	    ) {
	        $output = [
	            'longitude' => $json['features'][0]['geometry']['coordinates'][0],
	            'latitude' => $json['features'][0]['geometry']['coordinates'][1],
	        ];
	    }

	    return $output;
	}
}
