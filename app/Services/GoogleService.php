<?php

namespace App\Services;

class GoogleService {
    private $client;

    public function __construct()
    {
        $this->client = new \Google\Client();
    }

    protected function setAuthKey()
    {
        $this->client->setDeveloperKey(config('app.googleApiKey'));
    }

    public function connection()
    {
        $client = new \Google\Client();
        $client->setScopes([\Google\Service\Sheets::SPREADSHEETS, \Google\Service\Sheets::DRIVE]);

        $client->setDeveloperKey(config('app.googleApiKey'));

        $service = new \Google\Service\Sheets($client);

        $response = $service->spreadsheets_values->get('13_9lJTB85vbr9f1QfMNDYHo3-aQjhBFuodvGC9Gwho4', 'Sheet1');

        return $response->getValues();
    }

    public function spreadSheet(string $spreadsheetId)
    {
        $this->setAuthKey();

        $this->client->setScopes([\Google\Service\Sheets::SPREADSHEETS, \Google\Service\Sheets::DRIVE]);

        $service = new \Google\Service\Sheets($this->client);

        $response = $service->spreadsheets_values->get($spreadsheetId, 'Fulltime Compile');

        return $response->getValues();
    }
}