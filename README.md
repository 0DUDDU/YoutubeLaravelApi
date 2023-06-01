#  Youtube Laravel Api
 `PHP (Laravel) Package for Google / YouTube API V3 with Google Auth`

## Features

- [x] [Google Auth](#google-auth)
- [x] [Full Live Streaming API for Youtube](#full-live-streaming-api) 
- [x] [Full Youtube Channel API](#full-youtube-channel-api)
- [x] [Full Youtube Video API](#full-youtube-video-api)


## Installation
 
```shell
composer require 0duddu/youtube-laravel-api
```

Add Service provider to config/app.php provider's array:
```php
ZeroDUDDU\YoutubeLaravelApi\YoutubeLaravelApiServiceProvider::class
```

Execute the following command to get the configurations:
```shell
php artisan vendor:publish --tag='youtube-config'
```

## Steps to create your google oauth credentials:

1. Goto `https://console.developers.google.com`
2. Login with your credentials & then create a new project.
3. Enable the following features while creating key
	- Youtube Data API
	- Youtube Analytics API
	- Youtube Reporting API
4. Then create `API key` from credentials tab.
5. Then in OAuth Consent Screen enter the `product name`(your site name). 
6. create credentials > select OAuth Client ID. (here you will get client_id and client_secret)
7. Then in the Authorized Javascript Origins section add `you site url`.
8. In the Authorized Redirect URLs section add `add a url which you want the auth code to return`(login callback)
9. You will get values (to be exact - client_id, client_secret & api_key) 
10. Then add these values - client_id, client_secret, api_key and redirect_url in the env file and you can start using the package now.


## Usage :

### Google Auth 

- **Add Code to call the api class**

```php

<?php
namespace Your\App\NameSpace;

use  ZeroDUDDU\YoutubeLaravelApi\AuthenticateService;	

```

- **Generating an Auth-Url**
```php

$authObject  = new AuthenticateService;

# Replace the identifier with a unqiue identifier for account or channel
$authUrl = $authObject->getLoginUrl('email','identifier'); 

```

- **Fetching the Auth Code and Identifier**
	Now once the user authorizes by visiting the url, the authcode will be redirected to the redirect_url specified in .env with params as code( this will be auth code) and  state (this will be identifier we added during making the loginUrl)

```php
$code = $request->get('code');
$identifier = $request->get('state');

```

-  **Auth-Token and Details For Channel**

```php
$authObject  = new AuthenticateService;
$authResponse = $authObject->authChannelWithCode($code, true);
```

- **This will return an array**: 
```
$authResponse['token'] (Channel Token)
$authResponse['channel_details']
$authResponse['live_streaming_status'] (enabled or disabled)
```

-  **Auth-Token only**

```php
$authObject  = new AuthenticateService;
$authResponse = $authObject->authChannelWithCode($code, false);
```

- **This will return an array**:
```
$authResponse['token'] (Channel Token)
```

-  **Refresh Token**
   If your token expires, it will be refreshed using the refresh token, and the new token will be available by calling `getNewToken()` on any object. It will return null if no new token has been fetched. 

### Full Live Streaming API 

- **Add Code to call the api class**

```php
<?php
namespace Your\App\NameSpace;

use  ZeroDUDDU\YoutubeLaravelApi\LiveStreamService;	
```

- **Creating a Youtube Event**

```php
# data format creating live event
$data = array(
	"thumbnail_path" => "",				// Optional
	"event_start_date_time" => "",
	"event_end_date_time" => "",			// Optional
	"time_zone" => "",
	'privacy_status' => "",				// default: "private"
	"language_name" => "",				// default: "English"
	"tag_array" => ""				// Optional and should not be more than 500 characters
);

$ytEventObj = new LiveStreamService($authToken);
/**
 * The broadcast function returns array of details from YouTube.
 * Store this information & will be required to supply to youtube 
 * for live streaming using encoder of your choice. 
 */
$response = $ytEventObj->broadcast($title, $description,  $data);
if ( !empty($response) ) {
	$youtubeEventId = $response['broadcast_response']['id'];
	$serverUrl = $response['stream_response']['cdn']->ingestionInfo->ingestionAddress;
	$serverKey = $response['stream_response']['cdn']->ingestionInfo->streamName;
}

```

- **Updating a Youtube Event**

```php
$ytEventObj = new LiveStreamService($authToken);
/**
* The updateBroadcast response give details of the youtube_event_id,server_url and server_key. 
* The server_url & server_key gets updated in the process. (save the updated server_key and server_url).
*/
$response = $ytEventObj->updateBroadcast($data, $youtubeEventId);

// $youtubeEventId = $response['broadcast_response']['id'];
// $serverUrl = $response['stream_response']['cdn']->ingestionInfo->ingestionAddress;
// $serverKey = $response['stream_response']['cdn']->ingestionInfo->streamName
```

- **Deleting a Youtube Event**

```php
$ytEventObj = new LiveStreamService($authToken);

# Deleting the event requires authentication token for the channel in which the event is created and the youtube_event_id
$ytEventObj->deleteEvent($youtubeEventId);
```

- Starting a Youtube Event Stream:

```php
$ytEventObj = new LiveStreamService($authToken);
/**
 * $broadcastStatus - ["testing", "live"]
 * Starting the event takes place in 3 steps
 * 1. Start sending the stream to the server_url via server_key recieved as a response in creating the event via the encoder of your choice.
 * 2. Once stream sending has started, stream test should be done by passing $broadcastStatus="testing" & it will return response for stream status.
 * 3. If transitioEvent() returns successfull for testing broadcast status, then start live streaming your video by passing $broadcastStatus="live" 
 * & in response it will return us the stream status.
 */ 
$streamStatus = $ytEventObj->transitionEvent($youtubeEventId, $broadcastStatus);	
```

- **Stopping a Youtube Event Stream**

```php
$ytEventObj = new LiveStreamService($authToken);
/**
 * $broadcastStatus - ["complete"]
 * Once live streaming gets started succesfully. We can stop the streaming the video by passing broadcastStatus="complete" and in response it will give us the stream status.
 */
$ytEventObj->transitionEvent($youtubeEventId, $broadcastStatus);	// $broadcastStatus = ["complete"]
```


### Full Youtube Channel API

- **Add Code to call the api class**

```php
<?php
namespace Your\App\NameSpace;

use  ZeroDUDDU\YoutubeLaravelApi\ChannelService;
```

- **Channel details By Channel Id**
	If you want channel details for multiple channels add channel id saperated by commas(,) in param

```php
/**
 * [channelsListById -gets the channnel details and ]
 *   $part    'id,snippet,contentDetails,status, statistics, contentOwnerDetails, brandingSettings'
 *  $params  [array channels id(comma separated ids ) or you can get ('forUsername' => 'GoogleDevelopers')]
 */

$part = 'id,snippet';
$params = array('id'=> 'channel_1_id,channel_2_id');
$channelServiceObject  = new ChannelService($authToken);
$channelDetails = $channelServiceObject->channelsListById($part, $params);

```

- **Channel Detail by Token**
	Channel Details of the users channel which has authorized token

```php 
$channelServiceObject  = new ChannelService;
$channelDetails = $channelServiceObject->getChannelDetails($authToken);
```

- **Channel Subscription List**
	List of subscriptions of the channel

```php

/*
* $params array('channelId'=>'', 'totalResults'= 10)
* totalResults is different of maxResults from Google Api.
* totalResults = the amount of results you want
* maxResults = max of results PER PAGE. We don't need this parameter here since it will loop until it gets all the results you want.
*/
$channelServiceObject  = new ChannelService($authToken);
$channelDetails = $channelServiceObject->subscriptionByChannelId($params);
```

- **Add Subscriptions For Authorized Channel**

```php 
/*
* properties  array('snippet.resourceId.kind' => 'youtube#channel','snippet.resourceId.channelId' => 'UCqIOaYtQak4-FD2-yI7hFkw')
*/
$channelServiceObject  = new ChannelService;
$response = $channelServiceObject->addSubscriptions($properties, $token, $part='snippet', $params=[]);

```

-**Remove Subscriptions For Authorized Channel**
To remove subscription we need subscription id which can be found from subscription list.
```php
$response = $channelServiceObject->removeSubscription( $token, $subscriptionId);

```

- **Update Channel Branding Settings**
	Updates the channel details and preferences.

```php
/*
 *      $properties array('id' => '',
 *					'brandingSettings.channel.description' => '',
 *					'brandingSettings.channel.keywords' => '',
 *					'brandingSettings.channel.defaultLanguage' => '',
 *					'brandingSettings.channel.defaultTab' => '',
 *					'brandingSettings.channel.moderateComments' => '',
 *					'brandingSettings.channel.showRelatedChannels' => '',
 *					'brandingSettings.channel.showBrowseView' => '',
 *					'brandingSettings.channel.featuredChannelsTitle' => '',
 *					'brandingSettings.channel.featuredChannelsUrls[]' => '',
 *					'brandingSettings.channel.unsubscribedTrailer' => '')
 */

$channelServiceObject  = new ChannelService($authToken);
$response = $channelServiceObject->updateChannelBrandingSettings($properties);
```

### Full Youtube Video API

- **Add Code to call the api class**

```php
<?php
namespace Your\App\NameSpace;

use  ZeroDUDDU\YoutubeLaravelApi\VideoService;
```

- **List Video By Id**

```php
$part ='snippet,contentDetails,id,statistics';
$params =['id'=>'xyzgh'];
$videoServiceObject  = new VideoService($authToken);
$response = $videoServiceObject->videosListById($part, $params);
```

- **Upload Video To Your Channel**
```php

$videoServiceObject  = new VideoService($authToken);
$response = $videoServiceObject->uploadVideo($videoPath, $title, $description, $categoryId, $privacyStatus, $tags, $data);
```

- **Delete Video To Your Channel**
```php
$videoServiceObject  = new VideoService($authToken);
$response = $videoServiceObject->deleteVideo($videoId);
```


- **Rate Video**
	Adding a like, dislike or removing the response from video

```php
# rating  'like' or 'dislike' or 'none'
	$videoServiceObject  = new VideoService($authToken);
$response = $videoServiceObject->videosRate( $videoId, $rating);

```







