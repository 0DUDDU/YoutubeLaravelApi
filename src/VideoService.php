<?php

namespace ZeroDUDDU\YoutubeLaravelApi;

use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use ZeroDUDDU\YoutubeLaravelApi\Auth\AuthService;
use Exception;

class VideoService extends AuthService
{
    private readonly Youtube $youTube;

    public function __construct($token)
    {
        parent::__construct();

        if (!$this->setAccessToken($token)) {
            throw new Exception('invalid token');
        }

        $this->youTube = new YouTube($this->client);
    }

    /**
     * @param string $part [snippet,contentDetails,id,statistics](comma separated id's)
     * @param array $params [regionCode,relevanceLanguage,videoCategoryId, videoDefinition, videoDimension]
     */
    public function videosListById(string $part, array $params = []): YouTube\VideoListResponse
    {
        $params = array_filter($params);

        return $this->youTube->videos->listVideos($part, $params);
    }

    /**
     * @param string $part [snippet,id]
     * @param array $params ['maxResults','q','type','pageToken']
     */
    public function searchListByKeyword(string $part, array $params): YouTube\SearchListResponse
    {
        $params = array_filter($params);

        return $this->youTube->search->listSearch($part, $params);
    }

    /**
     * @param string $part [ sinppet, id]
     * @param array $params [ regionCode,relatedToVideoId,relevanceLanguage,videoCategoryId,type(video or channel)]
     */
    public function relatedToVideoId(string $part, array $params): YouTube\SearchListResponse
    {
        $params = array_filter($params);

        return $this->youTube->search->listSearch($part, $params);
    }

    /**
     * @param string[] $tags
     */
    public function uploadVideo(
        string $videoPath,
        string $title,
        string $description,
        string $categoryId,
        string $privacyStatus = 'private',
        array $tags = [],
        array $data = []
    ): bool {
        /**
         * snippet [title, description, tags and category ID]
         * asset resource [snippet metadata and type.]
         */
        $snippet = new VideoSnippet();

        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setCategoryId($categoryId);
        $snippet->setTags($tags);

        /**
         * video status ["public", "private", "unlisted"]
         */
        $status = new VideoStatus();
        $status->privacyStatus = $privacyStatus;

        /**
         * snippet and status [link with new video resource.]
         */
        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        /**
         * size of chunk to be uploaded  in bytes [default  1 * 1024 * 1024]
         * (Set a higher value for reliable connection as fewer chunks lead to faster uploads)
         */
        if (isset($data['chunk_size'])) {
            $chunkSizeBytes = $data['chunk_size'];
        } else {
            $chunkSizeBytes = 1 * 1024 * 1024;
        }

        /**
         * Setting the defer flag to true tells the client to return a request which can be called with ->execute();
         * instead of making the API call immediately
         */
        $this->client->setDefer(true);

        /**
         * request [API's videos.insert method] [ to create and upload the video]
         */
        $insertRequest = $this->youTube->videos->insert('status,snippet', $video);

        /**
         * MediaFileUpload object [resumable uploads]
         */
        $media = new MediaFileUpload(
            $this->client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );

        $media->setFileSize(filesize($videoPath));

        /**
         * Read the media file [to upload chunk by chunk]
         */
        $status = false;
        $handle = fopen($videoPath, "rb");
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        /**
         * set defer to false [to make other calls after the file upload]
         */
        $this->client->setDefer(false);
        return true;
    }

    public function deleteVideo(string $id, array $params = []): mixed
    {
        $params = array_filter($params);

        return $this->youTube->videos->delete($id, $params);
    }

    public function videosRate(string $id, string $rating = 'like', array $params = []): mixed
    {
        $params = array_filter($params);

        return $this->youTube->videos->rate($id, $rating, $params);
    }
}
