<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;

class MyanmarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $myanmarData = [
            'country' => [
                [
                    'name' => 'Myanmar',
                    'iso2' => 'MM',
                    'iso3' => 'MMR',
                    'phone_code' => '95',
                    'currency' => 'MMK',
                ],
            ],

            'states' => [
                // States/Regions of Myanmar with their cities
                [
                    'country_code' => 'MM',
                    'name' => 'Ayeyarwady Region',
                    'cities' => [
                        ['name' => 'Pathein'],
                        ['name' => 'Hinthada'],
                        ['name' => 'Myaungmya'],
                        ['name' => 'Pyapon'],
                        ['name' => 'Maubin'],
                        ['name' => 'Labutta'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Bago Region',
                    'cities' => [
                        ['name' => 'Bago'],
                        ['name' => 'Taungoo'],
                        ['name' => 'Pyay'],
                        ['name' => 'Tharyarwady'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Chin State',
                    'cities' => [
                        ['name' => 'Hakha'],
                        ['name' => 'Falam'],
                        ['name' => 'Mindat'],
                        ['name' => 'Matupi'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Kachin State',
                    'cities' => [
                        ['name' => 'Myitkyina'],
                        ['name' => 'Bhamo'],
                        ['name' => 'Putao'],
                        ['name' => 'Mohnyin'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Kayah State',
                    'cities' => [
                        ['name' => 'Loikaw'],
                        ['name' => 'Demoso'],
                        ['name' => 'Pruso'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Kayin State',
                    'cities' => [
                        ['name' => 'Hpa-an'],
                        ['name' => 'Myawaddy'],
                        ['name' => 'Kawkareik'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Magway Region',
                    'cities' => [
                        ['name' => 'Magway'],
                        ['name' => 'Pakokku'],
                        ['name' => 'Minbu'],
                        ['name' => 'Thayet'],
                        ['name' => 'Gangaw'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Mandalay Region',
                    'cities' => [
                        ['name' => 'Mandalay'],
                        ['name' => 'Meiktila'],
                        ['name' => 'Pyin Oo Lwin'],
                        ['name' => 'Yamethin'],
                        ['name' => 'Myingyan'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Mon State',
                    'cities' => [
                        ['name' => 'Mawlamyine'],
                        ['name' => 'Thaton'],
                        ['name' => 'Kyaikto'],
                        ['name' => 'Chaungzon'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Naypyidaw Union Territory',
                    'cities' => [
                        ['name' => 'Naypyidaw'],
                        ['name' => 'Lewe'],
                        ['name' => 'Pyinmana'],
                        ['name' => 'Zabuthiri'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Rakhine State',
                    'cities' => [
                        ['name' => 'Sittwe'],
                        ['name' => 'Thandwe'],
                        ['name' => 'Maungdaw'],
                        ['name' => 'Kyaukpyu'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Sagaing Region',
                    'cities' => [
                        ['name' => 'Sagaing'],
                        ['name' => 'Monywa'],
                        ['name' => 'Shwebo'],
                        ['name' => 'Kale'],
                        ['name' => 'Katha'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Shan State',
                    'cities' => [
                        ['name' => 'Taunggyi'],
                        ['name' => 'Lashio'],
                        ['name' => 'Kengtung'],
                        ['name' => 'Tachileik'],
                        ['name' => 'Muse'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Tanintharyi Region',
                    'cities' => [
                        ['name' => 'Dawei'],
                        ['name' => 'Myeik'],
                        ['name' => 'Kawthaung'],
                    ],
                ],
                [
                    'country_code' => 'MM',
                    'name' => 'Yangon Region',
                    'cities' => [
                        ['name' => 'Yangon'],
                        ['name' => 'Thanlyin'],
                        ['name' => 'Hlegu'],
                        ['name' => 'Hmawbi'],
                        ['name' => 'Taikkyi'],
                    ],
                ],
            ],
        ];

        // delete first
        $currentCountry = Country::where('iso2', 'MM')->first();
        if ($currentCountry) {
            City::where('country_id', $currentCountry->id)->delete();
            State::where('country_id', $currentCountry->id)->delete();
            $currentCountry->delete();
        }

        $country = Country::create($myanmarData['country'][0]);

        foreach ($myanmarData['states'] as $state) {
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
