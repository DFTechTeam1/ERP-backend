<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;

class WorldRegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('countries')->truncate();
        DB::table('states')->truncate();
        DB::table('cities')->truncate();

        $countries = json_decode(File::get(public_path('static_file/region/json/countries.json')), true);
        $countries = collect($countries)->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'iso3' => $item['iso3'],
                'iso2' => $item['iso2'],
                'phone_code' => $item['phone_code'],
                'currency' => $item['currency'],
            ];
        })->toArray();

        DB::table('countries')->insert($countries);

        $states = json_decode(File::get(storage_path('app/public/static-file/region/json/states.json')), true);
        $states = collect($states)->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'country_id' => $item['country_id'],
                'country_code' => $item['country_code'],
            ];
        })->toArray();

        DB::table('states')->insert($states);

        $cities = json_decode(File::get(storage_path('app/public/static-file/region/json/cities.json')), true);
        $cities = collect($cities)->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'state_id' => $item['state_id'],
                'country_id' => $item['country_id'],
                'country_code' => $item['country_code'],
            ];
        })->toArray();

        foreach ($cities as $city) {
            DB::table('cities')->insert($city);
        }

        Schema::enableForeignKeyConstraints();
    }
}
