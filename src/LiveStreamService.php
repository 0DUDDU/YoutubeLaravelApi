<?php

namespace ZeroDUDDU\YoutubeLaravelApi;

use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\CdnSettings;
use Google\Service\YouTube\LiveBroadcast;
use Google\Service\YouTube\LiveBroadcastSnippet;
use Google\Service\YouTube\LiveBroadcastStatus;
use Google\Service\YouTube\LiveStream;
use Google\Service\YouTube\LiveStreamSnippet;
use Google\Service\YouTube\VideoRecordingDetails;
use ZeroDUDDU\YoutubeLaravelApi\Auth\AuthService;
use Carbon\Carbon;
use Exception;

/**
 *  Api Service For Youtube Live Events
 */
class LiveStreamService extends AuthService
{
    public const DEFAULT_BROADCAST_PRIVACY = 'private';

    protected YouTube $youtube;
    protected LiveBroadcastSnippet $googleLiveBroadcastSnippet;
    protected LiveBroadcastStatus $googleLiveBroadcastStatus;
    protected LiveBroadcast $googleYoutubeLiveBroadcast;
    protected LiveStreamSnippet $googleYoutubeLiveStreamSnippet;
    protected CdnSettings $googleYoutubeCdnSettings;
    protected LiveStream $googleYoutubeLiveStream;
    protected VideoRecordingDetails $googleYoutubeVideoRecordingDetails;

    public function __construct($token)
    {
        parent::__construct();
        if (!$this->setAccessToken($token)) {
            throw new Exception('invalid token');
        }
        $this->youtube = new YouTube($this->client);

        $this->googleLiveBroadcastSnippet = new LiveBroadcastSnippet();
        $this->googleLiveBroadcastStatus = new LiveBroadcastStatus();
        $this->googleYoutubeLiveBroadcast = new LiveBroadcast();
        $this->googleYoutubeLiveStreamSnippet = new LiveStreamSnippet();
        $this->googleYoutubeCdnSettings = new CdnSettings();
        $this->googleYoutubeLiveStream = new LiveStream();
        $this->googleYoutubeVideoRecordingDetails = new VideoRecordingDetails();
    }

    /**
     * @param ?array $data ['title' => '', 'description' => '']
     */
    public function broadcast($title, $description = '', array $data = null): array
    {
        $thumbnail_path = $data['thumbnail_path'] ?? null;
        $startDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $data['event_start_date_time'],
            $data['time_zone']
        );
        $now = Carbon::now($data['time_zone']);
        $startDatetime = max($startDatetime, $now);
        $startDatetimeIso = $startDatetime->toIso8601String();

        if (count($data['tag_array']) > 0) {
            $tags = implode(',', $data['tag_array']);
            $tags = rtrim($tags, ',');
            $data['tag_array'] = explode(',', $tags);
        } else {
            $data['tag_array'] = [];
        }

        $privacy_status = $data['privacy_status'] ?? self::DEFAULT_BROADCAST_PRIVACY;
        $language = $data['language_name'] ?? 'English';

        /**
         * Create an object for the liveBroadcast resource
         * [specify snippet's title, scheduled start time, and scheduled end time]
         */
        $this->googleLiveBroadcastSnippet->setTitle($title);
        $this->googleLiveBroadcastSnippet->setDescription($description);
        $this->googleLiveBroadcastSnippet->setScheduledStartTime($startDatetimeIso);

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
        $broadcastsResponse = $this->youtube->liveBroadcasts->insert(
            'snippet,status',
            $this->googleYoutubeLiveBroadcast
        );
        $response['broadcast_response'] = $broadcastsResponse;

        $youtubeEventId = $broadcastsResponse['id'];

        /**
         * set thumbnail to the event
         */
        if (!is_null($thumbnail_path)) {
            $thumb = $this->uploadThumbnail($thumbnail_path, $youtubeEventId);
        }

        /**
         * Call the API's videos.list method to retrieve the video resource.
         */
        $listResponse = $this->youtube->videos->listVideos('snippet', ['id' => $youtubeEventId]);
        $video = $listResponse[0];

        /**
         * update the tags and language via video resource
         */
        $videoSnippet = $video['snippet'];
        $videoSnippet['tags'] = $data['tag_array'];
        if (!is_null($language)) {
            $temp = $this->ytLanguage[$language] ?? 'en';
            $videoSnippet['defaultAudioLanguage'] = $temp;
            $videoSnippet['defaultLanguage'] = $temp;
        }

