<?php

namespace ZeroDUDDU\YoutubeLaravelApi\Auth;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Config;

/**
 *  Api Service For Auth
 */
class AuthService
{
    protected GoogleClient $client;
    protected array $ytLanguage;
    protected ?string $newToken = null;


    public function __construct()
    {
        $this->client = new GoogleClient();

        $this->client->setClientId(Config::get('google-config.client_id'));
        $this->client->setClientSecret(Config::get('google-config.client_secret'));
        $this->client->setDeveloperKey(Config::get('google-config.api_key'));
        $this->client->setRedirectUri(Config::get('google-config.redirect_url'));

        $this->client->setScopes([
            'https://www.googleapis.com/auth/youtube',
        ]);

        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->ytLanguage = Config::get('google-config.yt_language');
    }

    public function getToken(string $code): array
    {
        $this->client->fetchAccessTokenWithAuthCode($code);

        return $this->client->getAccessToken();
    }

    public function getLoginUrl(string $youtube_email, string $channelId = null): string
    {
        if (!empty($channelId)) {
            $this->client->setState($channelId);
        }

        $this->client->setLoginHint($youtube_email);

        return $this->client->createAuthUrl();
    }

    public function setAccessToken(string|array $google_token = null): bool
    {
        if (!is_null($google_token)) {
            $this->client->setAccessToken($google_token);
        }

        if (!is_null($google_token) && $this->client->isAccessTokenExpired()) {
            $refreshed_token = $this->client->getRefreshToken();
            $this->client->fetchAccessTokenWithRefreshToken($refreshed_token);
            $newToken = $this->client->getAccessToken();
            $this->newToken = json_encode($newToken);
        }

        return !$this->client->isAccessTokenExpired();
    }

    public function getNewToken(): ?string
    {
        return $this->newToken;
    }

    public function createResource(array $properties): array
    {
        $resource = [];
        foreach ($properties as $prop => $value) {
            if ($value) {
                /**
                 * add property to resource
                 */
                $this->addPropertyToResource($resource, $prop, $value);
            }
        }

        return $resource;
    }

    public function addPropertyToResource(array &$ref, string $property, string $value): void
    {
        $keys = explode(".", $property);
        $isArray = false;
        foreach ($keys as $key) {

            /**
             * snippet.tags[]  [convert to snippet.tags]
             * a boolean variable  [to handle the value like an array]
             */
            if (substr($key, -2) == "[]") {
                $key = substr($key, 0, -2);
                $isArray = true;
            }

            $ref = &$ref[$key];
        }

        /**
         * Set the property value [ handling the array values]
         */
        if ($isArray && $value) {
            $ref = $value;
            $ref = explode(",", $value);
        } elseif ($isArray) {
            $ref = array();
        } else {
            $ref = $value;
        }
    }

    public function parseTime($time): array|string
    {
        $tempTime = str_replace("PT", " ", $time);
        $tempTime = str_replace('H', " Hours ", $tempTime);
        $tempTime = str_replace('M', " Minutes ", $tempTime);
        $tempTime = str_replace('S', " Seconds ", $tempTime);

        return $tempTime;
    }
}
