<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;

class SingaporeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $singaporeData = [
            'country' => [
                [
                    'name' => 'Singapore',
                    'iso2' => 'SG',
                    'iso3' => 'SGP',
                    'phone_code' => '65',
                    'currency' => 'SGD',
                ]
            ],
            
            'states' => [
                // Singapore is divided into 5 regions (treated as states here)
                [
                    'name' => 'Central Region',
                    'country_code' => 'SG',
                    'cities' => [
                        ['name' => 'Bishan'],
                        ['name' => 'Bukit Merah'],
                        ['name' => 'Bukit Timah'],
                        ['name' => 'Downtown Core'],
                        ['name' => 'Geylang'],
                        ['name' => 'Kallang'],
                        ['name' => 'Marina East'],
                        ['name' => 'Marina South'],
                        ['name' => 'Marine Parade'],
                        ['name' => 'Museum'],
                        ['name' => 'Newton'],
                        ['name' => 'Novena'],
                        ['name' => 'Orchard'],
                        ['name' => 'Outram'],
                        ['name' => 'Queenstown'],
                        ['name' => 'River Valley'],
                        ['name' => 'Rochor'],
                        ['name' => 'Singapore River'],
                        ['name' => 'Southern Islands'],
                        ['name' => 'Straits View'],
                        ['name' => 'Tanglin'],
                        ['name' => 'Toa Payoh'],
                    ]
                ],
                [
                    'country_id' => 1,
                    'name' => 'East Region',
                    'country_code' => 'SG',
                    'cities' => [
                        ['name' => 'Bedok'],
                        ['name' => 'Changi'],
                        ['name' => 'Changi Bay'],
                        ['name' => 'Pasir Ris'],
                        ['name' => 'Paya Lebar'],
                        ['name' => 'Tampines'],
                    ]
                ],
                [
                    'country_id' => 1,
                    'name' => 'North Region',
                    'country_code' => 'SG',
                    'cities' => [
                        ['name' => 'Central Water Catchment'],
                        ['name' => 'Lim Chu Kang'],
                        ['name' => 'Mandai'],
                        ['name' => 'Sembawang'],
                        ['name' => 'Simpang'],
                        ['name' => 'Sungei Kadut'],
                        ['name' => 'Woodlands'],
                        ['name' => 'Yishun'],
                    ]
                ],
                [
                    'country_id' => 1,
                    'name' => 'North-East Region',
                    'country_code' => 'SG',
                    'cities' => [
                        ['name' => 'Ang Mo Kio'],
                        ['name' => 'Hougang'],
                        ['name' => 'North-Eastern Islands'],
                        ['name' => 'Punggol'],
                        ['name' => 'Seletar'],
                        ['name' => 'Sengkang'],
                        ['name' => 'Serangoon'],
                    ]
                ],
                [
                    'country_id' => 1,
                    'name' => 'West Region',
                    'country_code' => 'SG',
                    'cities' => [
                        ['name' => 'Boon Lay'],
                        ['name' => 'Bukit Batok'],
                        ['name' => 'Bukit Panjang'],
                        ['name' => 'Choa Chu Kang'],
                        ['name' => 'Clementi'],
                        ['name' => 'Jurong East'],
                        ['name' => 'Jurong West'],
                        ['name' => 'Pioneer'],
                        ['name' => 'Tengah'],
                        ['name' => 'Tuas'],
                        ['name' => 'Western Islands'],
                        ['name' => 'Western Water Catchment']
                    ]
                ]
            ],
        ];

        // delete first
        $currentCountry = Country::where('iso2', 'SG')->first();
        if ($currentCountry) {
            City::where('country_id', $currentCountry->id)->delete();
            State::where('country_id', $currentCountry->id)->delete();
            $currentCountry->delete();
        }

        $country = Country::create($singaporeData['country'][0]);
        foreach ($singaporeData['states'] as $state) {
            $payloadState = collect($state)->except(['cities'])->toArray();

            $country->states()->create($payloadState);

            $stateData = State::where('name', $state['name'])->first();

            foreach ($state['cities'] as $city) {
                $city['country_id'] = $country->id;
                $city['country_code'] = $country->iso2;

                $stateData->cities()->create($city);
            }
        }
    }
}
