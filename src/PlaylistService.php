<?php

declare(strict_types=1);

namespace ZeroDUDDU\YoutubeLaravelApi;

use Google\Service\YouTube;
use ZeroDUDDU\YoutubeLaravelApi\Auth\AuthService;

final class PlaylistService extends AuthService
{
    private readonly Youtube $youTube;

    public function __construct($token)
    {
        parent::__construct();

        if (!$this->setAccessToken($token)) {
            throw new \Exception('invalid token');
        }

        $this->youTube = new YouTube($this->client);
    }

    /**
     * @param string $part [snippet,contentDetails,id,statistics](comma separated id's)
     * @param array $params [regionCode,relevanceLanguage,videoCategoryId, videoDefinition, videoDimension]
     */
    public function getPlaylistsByChannelId(string $part, array $params): YouTube\PlaylistListResponse
    {
        $params['maxResults'] = 50;
        $params = array_filter($params);

        return $this->youTube->playlists->listPlaylists($part, $params);
    }

    /**
     * @param string $part [snippet,contentDetails,id,statistics](comma separated id's)
     * @param array $params [regionCode,relevanceLanguage,videoCategoryId, videoDefinition, videoDimension]
     */
    public function getPlaylistItemsById(string $part, array $params): YouTube\PlaylistItemListResponse
    {
        $params['maxResults'] = 50;
        $params = array_filter($params);

        return $this->youTube->playlistItems->listPlaylistItems($part, $params);
    }
}
