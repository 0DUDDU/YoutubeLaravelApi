<?php

namespace ZeroDUDDU\YoutubeLaravelApi;

use Google\Service\Exception as GoogleServiceException;
use Google\Service\YouTube;
use Google\Service\YouTube\LiveBroadcast;
use Google\Service\YouTube\LiveBroadcastSnippet;
use Google\Service\YouTube\LiveBroadcastStatus;
use ZeroDUDDU\YoutubeLaravelApi\Auth\AuthService;
use Carbon\Carbon;

class AuthenticateService extends AuthService
{
    protected LiveBroadcastSnippet $googleLiveBroadcastSnippet;
    protected LiveBroadcastStatus $googleLiveBroadcastStatus;
    protected LiveBroadcast $googleYoutubeLiveBroadcast;

    public function __construct()
    {
        parent::__construct();
        $this->googleLiveBroadcastSnippet = new LiveBroadcastSnippet();
        $this->googleLiveBroadcastStatus = new LiveBroadcastStatus();
        $this->googleYoutubeLiveBroadcast = new LiveBroadcast();
    }

    public function authChannelWithCode(string $code, bool $testToken = false): array
    {
        $authResponse = [];

        $token = $this->getToken($code);
        if (!$token) {
            $authResponse['error'] = 'invalid token';
            return $authResponse;
        }

        $authResponse['token'] = $token;
        $this->setAccessToken($authResponse['token']);

        if ($testToken) {
            $authResponse['channel_details'] = $this->channelDetails();
            $authResponse['live_streaming_status'] = $this->liveStreamTest($token) ? 'enabled' : 'disabled';
        }

        return $authResponse;
    }

    protected function channelDetails(): YouTube\ChannelListResponse
    {
        $params = array('mine' => true);
        $params = array_filter($params);
        $part = 'snippet';
        $service = new YouTube($this->client);
        return $service->channels->listChannels($part, $params);
    }

    protected function liveStreamTest($token): bool
    {
        try {
            $response = [];
            /**
             * [setAccessToken [setting accent token to client]]
             */
            $setAccessToken = $this->setAccessToken($token);
            if (!$setAccessToken) {
                return false;
            }

            /**
             * [$service [instance of YouTube ]]
             * @var [type]
             */
            $youtube = new YouTube($this->client);

            $title = "test";
            $description = "test live event";
            $startdt = Carbon::now("Asia/Kolkata");
            $startdtIso = $startdt->toIso8601String();

            $privacy_status = "private";
            $language = 'English';

            /**
             * Create an object for the liveBroadcast resource
             * [specify snippet's title, scheduled start time, and scheduled end time]
             */
            $this->googleLiveBroadcastSnippet->setTitle($title);
            $this->googleLiveBroadcastSnippet->setDescription($description);
            $this->googleLiveBroadcastSnippet->setScheduledStartTime($startdtIso);

            /**
             * object for the liveBroadcast resource's status ["private, public or unlisted"]
             */
            $this->googleLiveBroadcastStatus->setPrivacyStatus($privacy_status);

            /**
             * API Request [inserts the liveBroadcast resource]
             */
            $this->googleYoutubeLiveBroadcast->setSnippet($this->googleLiveBroadcastSnippet);
            $this->googleYoutubeLiveBroadcast->setStatus($this->googleLiveBroadcastStatus);
            $this->googleYoutubeLiveBroadcast->setKind('youtube#liveBroadcast');

            /**
             * Execute Insert LiveBroadcast Resource Api
             * [return an object that contains information about the new broadcast]
             */
            $broadcastsResponse = $youtube->liveBroadcasts->insert(
                'snippet,status',
                $this->googleYoutubeLiveBroadcast
            );
            $response['broadcast_response'] = $broadcastsResponse;
            $youtubeEventId = isset($broadcastsResponse['id']) ? $broadcastsResponse['id'] : false;

            if (!$youtubeEventId) {
                return false;
            }

            $this->deleteEvent($youtubeEventId);
            return true;
        } catch (GoogleServiceException $e) {
            /**
             *  This error is thrown if the Service is
             *    either not available or not enabled for the specific account
             */
            return false;
        }
    }

    public function deleteEvent($youtubeEventId): mixed
    {
        $youtube = new YouTube($this->client);
        return $youtube->liveBroadcasts->delete($youtubeEventId);
    }
}
