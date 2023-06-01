<?php

namespace ZeroDUDDU\YoutubeLaravelApi;

use Google\Service\Exception as GoogleServiceException;
use Google\Exception as GoogleException;
use Google\Service\YouTube;
use Google\Service\YouTube\Channel;
use Google\Service\YouTube\Subscription;
use ZeroDUDDU\YoutubeLaravelApi\Auth\AuthService;
use Exception;

class ChannelService extends AuthService
{
    private readonly Youtube $service;

    public function __construct($token)
    {
        parent::__construct();

        if (!$this->setAccessToken($token)) {
            throw new Exception('invalid token');
        }
        $this->service = new YouTube($this->client);
    }

    /**
     * @param array $part [id,snippet,contentDetails,status, statistics, contentOwnerDetails, brandingSettings]
     * @param array $params [channels id(comma separated ids ) or you can get ('forUsername' => 'GoogleDevelopers')]
     */
    public function channelsListById(array $part, array $params): YouTube\ChannelListResponse
    {
        $params = array_filter($params);

        return $this->service->channels->listChannels($part, $params);
    }

    public function getChannelDetails($token): Channel
    {

        $part = "snippet,contentDetails,statistics,brandingSettings";
        $params = array('mine' => true);
        $response = $this->service->channels->listChannels($part, $params);

        return $response->getItems()[0];
    }

    /**
     * @param array $properties ['id' => '',
     *                      'brandingSettings.channel.description' => '',
     *                      'brandingSettings.channel.keywords' => '',
     *                      'brandingSettings.channel.defaultLanguage' => '',
     *                      'brandingSettings.channel.defaultTab' => '',
     *                      'brandingSettings.channel.moderateComments' => '',
     *                      'brandingSettings.channel.showRelatedChannels' => '',
     *                      'brandingSettings.channel.showBrowseView' => '',
     *                      'brandingSettings.channel.featuredChannelsTitle' => '',
     *                      'brandingSettings.channel.featuredChannelsUrls[]' => '',
     *                      'brandingSettings.channel.unsubscribedTrailer' => '')
     *                      ]
     * @param string|array $part [ brandingSettings ]
     * @param array $params ['onBehalfOfContentOwner' => '']
     */
    public function updateChannelBrandingSettings(array $properties, string|array $part = "", array $params = []): Channel
    {
        $params = array_filter($params);

        $propertyObject = $this->createResource($properties);

        $resource = new Channel($propertyObject);
        return $this->service->channels->update($part, $resource, $params);
    }

    public function subscriptionByChannelId(array $params): array
    {
        $params = array_filter($params);

        return $this->parseSubscriptions($params);
    }

    public function parseSubscriptions($params): array
    {
        $channelId = $params['channelId'];
        $totalResults = $params['totalResults'];
        $maxResultsPerPage = 50;
        if ($totalResults < 1) {
            $totalResults = 0;
        }
        $maxPages = ($totalResults - ($totalResults % $maxResultsPerPage)) / $maxResultsPerPage + 1;
        $i = 0;
        $part = 'snippet';
        $params = array('channelId' => $channelId, 'maxResults' => $maxResultsPerPage);
        $nextPageToken = 1;
        $subscriptions = [];
        while ($nextPageToken and $i < $maxPages) {
            if ($i == $maxPages - 1) {
                $params['maxResults'] = $totalResults % $maxResultsPerPage + 2;
            }

            $response = $this->service->subscriptions->listSubscriptions($part, $params);
            $response = json_decode(json_encode($response), true);
            $sub = array_column($response['items'], 'snippet');
            $sub2 = array_column($sub, 'resourceId');
            $subscriptions = array_merge($subscriptions, $sub2);
            $nextPageToken = $response['nextPageToken'] ?? false;

            $params['pageToken'] = $nextPageToken;
            $i++;
        }

        return $subscriptions;
    }

    public function addSubscriptions(
        array $properties,
        string|array $part = 'snippet',
        array $params = []
    ): Subscription {
        $params = array_filter($params);
        $propertyObject = $this->createResource($properties);

        $resource = new Subscription($propertyObject);
        return $this->service->subscriptions->insert($part, $resource, $params);
    }

    public function removeSubscription($subscriptionId, $params = []): mixed
    {
        $params = array_filter($params);

        return $this->service->subscriptions->delete($subscriptionId, $params);
    }
}
