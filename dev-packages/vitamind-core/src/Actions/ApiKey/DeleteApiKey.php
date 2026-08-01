<?php

namespace VitaminD\Core\Actions\ApiKey;

use VitaminD\Core\Events\ApiKeyRemoved;
use VitaminD\Core\Events\ApiKeyRemoving;
use VitaminD\Core\Models\PersonalAccessToken;

class DeleteApiKey
{
    public function delete(PersonalAccessToken $apiKey): void
    {
        ApiKeyRemoving::dispatch($apiKey);

        $apiKey->delete();

        ApiKeyRemoved::dispatch($apiKey);
    }
}
