<?php
namespace Plugins\Attachment\App\Http\Controllers;

use Plugins\Attachment\App\Attachment;
use Plugins\Attachment\App\AttachmentFile;
use Addons\Core\Controllers\Controller;
use Illuminate\Http\Request;
use Addons\Core\File\Mimes;
use Addons\Core\Http\OutputResponse;
use Lang, Crypt, Agent, Image, Session, Auth;
class AttachmentController extends Controller {

	//public $permissions = ['uploaderQuery,avatarUploadQuery,fullavatarQuery,kindeditorUploadQuery,ueditorUploadQuery,dataurlUploadQuery,editormdUploadQuery,hashQuery' => 'attachment.create'];

	private $model;
	public function __construct()
	{
		//解决flash上传的cookie问题
		if (isset($_POST['PHPSESSIONID']))
		{
			$session_id = Crypt::decrypt(trim($_POST['PHPSESSIONID']));
			if (!empty($session_id))
			{
				session_id($session_id);
				Session::setId($session_id);
			}
		}

		$this->model = new Attachment();
	}

	public function download(Request $request, $id)
	{
		$id = intval($id);

		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);
		//获取远程文件
		$attachment->sync();

		$full_path = $attachment->full_path();
		$mime_type = Mimes::getInstance()->mime_by_ext($attachment->ext);
		$content_length = $attachment->size;
		$last_modified = $attachment->created_at;
		$etag = $attachment->hash;
		$cache = TRUE;
		return response()->download($full_path, $attachment->displayname, [], compact('mime_type', 'etag', 'last_modified', 'content_length', 'cache'));

	}

	public function info(Request $request, $id)
	{
		$id = intval($id);

		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);
		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);

		return $this->api($attachment->toArray());
	}

	public function index(Request $request, $id, $width = NULL, $height = NULL, $m = NULL)
	{
		$id = intval($id);
		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);

		if ($attachment->file_type() == 'image')
		{
			if (!empty($m))
				return $this->watermark($request, $id, $m, $width, $height);
			else if (!empty($width) || !empty($height))
				return $this->resize($request, $id, $width, $height);
			else
			{
	 			if ( Agent::isMobile() && !Agent::isTablet() )
					return $this->phone($request, $id);
				else
					return $this->preview($request, $id);
			}
		}
		else
		{
			return $this->download($request, $id);
		}
	}

	public function resize(Request $request, $id, $width = NULL, $height = NULL, $m = NULL)
	{
		if (!empty($m)) return $this->watermark($request, $id, $m, $width, $height);
		
		$id = intval($id);
		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);

		if ($attachment->file_type() != 'image')
			return $this->failure('attachment::attachment.failure_image');


		//获取远程文件
		$attachment->sync();

		$full_path = $attachment->full_path();
		$size = getimagesize($full_path);
		if ((!empty($width) && $size[0] > $width) || (!empty($height) && $size[1] > $height))
		{
			$wh = aspect_ratio($size[0], $size[1], $width, $height);extract($wh);
			$new_path = storage_path(str_replace('.','[dot]',$attachment->relative_path()).';'.$width.'x'.$height.'.'.$attachment->ext);
			if (!file_exists($new_path))
			{
				$img = Image::make($full_path);
				!is_dir($path = dirname($new_path)) && mkdir($path, 0777, TRUE);
				$img->resize($width, $height, function ($constraint) {$constraint->aspectRatio();})->save($new_path);
				unset($img);
			}
		} else
			$new_path = $full_path;
		$mime_type = Mimes::getInstance()->mime_by_ext($attachment->ext);
		$content_length = NULL;//$attachment->size;
		$last_modified = true;
		$etag = $attachment->hash; //只要网址一样，输出同一个etag
		$cache = TRUE;
		return response()->preview($new_path, [], compact('mime_type', 'etag', 'last_modified', 'content_length', 'cache'));
	}

	public function phone(Request $request, $id)
	{
		$id = intval($id);
		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);

		if ($attachment->file_type() == 'image')
 			return $this->resize($request, $id, 640, 960);
		else
			return $this->preview($request, $id);
	}

	public function preview(Request $request, $id)
	{
		$id = intval($id);
		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);
		//获取远程文件
		$attachment->sync();

		$full_path = $attachment->full_path();
		$mime_type = Mimes::getInstance()->mime_by_ext($attachment->ext);
		$content_length = $attachment->size;
		$last_modified = $attachment->created_at;
		$etag = $attachment->hash;
		$cache = TRUE;
		return response()->preview($full_path, [], compact('mime_type', 'etag', 'last_modified', 'content_length', 'cache'));
	}

	public function watermark(Request $request, $id, $m, $width = NULL, $height = NULL)
	{
		$id = intval($id);
		if (empty($id) || empty($m))
			return $this->error_param()->setStatusCode(404);

		$watermark_path = is_numeric($m) ? (($a = $this->model->get($m)) ? $a->full_path() : '') : base_path($m);
		if (empty($watermark_path) || !file_exists($watermark_path))
			return $this->failure('attachment::attachment.failure_watermark');

		$attachment = $this->model->get($id);

		if (empty($attachment))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);
		else if(empty($attachment->afid))
			return $this->failure('attachment::attachment.failure_file_noexists')->setStatusCode(404);

		if ($attachment->file_type() != 'image')
			return $this->failure('attachment::attachment.failure_image');

		//获取远程文件
		$attachment->sync();

		$full_path = $attachment->full_path();
		$size = getimagesize($full_path);
		if (!empty($width) || !empty($height)) {$wh = aspect_ratio($size[0], $size[1], $width, $height);extract($wh);} else list($width, $height) = $size;
		$new_path = storage_path(str_replace('.','[dot]',$attachment->relative_path()).';'.$width.'x'.$height.';'.md5($watermark_path).'.'.$attachment->ext);

		if (!file_exists($new_path))
		{
			$img = Image::make($full_path);
			!is_dir($path = dirname($new_path)) && mkdir($path, 0777, TRUE);
			($size[0] != $width || $size[1] != $height) && $img->resize($width, $height, function ($constraint) {$constraint->aspectRatio();});
			$size = getimagesize($watermark_path);
			$wh = aspect_ratio($size[0], $size[1], $width * 0.2, $height * 0.2);
			$wm = Image::make($watermark_path)->resize($wh['width'], $wh['height']);
			$img->insert($wm, 'bottom-right', 7, 7)->save($new_path);
			unset($img);
		}

		$mime_type = Mimes::getInstance()->mime_by_ext($attachment->ext);
		$content_length = NULL;//$attachment->size;
		$last_modified = true;
		$etag = $attachment->hash; //只要网址一样，输出同一个etag
		$cache = TRUE;
		return response()->preview($new_path, [], compact('mime_type', 'etag', 'last_modified', 'content_length', 'cache'));
	}

	public function redirect(Request $request, $id)
	{
		$id = intval($id);
		if (empty($id))
			return $this->error_param()->setStatusCode(404);

		$link_path = $this->model->get($id)->get_symlink_url();

		if (empty($link_path))
			return $this->failure('attachment::attachment.failure_noexists')->setStatusCode(404);

		return redirect($link_path);
	}

	public function uploaderQuery(Request $request)
	{
		$uuid = $request->input('uuid') ?: '';
		$count = $request->input('chunks') ?: 1;
		$index = $request->input('chunk') ?: 0;
		$total = $request->input('total') ?: 0;
		$start = $request->input('start') ?: 0;
		$end = $request->input('end') ?: 0;
		$hash = $request->input('hash') ?: '';

		if (!isset($_FILES['Filedata']))
			return $this->error_param();

		$attachment = $this->model->upload($request->user(), 'Filedata', compact('uuid', 'count', 'index', 'start', 'end', 'total', 'hash'));
		if (!($attachment instanceof Attachment))
			return $this->failure_attachment($attachment);
		return $this->success('', FALSE, $attachment->toArray());
	}

	public function hashQuery(Request $request)
	{
		$hash = $request->input('hash');
		$size = $request->input('size');
		$filename = $request->input('filename');
		$ext = $request->input('ext');

		if (empty($hash) || empty($size) || empty($filename))
			return $this->error_param()->setStatusCode(404);
		$attachment = $this->model->hash($request->user(), $hash, $size, $filename);
		if (!($attachment instanceof Attachment))
			return $this->failure_attachment($attachment);
		return $this->success(null, FALSE, $attachment->toArray());
	}

	public function editormdUploadQuery(Request $request)
	{
		$data = array('success' => 1, 'message' => '');
		$attachment = $this->model->upload($request->user(), 'editormd-image-file');
		if (!($attachment instanceof Attachment))
		{
			$data = array('success' => 0, 'message' => $this->read_message($attachment));
		} else {
			$data['url'] = $attachment->url();
		}
		return (new OutputResponse)->setData($data, true);
	}

	public function kindeditorUploadQuery(Request $request)
	{
		$data = array('error' => 0, 'url' => '');
		
		$attachment = $this->model->upload($request->user(), 'Filedata');
		if (!($attachment instanceof Attachment))
		{
			$data = array('error' => 1, 'message' => $this->read_message($attachment));
		} else
			$data['url'] = $attachment->url();
		
		return (new OutputResponse)->setData($data, true);
	}

	public function ueditorUploadQuery(Request $request, $start = 0, $size = NULL)
	{
		$data = array();
		$_config = config('attachment');
		$action = $request->input('action');
		$page = !empty($size) ? ceil($start / $size) : 1;
		switch ($action) {
			case 'config':
				$data = array(
					/* 上传图片配置项 */
					'imageActionName' => 'uploadimage', /* 执行上传图片的action名称 */
					'imageFieldName' => 'Filedata', /* 提交的图片表单名称 */
					'imageCompressEnable' => true, /* 是否压缩图片,默认是true */
					'imageCompressBorder' => 1600, /* 图片压缩最长边限制 */
					'imageUrlPrefix' => '',
					'imageInsertAlign' => 'none', /* 插入的图片浮动方式 */
					'imageAllowFiles' => array_map(function($v) {return '.'.$v;}, $_config['file_type']['image']),
					/* 涂鸦图片上传配置项 */
					'scrawlActionName' => 'uploadscrawl', /* 执行上传涂鸦的action名称 */
					'scrawlFieldName' => 'Filedata', /* 提交的图片表单名称 */
					'scrawlUrlPrefix' => '', /* 图片访问路径前缀 */
					'scrawlInsertAlign' => 'none',
					/* 截图工具上传 */
					'snapscreenActionName' => 'uploadimage', /* 执行上传截图的action名称 */
					'snapscreenUrlPrefix' => '', /* 图片访问路径前缀 */
					'snapscreenInsertAlign' => 'none', /* 插入的图片浮动方式 */
					/* 抓取远程图片配置 */
					'catcherLocalDomain' => array('127.0.0.1', 'localhost', 'img.bidu.com'),
					'catcherActionName' => 'catchimage', /* 执行抓取远程图片的action名称 */
					'catcherFieldName' => 'Filedata', /* 提交的图片列表表单名称 */
					'catcherUrlPrefix' => '', /* 图片访问路径前缀 */
					'catcherAllowFiles' => array_map(function($v) {return '.'.$v;}, $_config['file_type']['image']),
					/* 上传视频配置 */
					'videoActionName' => 'uploadvideo', /* 执行上传视频的action名称 */
					'videoFieldName' => 'Filedata', /* 提交的视频表单名称 */
					'videoUrlPrefix' => '', /* 视频访问路径前缀 */
					'videoAllowFiles' => array_map(function($v) {return '.'.$v;}, $_config['file_type']['video'] + $_config['file_type']['audio']),
					/* 上传文件配置 */
					'fileActionName' => 'uploadfile', /* controller里,执行上传视频的action名称 */
					'fileFieldName' => 'Filedata', /* 提交的文件表单名称 */
					'fileUrlPrefix' => '', /* 文件访问路径前缀 */
					'fileAllowFiles' => array_map(function($v) {return '.'.$v;}, $_config['ext']),
					/* 列出指定目录下的图片 */
					'imageManagerActionName' => 'listimage', /* 执行图片管理的action名称 */
					'imageManagerInsertAlign' => 'none', /* 插入的图片浮动方式 */
					'imageManagerUrlPrefix' => '',
					/* 列出指定目录下的文件 */
					'fileManagerActionName' => 'listfile', /* 执行文件管理的action名称 */
					'fileManagerUrlPrefix' => '',
				);
				break;
			 /* 上传图片 */
			case 'uploadimage':
			/* 上传视频 */
			case 'uploadvideo':
			/* 上传文件 */
			case 'uploadfile':
				$attachment = $this->model->upload($request->user(), 'Filedata');
				$data = !($attachment instanceof Attachment) ? array('state' => $this->read_message($attachment)) : array(
					'state' => 'SUCCESS',
					'url' => $attachment->url(),
					'title' => $attachment->original_basename,
					'original' => $attachment->original_basename,
					'type' => !empty($attachment->ext) ? '.'.$attachment->ext : '',
					'size' => $attachment->size,
				);
				break;
			/* 上传涂鸦 */
			case 'uploadscrawl':
				$file_path = tempnam(sys_get_temp_dir(),'');
				$fp = fopen($file_path,'wb+');
				fwrite($fp, base64_decode($_POST['Filedata']));
				fclose($fp);
				$attachment = $this->model->savefile($request->user(), $file_path, 'scrawl_'.(Auth::check() ? $request->user()->getKey() : 0).'_'.date('Ymdhis').'.png');
				$data = !($attachment instanceof Attachment) ? array('state' => $this->read_message($attachment)) : array(
					'state' => 'SUCCESS',
					'url' => $attachment->url(),
					'title' => $attachment->original_basename,
					'original' => $attachment->original_basename,
					'type' => !empty($attachment->ext) ? '.'.$attachment->ext : '',
					'size' => $attachment->size,
				);
				break;
			/* 抓取远程文件 */
			case 'catchimage':
				$url = isset($_POST['Filedata']) ? $_POST['Filedata'] : $_GET['Filedata'];
				$urls = to_array($url);$list = array();
				foreach ($urls as $value) {
					$attachment = $this->model->download($request->user(), $value);
					$list[] = !($attachment instanceof Attachment) ? array('state' => $this->read_message($attachment), 'source' => $value) : array (
						'state' => 'SUCCESS',
						'url' => $attachment->url(),
						'title' => $attachment->original_basename,
						'original' => $attachment->original_basename,
						'size' => $attachment->size,
						'source' => $value,
					);
				}
				$data = array(
					'state'=> !empty($list) ? 'SUCCESS' : 'ERROR',
					'list'=> $list,
				);
				break;
			 /* 列出图片 */
			case 'listimage':
			/* 列出文件 */
			case 'listfile':
				$list = $this->model->whereIn('ext', $_config['file_type']['image'])->orderBy('created_at', 'DESC')->paginate($size, ['*'], 'page', $page);
				
				$urls = [];
				foreach($list as $v)
					$urls[] = [ 'url' => $v->url() ];

				$data = array(
					'state' => 'SUCCESS',
					'list' => $urls,
					'start' => $list->firstItem(),
					'total' => $list->total(),
				);
				break;
			default:
				break;
		}
		return (new OutputResponse)->setData($data, true);
	}

	public function avatarUploadQuery(Request $request)
	{

		$input = file_get_contents('php://input');
		$data = explode('--------------------', $input);
		//@file_put_contents('./avatar_1.jpg', $data[0]);
		$file_path = tempnam(sys_get_temp_dir(),'');
		$fp = fopen($file_path,'wb+');
		fwrite($fp, $data[0]);
		fclose($fp);

		$attachment = $this->model->savefile($request->user(), $file_path, 'avatar_'.(Auth::check() ? $request->user()->getKey() : 0).'_'.date('Ymdhis').'.jpg');
		return $this->success('', $url, array('id' => $attachment->getKey(), 'url' => $attachment->url()));
	}

	public function fullavatarQuery(Request $request)
	{
		$_config = config('attachment');
		$result = ['success' => true];
		if (isset($_FILES['__source']))
		{
			$attachment = $this->model->upload($request->user(), '__source');
			if (!($attachment instanceof Attachment))
				$result = ['success' => false, 'message' => $this->read_message($attachment)];
			else
				$result['original_aid'] = $attachment['id'];
		}
		if ($result['success'])
		foreach (['__avatar1', '__avatar2', '__avatar3'] as $v) {
			if (isset($_FILES[$v]) && is_uploaded_file($_FILES[$v]["tmp_name"]) && !$_FILES[$v]["error"]){
				$attachment = $this->model->savefile($request->user(), $_FILES[$v]["tmp_name"], $v.(Auth::check() ? $request->user()->getKey() : 0).'_'.date('Ymdhis').'.jpg');
				if (!($attachment instanceof Attachment))
					$result = ['success' => false, 'message' => $this->read_message($attachment)];
				else
					$result['avatar_aids'][] = $attachment['id'];
			}
		}

		return (new OutputResponse)->setData($result, true);
	}

	public function dataurlUploadQuery(Request $request)
	{
		$dataurl = $request->post('DataURL');
		
		$part = parse_dataurl($dataurl);
		$ext = Mimes::getInstance()->ext_by_mime($part['mine']);
		$data = $part['data'];
		$file_path = tempnam(sys_get_temp_dir(),'');
		$fp = fopen($file_path,'wb+');
		fwrite($fp, $data);
		fclose($fp);
		unset($dataurl, $data, $part);

		$attachment = $this->model->savefile($request->user(), $file_path, 'datauri_'.(Auth::check() ? $request->user()->getKey() : 0).'_'.date('Ymdhis').'.'.$ext);
		return $this->success('', $url, array('id' => $attachment->getKey(), 'url' => $attachment->url()));
	}


	private function read_message($message_field)
	{
		$_config = config('attachment');
		$_data =  ['maxsize' => $_config['maxsize'], 'ext' => implode(',', $_config['ext'])];
		return Lang::has($message = 'attachment.'.$message_field.'.content') ? trans($message, $_data) : trans('attachment::'.$message, $_data);
	}

	private function failure_attachment($error_no, $url = FALSE)
	{
		$_config = config('attachment');
		return $this->failure(Lang::has($message = 'attachment.'.$error_no.'.content') ? $message : 'attachment::'.$message, $url, ['maxsize' => format_bytes($_config['maxsize']), 'ext' => implode(',', $_config['ext'])]);
	}
}