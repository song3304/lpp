<?php
namespace Plugins\Attachment\App;

use App\Model;
use Illuminate\Database\Eloquent\Model as BaseModel;
use \Curl\Curl;
use Addons\Core\SSH;
use Plugins\Attachment\App\AttachmentFile;
use Plugins\Attachment\App\AttachmentChunk;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use DB, Cache;
class Attachment extends Model{
	
	protected $guarded = ['id'];
	protected $hidden = ['path', 'afid', 'basename'];

	const UPLOAD_ERR_MAXSIZE = 100;
	const UPLOAD_ERR_EMPTY = 101;
	const UPLOAD_ERR_EXT = 102;
	const UPLOAD_ERR_SAVE = 106;
	const DOWNLOAD_ERR_URL = 104;
	const DOWNLOAD_ERR_FILE = 105;

	private $fileModel,$_config;

	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);
		$this->fileModel = new AttachmentFile();
		$this->_config = config('attachment');
	}

	public function file_type()
	{
		static $file_type;
		if (!empty($file_type)) return $file_type;

		$ext = $this->ext;
		foreach ($this->_config['file_type'] as $key => $value)
			if (in_array($ext, $value)) return $file_type = $key;

		return NULL;
	}

	public function file()
	{
		return $this->hasOne(get_namespace($this).'\\AttachmentFile', 'id', 'afid');
	}

	public function chunks()
	{
		return $this->hasMany(get_namespace($this).'\\AttachmentChunk', 'aid', 'id');
	}

	public function full_path()
	{
		return $this->get_real_path();
	}

	public function real_path()
	{
		return $this->get_real_path();
	}

	public function relative_path()
	{
		return $this->get_relative_path();
	}

	/**
	 * 构造一个符合router标准的URL
	 * 
	 * @param  integer $id      AID
	 * @param  boolean $protocol 是否有域名部分
	 * @param  string $filename  需要放在网址结尾的文件名,用以欺骗浏览器
	 * @return string
	 */
	public function url($filename = NULL)
	{
		empty($filename) && $filename = $this->original_basename;
		return url('attachment/'.$this->getKey().'/'.urlencode($filename));
	}

	/**
	 * 获取软连接的网址
	 * 
	 * @return string
	 */
	public function symlink_url()
	{
		$path = $this->create_symlink(NULL);
		if (empty($path))
			return FALSE;

		return url(str_replace(base_path(), '', $path));
	}

	public function upload($uid, $field_name, $chunks = [], $extra = [])
	{
		$request = app('request');
		$file = $request->file($field_name);
		if (!$request->hasFile($field_name) || !$file->isValid()) {
			return $file->getError();
		}

		//ignore_user_abort(TRUE);
		set_time_limit(0);

		if (empty($chunks)  || $chunks['count'] == 1) //只有一个分块
			return $this->savefile($uid, $file, NULL, NULL, $extra);
		else
			return $this->savechunk($uid, $file, $chunks, NULL, NULL, $extra);
	}

	public function download($uid, $url, $newFileName = NULL, $newExt = NULL)
	{
		if (empty($url))
			return static::DOWNLOAD_ERR_URL;

		ignore_user_abort(true);
		set_time_limit(0);

		$stack = HandlerStack::create();
		$stack->push(
			Middleware::log(
				app('log'),
				new MessageFormatter('GuzzleHttp {uri}'.PHP_EOL.PHP_EOL.'{request}'.PHP_EOL.PHP_EOL.'{response}'.PHP_EOL.PHP_EOL.'{error}')
			)
		);
		$file_path = tempnam(storage_path('utils'),'download-');

		try {
			 $client = new \GuzzleHttp\Client([
				'handler' => $stack,
				'verify' => false,
				'sink' => $file_path,
			]);
			$res = $client->get($url);
		} catch (Exception $e) {
			return static::DOWNLOAD_ERR_FILE;
		}
		if ($res->getStatusCode() != 200)
			return static::DOWNLOAD_ERR_FILE;

		$download_filename = $res->getHeader('Content-Disposition');

		$basename = mb_basename($url);//pathinfo($url,PATHINFO_BASENAME);
		if (!empty($download_filename))
		{
			if (preg_match('/filename\s*=\s*(\S*)/i',  $download_filename, $matches))
				$basename = mb_basename(trim($matches[1],'\'"'));
		}

		return $this->savefile($uid, new SymfonyUploadedFile($file_path, $basename), $newFileName, $newExt, ['description' => 'Download from:' . $url]);
	}

	public function hash($uid, $hash, $size, $original_basename, $newFileName = NULL, $newFileExt = NULL, $extra = NULL)
	{
		if (empty($hash) || empty($size))
			return FALSE;
		$file = $this->fileModel->get_byhash($hash, $size);
		if (empty($file))
			return FALSE;

		is_null($newFileExt) && $newFileExt = strtolower(pathinfo($original_basename, PATHINFO_EXTENSION));
		is_null($newFileName) && $newFileName = mb_basename($original_basename, '.'.$newFileExt); //支持中文的basename
		
		$attachment = $this->create([
			'afid' => $file->getKey(),
			'filename' => $newFileName,
			'ext' => $newFileExt,
			'original_basename' => $original_basename,
			'description' => !empty($extra['description']) ? $extra['description'] : '',
			'uid' => $uid instanceof BaseModel ? $uid->getKey() : $uid,
		]);
		return $this->get($attachment->getKey());
	}

	private function _saveCheck(SymfonyUploadedFile $uploadedFile, &$size = 0, &$newFileName = NULL, &$newFileExt = NULL)
	{
		if (!$uploadedFile->isFile())
			return FALSE;

		is_null($newFileExt) && $newFileExt = strtolower($uploadedFile->getClientOriginalExtension());
		is_null($newFileName) && $newFileName = mb_basename($uploadedFile->getClientOriginalName(), '.'.$newFileExt); //支持中文的basename

		$size = $uploadedFile->getSize();

		if(!in_array($newFileExt, $this->_config['ext']))
			return static::UPLOAD_ERR_EXT;
		if ($size > $this->_config['maxsize'])
			return static::UPLOAD_ERR_MAXSIZE;
		if (empty($size))
			return static::UPLOAD_ERR_EMPTY;

		return TRUE;
	}


	public function savechunk($uid, SymfonyUploadedFile $uploadedFile, $chunks, $newFileName = NULL, $newFileExt = NULL, $extra = [])
	{
		if (empty($chunks['uuid']) || empty($chunks['count'])) return FALSE;

		$size = 0;
		$r = $this->_saveCheck($uploadedFile, $size, $newFileName, $newFileExt);
		if ($r !== TRUE) return $r;

		//传文件都耗费了那么多时间,还怕md5?
		$hash = md5_file($uploadedFile->getPathName());

		DB::beginTransaction();
		//此处如果使用先select再insert，容易出现uuid重复冲突，如果在select中启用for update，则会DeadLock
		//在本地调试时，分块文件会同时上传（差异时间可忽略），导致同时进行的select为空，却重复在插入，即使使用文件锁，for update都不行。只能使用SyncMutex解决问题，但是需要安装sync插件
		//最终选择了insert into  on duplicate key update 这种方式来解决
		$attachment = $this->insertUpdate([
			'uuid' => $chunks['uuid'],
			'chunk_count' => $chunks['count'],
			'afid' => 0,
			'filename' => $newFileName,
			'ext' => $newFileExt,
			'original_basename' => $file->getClientOriginalName(),
			'description' => !empty($extra['description']) ? $extra['description'] : '',
			'uid' => $uid instanceof BaseModel ? $uid->getKey() : $uid,
		]);

		$new_basename = $this->_get_hash_basename();
		$new_hash_path = $this->get_hash_path($new_basename);
		if (!$this->_save_file($uploadedFile->getPathName(), $new_basename))
		{
			DB::rollback();
			return static::UPLOAD_ERR_SAVE;
		}

		AttachmentChunk::create([
			'aid' => $attachment->getKey(),
			'basename' => $new_basename,
			'path' => $new_hash_path,
			'hash' => $hash,
			'size' => $size,
			'index' => $chunks['index'],
			'start' => $chunks['start'],
			'end' => $chunks['end'],
		]);
		DB::commit();
		$this->_mergeChunk($attachment->getKey());

		return $this->get($attachment->getKey());
	}

	private function _mergeChunk($aid)
	{
		DB::beginTransaction();
		$attachment = static::where($this->getKeyName(), $aid)->lockForUpdate()->first();

		if ($attachment->chunks()->count() != $attachment->chunk_count || !empty($attachment->afid))
		{
			DB::rollback();
			return FALSE; //未完整的得到文件
		}

		//合并文件
		$merged_file_path = tempnam(sys_get_temp_dir(), 'attachment-'.$attachment->getKey());
		$fw = fopen($merged_file_path, 'w');
		foreach ($attachment->chunks()->orderBy('index', 'ASC')->get() as $chunk) //严格模式下$attachment->chunks()->count()不能有orderBy，所以将orderBy放在这里
		{
			$path = $this->get_real_path($chunk->path);
			if (!file_exists($path)) {DB::rollback();return FALSE;}
			$fr = fopen($path, 'rb');
			while(!feof($fr))
			{
				$stream = fread($fr, $this->_config['write_cache']);
				fwrite($fw, $stream);unset($stream);
			}
			fclose($fr);
			@unlink($path); //读取完毕就删除，没有保留的必要
		}
		fclose($fw);

		$size = filesize($merged_file_path);
		//传文件都耗费了那么多时间,还怕md5?
		$hash = md5_file($merged_file_path);

		$file = $this->fileModel->get_byhash($hash, $size);
		if (empty($file))
		{
			$new_basename = $this->_get_hash_basename();
			$new_hash_path = $this->get_hash_path($new_basename);
			if (!$this->_save_file($merged_file_path, $new_basename)) {
				DB::rollback();
				return static::UPLOAD_ERR_SAVE;
			}

			$file = AttachmentFile::create([
				'basename' => $new_basename,
				'path' => $new_hash_path,
				'hash' => $hash,
				'size' => $size,
			]);
		} else
			@unlink($merged_file_path);

		$attachment->update(['afid' => $file->getKey()]);
		DB::commit();

		return TRUE;
	}

	public function savefile($uid, SymfonyUploadedFile $uploadedFile, $newFileName = NULL, $newFileExt = NULL, $extra = [])
	{
		$size = 0;
		$r = $this->_saveCheck($uploadedFile, $size, $newFileName, $newFileExt);
		if ($r !== TRUE) return $r;

		//传文件都耗费了那么多时间,还怕md5?
		$hash = md5_file($uploadedFile->getPathName());

		$file = $this->fileModel->get_byhash($hash, $size);
		if (empty($file))
		{
			$new_basename = $this->_get_hash_basename();
			$new_hash_path = $this->get_hash_path($new_basename);

			if (!$this->_save_file($uploadedFile->getPathName(), $new_basename))
				return static::UPLOAD_ERR_SAVE;

			$file = AttachmentFile::create([
				'basename' => $new_basename,
				'path' => $new_hash_path,
				'hash' => $hash,
				'size' => $size,
			]);
		}
		else //已经存在此文件
			@unlink($uploadedFile->getPathName());

		$attachment = $this->create([
			'afid' => $file->getKey(),
			'filename' => $newFileName,
			'ext' => $newFileExt,
			'original_basename' => $uploadedFile->getClientOriginalName(),
			'description' => !empty($extra['description']) ? $extra['description'] : '',
			'uid' => $uid instanceof BaseModel ? $uid->getKey() : $uid,
		]);
		//当前Model更新
		//$this->setRawAttributes($attachment->getAttributes(), true);
		return $this->get($attachment->getKey());
	}

	public function get($id)
	{
		$attachment = static::findByCache($id);
		if (!empty($attachment) && !empty($attachment->afid))
		{
			$result = $attachment->getAttributes() + AttachmentFile::findByCache($attachment->afid)->getAttributes();
			$result['displayname'] = $result['filename'].(!empty($result['ext']) ?  '.'.$result['ext'] : '' );
			//Model更新
			$attachment->setRawAttributes($result, true);
		}
		return $attachment;
	}

	/**
	 * 根据数据库中的路径得到绝对路径
	 * 
	 * @param  string $hash_path 数据库中取出的路径
	 * @return string                绝对路径
	 */
	private function get_real_path($hash_path = NULL)
	{
		return base_path($this->get_relative_path($hash_path));
	}

	/**
	 * 根据数据库中的路径得到远程绝对路径
	 * 
	 * @param  string $hash_path 数据库中取出的路径
	 * @return string                远程绝对路径
	 */
	private function get_remote_path($hash_path = NULL)
	{
		empty($hash_path) && $hash_path = $this->file->path;
		return $this->_config['remote']['path'].$hash_path;
	}

	/**
	 * 根据数据库中的路径获得文件的相对路径
	 * 	
	 * @param  string $hash_path 数据库中的路径
	 * @return string            相对路径
	 */
	private function get_relative_path($hash_path = NULL)
	{
		empty($hash_path) && $hash_path = $this->file->path;
		return $this->_config['local']['path'].$hash_path;
	}

	/**
	 * 根据附件名称获得相对路径
	 * 	
	 * @param  string $basename 附件文件名
	 * @return string           相对路径
	 */
	protected function get_hash_path($basename = NULL)
	{
		empty($basename) && $basename = $this->file->basename;
		$md5 = md5($basename . md5($basename));
		return $md5[0].$md5[1].'/'.$md5[2].$md5[3].'/'.$md5[4].$md5[5].','.$basename;
	}

	/**
	 * 获取一个不存在的文件名称
	 * 
	 * @return [type] [description]
	 */
	protected function _get_hash_basename()
	{
		do
		{
			$basename = uniqid(date('YmdHis,') . rand(100000,999999) . ',')  . (!empty($this->_config['normal_ext']) ? '.' . $this->_config['normal_ext'] : '');
			$file = $this->fileModel->get_bybasename($basename);
		} while (!empty($file));
		return $basename;
	}

	/**
	 * 在cache目录下创建一个软连接
	 * 
	 * @param  integer $id AID
	 * @return string
	 */
	public function create_symlink($path = NULL, $life_time = 86400)
	{
		//将云端数据同步到本地
		$this->remote && $this->sync();
		$path = !empty($path) ? $path : storage_path($this->_config['local']['path'].'attachment,'.md5($this->getKey()).'.'.$this->ext);
		@unlink($path);
		symlink($this->full_path(), $path);

		//!empty($life_time) && delay_unlink($path, $life_time);
		return $path;
	}

	/**
	 * 在cache目录下创建一个硬连接
	 * 
	 * @param  integer $id AID
	 * @return string
	 */
	public function create_link($path = NULL, $life_time = 86400)
	{
		//将云端数据同步到本地
		$this->remote && $this->sync();
		$path = !empty($path) ? $path : storage_path($this->_config['local']['path'].'attachment,'.md5($this->getKey()).'.'.$this->ext);
		@unlink($path);
		link($this->full_path(), $path);

		//!empty($life_time) && delay_unlink($path, $life_time);
		return $path;
	}

	/**
	 * 在cache目录下创建一个副本
	 * 
	 * @param  integer $id AID
	 * @return string
	 */
	public function create_backup($path = NULL, $life_time = 86400)
	{
		//将云端数据同步到本地
		$this->remote && $this->sync();
		$path = !empty($path) ? $path : storage_path($this->_config['local']['path'].'attachment,'.md5($this->getKey()).'.'.$this->ext);
		@unlink($path);
		copy($this->full_path(), $path);

		//!empty($life_time) && delay_unlink($path, $life_time);
		return $path;
	}

	protected function _save_file($original_file_path, $new_basename)
	{
		$result = FALSE;
		$new_hash_path = $this->get_hash_path($new_basename);
		
		if ($this->_config['remote']['enabled']) //远程存储打开
		{
			$ssh = new SSH((array)$this->_config['remote']);

			$newpath = $this->get_remote_path($new_hash_path);
			$dir = dirname($newpath);

			!$ssh->is_dir($dir) && @$ssh->mkdir($dir, $this->_config['remote']['folder_mod'], TRUE);
			!empty($this->_config['remote']['folder_own']) && @$ssh->chown($dir, $this->_config['remote']['folder_own']);
			!empty($this->_config['remote']['folder_grp']) && @$ssh->chgrp($dir, $this->_config['remote']['folder_grp']);

			if (!($result = @$ssh->send_file($original_file_path, $newpath)))
			{
				@unlink($original_file_path);
				return FALSE;
			}
			@$ssh->chmod($newpath, $this->_config['remote']['file_mod']);
			!empty($this->_config['remote']['file_own']) && @$ssh->chown($newpath, $this->_config['remote']['file_own']);
			!empty($this->_config['remote']['file_grp']) && @$ssh->chgrp($newpath, $this->_config['remote']['file_grp']);
		}

		if ($this->_config['local']['enabled']) //本地存储打开
		{
			$newpath = $this->get_real_path($new_hash_path);
			$dir = dirname($newpath);

			!is_dir($dir) && @mkdir($dir, $this->_config['local']['folder_mod'], TRUE);
			!empty($this->_config['local']['folder_own']) && @chown($dir, $this->_config['local']['folder_own']);
			!empty($this->_config['local']['folder_grp']) && @chgrp($dir, $this->_config['local']['folder_grp']);
			if(is_uploaded_file($original_file_path))
				$result = @move_uploaded_file($original_file_path, $newpath);
			else
			{
				if (!($result = @rename($original_file_path, $newpath)))
				{
					$result = @copy($original_file_path, $newpath);
				}
			}
			if ($result)
			{
				@chmod($newpath, $this->_config['local']['file_mod']);
				!empty($this->_config['local']['file_own']) && @chown($newpath, $this->_config['local']['file_own']);
				!empty($this->_config['local']['file_grp']) && @chgrp($newpath, $this->_config['local']['file_grp']);
			}
		}
		@unlink($original_file_path);
		return $result;
	}

	public function sync($life_time = NULL)
	{
		if ($this->_config['remote']['enabled'])
		{
			$path = $this->file->path;
			$local = $this->get_real_path($path);
			$remote = $this->get_remote_path($path);

			//如果本地存在，就放弃下载
			if (file_exists($local)) return TRUE;

			$dir = dirname($local);
			!is_dir($dir) && @mkdir($dir, $this->_config['local']['folder_mod'], TRUE);
			!empty($this->_config['local']['folder_own']) && @chown($dir, $this->_config['local']['folder_own']);
			!empty($this->_config['local']['folder_grp']) && @chgrp($dir, $this->_config['local']['folder_grp']);

			$ssh = new SSH((array)$this->_config['remote']);
			$ssh->receive_file($remote, $local);
			!empty($this->_config['local']['file_own']) && @chown($newpath, $this->_config['local']['file_own']);
			!empty($this->_config['local']['file_grp']) && @chgrp($newpath, $this->_config['local']['file_grp']);

			//过期文件 删除
			is_null($life_time) && !$this->_config['local']['enabled'] && $life_time = $this->_config['local']['life_time'];
			//!empty($life_time) && delay_unlink($local, $life_time);
		}
		return TRUE;
	}

	public function __sleep()
	{
		return array_diff(array_keys(get_object_vars($this)), ['_config', 'fileModel']);;
	}

	public function __wakeup()
	{
		parent::__wakeup();
		$this->fileModel = new AttachmentFile();
		$this->_config = config('attachment');
	}
}