        $video['snippet'] = $videoSnippet;

        /**
         * Update video resource [videos.update() method.]
         */
        $updateResponse = $this->youtube->videos->update('snippet', $video);
        $response['video_response'] = $updateResponse;

        /**
         * object of livestream resource [snippet][title]
         */
        $this->googleYoutubeLiveStreamSnippet->setTitle($title);

        /**
         * object for content distribution  [stream's format,ingestion type.]
         */
        $this->googleYoutubeCdnSettings->setFormat("720p");
        $this->googleYoutubeCdnSettings->setIngestionType('rtmp');

        /**
         * API request [inserts liveStream resource.]
         */
        $this->googleYoutubeLiveStream->setSnippet($this->googleYoutubeLiveStreamSnippet);
        $this->googleYoutubeLiveStream->setCdn($this->googleYoutubeCdnSettings);
        $this->googleYoutubeLiveStream->setKind('youtube#liveStream');

        /**
         * execute the insert request [return an object that contains information about new stream]
         */
        $streamsResponse = $this->youtube->liveStreams->insert('snippet,cdn', $this->googleYoutubeLiveStream);
        $response['stream_response'] = $streamsResponse;

        /**
         * Bind the broadcast to the live stream
         */
        $bindBroadcastResponse = $this->youtube->liveBroadcasts->bind(
            $broadcastsResponse['id'],
            'id,contentDetails',
            ['streamId' => $streamsResponse['id']]
        );

        $response['bind_broadcast_response'] = $bindBroadcastResponse;

