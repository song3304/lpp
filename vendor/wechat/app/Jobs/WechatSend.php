<?php
namespace Plugins\Wechat\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Plugins\Attachment\App\Attachment;
use Plugins\Wechat\App\Tools\API;
use Plugins\Wechat\App\Tools\Url as UrlTool;
use Plugins\Wechat\App\WechatAccount;
use Plugins\Wechat\App\WechatUser;
use Plugins\Wechat\App\WechatDepot;
use Plugins\Wechat\App\WechatTemplate;
use Plugins\Wechat\App\WechatMessage;
use Plugins\Wechat\App\WechatMessageText;
use Plugins\Wechat\App\WechatMessageMedia;
use Plugins\Wechat\App\WechatMessageLink;
use Plugins\Wechat\App\WechatMessageLocation;
use Addons\Core\File\Mimes;

class WechatSend implements ShouldQueue
{
	use Queueable;
	use InteractsWithQueue, SerializesModels;

	public $user;
	public $media;
	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(WechatUser $user, $media)
	{
		$this->user = $user;
		$this->media = $media;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		//启动SocketLog
		slog(['error_handler' => true], 'config');

		$api = new API($this->user->account->toArray(), $this->user->account->getKey());
		$message = WechatMessage::create(['waid' => $api->waid, 'wuid' => $this->user->getKey(), 'type' => 'text', 'transport_type' => 'send', 'message_id' => '']);
		$data = ['touser' => $this->user->openid,];
		$type = 'text';
		if ($this->media instanceof Attachment)
		{
			$attachment = $this->media;
			$type = $attachment->file_type();
			$type == 'audio' && $type = 'voice';
			$media = $this->uploadToWechat($api, $this->media, $type);

			if (empty($media)) return;
			$type = $media['type']; //多余的步骤
			$data += ['msgtype' => $type, $type => ['media_id' => $media['media_id']],];

			if ($type == 'video')
			{
				$data[$type] += [
					'thumb_media_id' => '',
					'title' => $attachment->filename,
					'description' => $attachment->description,
				];
			}
			slog('发送微信消息(来自附件)');
			slog($this->media->toArray());
			//入库
			WechatMessageMedia::create(['id' => $message->getKey(), 'media_id' => $media['media_id'], 'aid' => $this->media->getKey(), 'format' => $this->media->ext]);
		} elseif ($this->media instanceof WechatDepot) { //素材

			$depot = $this->media;
			$type = $depot->type;
			$depot->$type; //init
			$data += ['msgtype' => $type, $type => []];

			if ($type == 'news')
			{
				$url = new UrlTool($api);
				$data[$type] = [
					'articles' => array_map(function($v) use ($url){
						return [
							'title' => $v['title'],
							'description' => $v['description'],
							'url' => $url->getURL('wechat/news?id='.$v['id'], $this->user),
							'picurl' => url('attachment?id='.$v['cover_aid']),
						];
					}, $depot->news->toArray()),
				];
			} else if ($type == 'text') {
				$data[$type] = ['content' => $depot->text->content];
			} else if ($type == 'callback') {
				//
			} else if ($type == 'music') {
				$url = Attachment::findOrFail($depot->$type->thumb_aid)->url(NULL, true);
				$data[$type] = ['title' => $depot->$type->title, 'description' => $depot->$type->description, 'musicurl' => $url, 'hqmusicurl' => $url];
				!empty($depot->$type->thumb_aid) && $media = $this->uploadToWechat($api, Attachment::find($depot->$type->thumb_aid), 'image');
				$data[$type] += ['thumb_media_id' => !empty($media) ? $media['thumb_media_id'] : ''];
			} else {
				$media = $this->uploadToWechat($api, Attachment::find($depot->$type->aid), $type);
				$data[$type] = ['media_id' => $media['media_id']];

				if ($type == 'video')
				{
					!empty($depot->$type->thumb_aid) && $media = $this->uploadToWechat($api, Attachment::find($depot->$type->thumb_aid), 'image');
					$data[$type] += ['title' => $depot->$type->title, 'description' => $depot->$type->description, 'thumb_media_id' => !empty($media) ? $media['thumb_media_id'] : ''];
				}
			}

			slog('发送微信消息(来自素材库)');
			slog($this->media->toArray());
			//入库
			$message->wdid = $this->media->getKey();
		} else { //String

			$data += [
				'msgtype' => 'text',
				'text' => ['content' => $this->media],
			];
			//入库
			WechatMessageText::create(['id' => $message->getKey(), 'content' => $this->media]);
		}

		$message->type = $type;
		$message->save();

		return $api->sendCustomMessage($data);
	}

	private function uploadToWechat(API $api, Attachment $attachment, $type)
	{
		return $api->uploadMedia($attachment->create_symlink(NULL, NULL), $type, Mimes::getInstance()->mime_by_ext($attachment->ext));
	}

	public function reply(API $api)
	{
		if ($this->media instanceof WechatDepot) //素材
		{
			$depot = $this->media;
			$type = $depot->type;
			$depot->$type; //init read

			$message = WechatMessage::create(['waid' => $api->waid, 'wuid' => $this->user->getKey(), 'type' => $type, 'transport_type' => 'send', 'message_id' => '', 'wdid' => $depot->getKey()]);
			
			if ($type == 'news')
			{
				$url = new UrlTool($api);
				$data = array_map(function($v) use ($url){
					return [
						'Title' => $v['title'],
						'Description' => $v['description'],
						'Url' => $url->getURL('wechat/news?id='.$v['id'], $this->user),
						'PicUrl' => url('attachment?id='.$v['cover_aid']),
					];
				}, $depot->news->toArray());
				return $api->news($data)->reply([], true);
			} else if ($type == 'text') {
				return $api->text($depot->text->content)->reply([], true);
			} else if ($type == 'callback') {
				//
				return NULL;
			} else if ($type == 'music') {
				$url = Attachment::findOrFail($depot->music->thumb_aid)->url(NULL, true);
				!empty($depot->music->thumb_aid) && $media = $this->uploadToWechat($api, Attachment::find($depot->music->thumb_aid), 'image');
				return $api->music($depot->music->title, $depot->music->description, $url, $url, !empty($media) ? $media['thumb_media_id'] : '')->reply([], true);
			} else {
				$media = $this->uploadToWechat($api, Attachment::find($depot->$type->aid), $type);
				return $api->$type($media['media_id'], $depot->$type->title, $depot->$type->description)->reply([], true);
			}
		}
	}
}
