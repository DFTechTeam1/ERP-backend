<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

class Geocoding {
	private $key;

	private $url;

	public function __construct()
	{
		$this->key = config('app.geoapify');

		$this->url = 'https://api.geoapify.com/v1/geocode/search?apiKey=' . $this->key;
	}

	public function getCoordinate(string $placeName)
	{
		$response = Http::get($this->url . '&text=' . $placeName);

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