        return $response;
    }

    public function uploadThumbnail(string $url, string $videoId): string
    {
        $imagePath = $url;

        /**
         * size of chunk to be uploaded  in bytes [default  1 * 1024 * 1024]
         * (Set a higher value for reliable connection as fewer chunks lead to faster uploads)
         */
        $chunkSizeBytes = 1 * 1024 * 1024;

        /**
         * Setting the defer flag to true tells the client to return a request which can be called with ->execute();
         * instead of making the API call immediately
         */
        $this->client->setDefer(true);
        $setRequest = $this->youtube->thumbnails->set($videoId);

        /**
         * MediaFileUpload object [resumable uploads]
         */
        $media = new MediaFileUpload(
            $this->client,
            $setRequest,
            'image/png',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($imagePath));

        /**
         * Read the media file [to upload chunk by chunk]
         */
        $status = false;
        $handle = fopen($imagePath, "rb");
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        /**
         * set defer to false [to make other calls after the file upload]
         */
        $this->client->setDefer(false);
        return $status['items'][0]['default']['url'];
    }

    public function updateTags(string $videoId, array $tagsArray = []): YouTube\Video
    {
        /**
         * [$listResponse videos.list method to retrieve the video resource.]
         */
        $listResponse = $this->youtube->videos->listVideos(
            'snippet',
            ['id' => $videoId]
        );
        $video = $listResponse[0];

        $videoSnippet = $video['snippet'];
        $videoSnippet['tags'] = $tagsArray;
        $video['snippet'] = $videoSnippet;

        /**
         * [$updateResponse calling the videos.update() method.]
         */
        return $this->youtube->videos->update("snippet", $video);
    }

    /**
     * @param string $broadcastStatus [transition state - ["testing", "live", "complete"]]
     */
    public function transitionEvent($youtubeEventId, $broadcastStatus): LiveBroadcast
    {
        $part = "status, id, snippet";

        $liveBroadcasts = $this->youtube->liveBroadcasts;
        return $liveBroadcasts->transition($broadcastStatus, $youtubeEventId, $part);
    }

    public function updateBroadcast(array $data, string $youtubeEventId): array
    {
        if (count($data) < 1 || empty($data)) {
            throw new MissingRequiredParameterException();
        }

        $title = $data['title'];
        $description = $data['description'];
        $thumbnail_path = $data['thumbnail_path'] ?? null;

        /**
         *  parsing event start date
         */
        $startDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $data['event_start_date_time'],
            $data['time_zone']
        );
        $now = Carbon::now($data['time_zone']);
        $startDatetime = max($startDatetime, $now);
        $startDatetimeIso = $startDatetime->toIso8601String();
        $privacy_status = $data['privacy_status'] ?? self::DEFAULT_BROADCAST_PRIVACY;
        ;

        /**
         * parsing event end date
         */
        if (isset($data['event_end_date_time'])) {
            $endDatetime = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $data['event_end_date_time'],
                $data['time_zone']
            );
            $now = Carbon::now($data['time_zone']);
            $endDatetime = max($endDatetime, $now);
            $endDatetimeIso = $endDatetime->toIso8601String();
        }

        $tags = implode(',', $data['tag_array']);
        $tags = rtrim($tags, ',');
        $data['tag_array'] = explode(',', $tags);

        $language = $data['language_name'];

        /**
         * Create an object for the liveBroadcast resource's snippet
         * [snippet's title, scheduled start time, and scheduled end time.]
         */
        $this->googleLiveBroadcastSnippet->setTitle($title);
        $this->googleLiveBroadcastSnippet->setDescription($description);
        $this->googleLiveBroadcastSnippet->setScheduledStartTime($startDatetimeIso);

        if (isset($data['event_end_date_time'])) {
            $this->googleLiveBroadcastSnippet->setScheduledEndTime($endDatetimeIso);
        }

        /**
         * Create an object for the liveBroadcast resource's status ["private, public or unlisted".]
         */
        $this->googleLiveBroadcastStatus->setPrivacyStatus($privacy_status);

        /**
         * Create the API request  [inserts the liveBroadcast resource.]
         */
        $this->googleYoutubeLiveBroadcast->setSnippet($this->googleLiveBroadcastSnippet);
        $this->googleYoutubeLiveBroadcast->setStatus($this->googleLiveBroadcastStatus);
        $this->googleYoutubeLiveBroadcast->setKind('youtube#liveBroadcast');
        $this->googleYoutubeLiveBroadcast->setId($youtubeEventId);

        /**
         * Execute the request [return info about the new broadcast ]
         */
        $broadcastsResponse = $this->youtube->liveBroadcasts->update(
            'snippet,status',
            $this->googleYoutubeLiveBroadcast,
            array()
        );

        /**
         * set thumbnail
         */
        if (!is_null($thumbnail_path)) {
            $thumb = $this->uploadThumbnail($thumbnail_path, $youtubeEventId);
        }

        /**
         * Call the API's videos.list method [retrieve the video resource]
         */
        $listResponse = $this->youtube->videos->listVideos('snippet', ['id' => $youtubeEventId]);
        $video = $listResponse[0];
        $videoSnippet = $video['snippet'];
        $videoSnippet['tags'] = $data['tag_array'];

        /**
         * set Language and other details
         */
        if (!is_null($language)) {
            $temp = $this->ytLanguage[$language] ?? "en";
            $videoSnippet['defaultAudioLanguage'] = $temp;
            $videoSnippet['defaultLanguage'] = $temp;
        }

        $videoSnippet['title'] = $title;
        $videoSnippet['description'] = $description;
        $videoSnippet['scheduledStartTime'] = $startDatetimeIso;
        $video['snippet'] = $videoSnippet;

        /**
         * Update the video resource  [call videos.update() method]
         */
        $updateResponse = $this->youtube->videos->update('snippet', $video);

        $response['broadcast_response'] = $updateResponse;

        $youtubeEventId = $updateResponse['id'];

        $this->googleYoutubeLiveStreamSnippet->setTitle($title);

        /**
         * object for content distribution  [stream's format,ingestion type.]
         */

        $this->googleYoutubeCdnSettings->setFormat("720p");
        $this->googleYoutubeCdnSettings->setIngestionType('rtmp');

        /**
         * API request [inserts liveStream resource.]
         */
        $this->googleYoutubeLiveStream->setSnippet($this->googleYoutubeLiveStreamSnippet);
        $this->googleYoutubeLiveStream->setCdn($this->googleYoutubeCdnSettings);
        $this->googleYoutubeLiveStream->setKind('youtube#liveStream');

        /**
         * execute the insert request [return an object that contains information about new stream]
         */
        $streamsResponse = $this->youtube->liveStreams->insert('snippet,cdn', $this->googleYoutubeLiveStream);
        $response['stream_response'] = $streamsResponse;

        /**
         * Bind the broadcast to the live stream
         */
        $bindBroadcastResponse = $this->youtube->liveBroadcasts->bind(
            $updateResponse['id'],
            'id,contentDetails',
            ['streamId' => $streamsResponse['id']]
        );
        $response['bind_broadcast_response'] = $bindBroadcastResponse;

        return $response;
    }

    public function deleteEvent(string $youtubeEventId): mixed
    {
        return $this->youtube->liveBroadcasts->delete($youtubeEventId);
    }
}
