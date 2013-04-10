<?php

/*
Plugin Name: Github MSync
Plugin URI: http://moyo.uuland.org/github-msync/
Description: 使用Github作为版本控制系统，可以自动同步文章
Author: Moyo
Author URI: http://moyo.uuland.org/
Version: 0.1
Stable tag: 0.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// check
function_exists( 'add_action' ) || exit('Access Deined');

// actions
add_action( 'admin_menu', 'mgi_admin_menu' );
add_action( 'wp_ajax_mgi-processing', 'mgi_ajax_processing' );

// functions
function mgi_admin_menu() { mgi::app()->admin_menu(); }
function mgi_admin_dashboard() { mgi::app()->admin_dashboard(); }
function mgi_ajax_processing() { mgi::app()->ajax_processing(); }
function mgi_service_callback() { mgi::app()->service_callback(); }

// main class
class mgi
{
	/**
	 * HTTP GET Function
	 * @var type 
	 */
	private $hgfunc = 'file_get_contents';
	/**
	 * Github API BASE
	 * @var type 
	 */
	private $github_api_base = 'https://api.github.com';
	/**
	 * Admin Page
	 * @var type 
	 */
	private $mgr_link = 'mgi-dashboard';
	/**
	 * Plugin ID
	 * @var type 
	 */
	private $guid = 'github-msync';
	/**
	 * 外部实例接口
	 */
	public static function app()
	{
		static $object = null;
		if (is_null($object))
		{
			$object = new self();
		}
		return $object;
	}
	/**
	 * 管理菜单
	 */
	public function admin_menu()
	{
		add_submenu_page('plugins.php', __($this->mgr_link), __('Github Sync'), 'manage_options', $this->mgr_link, 'mgi_admin_dashboard');
	}
	/**
	 * 管理首页
	 */
	public function admin_dashboard()
	{
		if (isset($_POST['submit']))
		{
			$this->admin_config_save();
		}
		$ops = isset($_GET['op']) ? $_GET['op'] : 'status';
		get_option('mgi-timestamp-saved') > 0 || $ops = 'config';
		$call = 'admin_'.$ops;
		if (method_exists($this, $call))
		{
			$this->$call();
		}
		else
		{
			echo 'REQUEST ERROR';
		}
	}
	/**
	 * 状态页面
	 */
	public function admin_status()
	{
		$github = $this->git_api_params();
		$last7logs = $this->git_last_7logs();
		$lastsince = $this->status_last_since();
?>
<style type="text/css">
	.status-box {
		margin: 20px 10px;
	}
	.status-box .title {
		padding: 10px;
		border-bottom: 2px solid #ccc;
		font-size: 20px;
		font-weight: bold;
		clear: both;
	}
	.status-box .github {
		
	}
	.status-box .repo {
		border: 1px solid #999;
		background: #ccc;
		padding: 10px;
		float: left;
	}
	.status-box .sym {
		margin: 18px 12px;
		padding: 5px 8px;
		background: #ccffcc;
		color: #000;
		text-decoration: none;
		font-weight: bold;
		float: left;
	}
	.status-box .sym:hover {
		background: #CFEA93;
	}
	.status-box ul {
		margin-left: 20px;
	}
	.status-box li {
		margin: 5px;
		padding: 2px 8px;
		border-left: 10px solid #CFEA93;
	}
	.status-box .sync-tips {
		border-left: 10px solid #000;
	}
	.sync-form {
		margin: 0;
	}
	.sync-form .sync-url {
		width: 513px;
	}
</style>
<div class="status-box">
	<div>
		<p class="title"><?php echo __('Current Github Repository'); ?></p>
		<p class="github">
			<p class="repo">
				<a href="https://github.com/<?php echo $github['repo'].'/tree/'.$github['branch'].'/'.$github['uri']; ?>" target="_blank">
					<?php echo $github['repo'].' ~ '.$github['uri'].' @ '.$github['branch']; ?>
				</a>
			</p>
			<p class="link">
				<a class="sym" href="<?php echo admin_url('plugins.php?page='.$this->mgr_link.'&op=config'); ?>"><?php echo __('[Config]'); ?></a></pre>
				<a class="sym" href="<?php echo admin_url('plugins.php?page='.$this->mgr_link.'&op=sync'); ?>"><?php echo __('[Sync]'); ?></a>
				<a class="sym" href="http://moyo.uuland.org/github-msync/" target="_blank"><?php echo __('[Help]'); ?></a>
			</p>
		</p>
	</div>
	<div>
		<p class="title"><?php echo __('Sync from url'); ?></p>
		<p>
			<form class="sync-form" action="<?php echo admin_url('plugins.php?page='.$this->mgr_link.'&op=syncurl'); ?>" method="post">
				<input class="sync-url" type="text" name="url" placeholder="https://raw.github.com/moolex/moyo.blogs/master/archives/github-msync.md" />
				<input class="sync-submit" type="submit" value="Sync" />
			</form>
		</p>
	</div>
	<div>
		<p class="title"><?php echo __('Last 7days log'); ?></p>
		<ul>
			<li class="sync-tips">
				Last Sync : <?php echo $lastsince; ?> / <a href="<?php echo admin_url('plugins.php?page='.$this->mgr_link.'&cache=no'); ?>"><?php echo __('Refresh'); ?></a>
			</li>
			<?php foreach ($last7logs as $log) { ?>
			<li>
				<?php echo $log['date']; ?> / <?php echo $log['message']; ?>
			</li>
			<?php } ?>
		</ul>
	</div>
	<div>
		<p class="title"><?php echo __('Sync Callback URL'); ?></p>
		<p>
			<?php echo plugins_url($this->guid.'/callback.php'); ?>
		</p>
	</div>
</div>
<?php
	}
	/**
	 * 配置保存
	 */
	public function admin_config_save()
	{
		$fields = array('repo', 'branch', 'uri');
		foreach ($fields as $field)
		{
			if (isset($_POST[$field]))
			{
				update_option('mgi-'.$field, $_POST[$field]);
			}
		}
		update_option('mgi-timestamp-saved', time());
	}
	/**
	 * 配置页面
	 */
	public function admin_config()
	{
		$repo = get_option('mgi-repo', 'moolex/moyo.blogs');
		$branch = get_option('mgi-branch', 'master');
		$uri = get_option('mgi-uri', 'archives');
?>
<style type="text/css">
	.mgi-form {
		margin-top: 20px;
	}
	.mgi-form span {
		float: left;
		width: 260px;
		text-align: right;
		padding-top: 5px;
		padding-right: 10px;
	}
	.mgi-form p {
		display: block;
		clear: both;
	}
</style>
<div class="mgi-form">
	<form action="<?php echo admin_url('plugins.php?page='.$this->mgr_link); ?>" method="post">
		<p>
			<label>
				<span><h3><?php echo __('Github Repository Config'); ?></h3></span>
			</label>
		</p>
		<p>
			<label>
				<span><?php echo __('Repository (include username)'); ?></span>
				<input type="text" name="repo" value="<?php echo $repo; ?>" />
			</label>
		</p>
		<p>
			<label>
				<span><?php echo __('Repo Branch'); ?></span>
				<input type="text" name="branch" value="<?php echo $branch; ?>" />
			</label>
		</p>
		<p>
			<label>
				<span><?php echo __('Archives Uri'); ?></span>
				<input type="text" name="uri" value="<?php echo $uri; ?>" />
			</label>
		</p>
		<p>
			<label>
				<span></span>
				<input type="submit" name="submit" value="<?php echo __('Save'); ?>" />
			</label>
		</p>
	</form>
</div>
<?php
	}
	/**
	 * 博客同步
	 */
	public function admin_sync()
	{
		if ($this->sync_task_running())
		{
			
		}
		else
		{
			$this->sync_task_init();
		}
?>
<style type="text/css">
	.status-box {
		margin: 20px 10px;
	}
	.status-box .title {
		padding: 10px;
		font-size: 20px;
		font-weight: bold;
	}
	.running-box {
		margin: 10px 20px;
	}
	#running-status {
		border: 1px solid #ccc;
		padding: 10px;
	}
</style>
<div class="status-box">
	<p class="title"><?php echo __('Syncing Github Repository'); ?></p>
</div>
<div class="running-box">
	<div id="running-status">loading...</div>
</div>
<script type="text/javascript">
	jQuery(document).ready(mgi_task_run);
	function mgi_task_run() {
		jQuery.get('<?php echo admin_url('admin-ajax.php?action=mgi-processing'); ?>', function(data) {
			if (data != 'OVER') {
				jQuery('#running-status').html(data);
				mgi_task_run();
			} else {
				jQuery('#running-status').html('FINISHED');
			}
		});
	}
</script>
<?php
	}
	/**
	 * Sync from url
	 */
	public function admin_syncurl()
	{
		$url = $_POST['url'];
		$id = $url ? $this->sync_archive('added', 'url;'.$url) : 0;
		$msg = $id ? __('Sync Success') : __('Sync Error');
		$this->message($msg, 'op=status');
	}
	/**
	 * Ajax 异步同步
	 */
	public function ajax_processing()
	{
		$rps = '?';
		$task = $this->sync_task_get();
		if ($task)
		{
			$status = $this->sync_task_status();
			$result = $this->sync_archive($task['cmd'], $task['uri']);
			echo $task['cmd'];
			echo ' -- ';
			echo array_pop(explode('/', $task['uri']));
			echo ' -- ';
			if ($result)
			{
				$rps = 'DONE';
			}
			else
			{
				$rps = 'FAILED';
			}
			echo 'TASK ' . $status['all'] . ' LEFT';
			echo ' -- ';
		}
		else
		{
			$rps = 'OVER';
		}
		exit($rps);
	}
	/**
	 * Github Service Callback
	 */
	public function service_callback()
	{
		if ($this->sync_task_running())
		{
			$this->sync_task_run_all();
		}
		$this->sync_task_init();
		$this->sync_task_run_all();
		exit('OK');
	}
	/*
	 * 执行队列中的所有任务
	 */
	private function sync_task_run_all()
	{
		while (false != $task = $this->sync_task_get())
		{
			$this->sync_archive($task['cmd'], $task['uri']);
		}
	}
	/**
	 * 检查是否有任务在运行
	 */
	private function sync_task_running()
	{
		return $this->cache_get('task') ? true : false;
	}
	/**
	 * 获取一个同步任务
	 */
	private function sync_task_get()
	{
		$files = $this->cache_get('task');
		$task = array_shift($files);
		$this->cache_set('task', $files, 3600);
		return $task ? $task : false;
	}
	/**
	 * 同步队列状态
	 */
	private function sync_task_status()
	{
		$files = $this->cache_get('task');
		return array(
			'all' => count($files)
		);
	}
	/**
	 * 初始化同步任务
	 */
	private function sync_task_init()
	{
		$last_since = null;
		$commits = $this->git_commit_news();
		$files = array();
		if ($commits)
		{
			foreach ($commits as $commit)
			{
				is_null($last_since) && $last_since = $commit['date'];
				$files = array_merge($files, $this->git_commit_files($commit['sha']));
			}
		}
		if ($files)
		{
			// resort & filter
			$github = $this->git_api_params();
			$fss = array();
			foreach ($files as $i => $file)
			{
				$uri = $file['uri'];
				$uri = str_replace('https://github.com', 'https://raw.github.com', $uri);
				$uri = preg_replace('/raw\/[a-z0-9]+/i', $github['branch'], $uri);
				if (isset($fss[$uri]))
				{
					unset($files[$fss[$uri]]);
					$files[$i] = array_merge($file, array('uri' => $uri));
				}
				$fss[$uri] = $i;
			}
			// write task
			$this->cache_set('task', $files, 3600);
		}
		if ($last_since)
		{
			// modify last since
			$last_since_ts = strtotime(str_replace(array('T', 'Z'), '', $last_since));
			$last_since_ts += 1;
			$last_since = date('Y-m-d', $last_since_ts).'T'.date('H:i:s', $last_since_ts).'Z';
			// over
			$this->status_last_since($last_since);
		}
	}
	/**
	 * 读写上次同步日期
	 * @param type $date
	 */
	private function status_last_since($date = null)
	{
		if (is_null($date))
		{
			// get
			return get_option('mgi-last-since', '1970-01-01T00:00:00Z');
		}
		else
		{
			update_option('mgi-last-since', $date);
		}
	}
	/**
	 * 文章同步
	 * @param type $cmd
	 * @param type $uri
	 */
	public function sync_archive($cmd, $uri)
	{
		$acmap = array(
			'added' => 'create',
			'modified' => 'modify',
			'removed' => 'delete'
		);
		if (isset($acmap[$cmd]))
		{
			$call = 'archive_'.$acmap[$cmd];
			if (method_exists($this, $call))
			{
				return $this->$call($uri);
			}
		}
	}
	/**
	 * 创建文章
	 * @param type $uri
	 */
	private function archive_create($uri)
	{
		$archive = $this->archive_load($uri);
		if ($archive)
		{
			$id = $this->wordpress_slug2id($archive['uri']['slug']);
			if ($id)
			{
				$this->archive_modify($id, $archive);
			}
			else
			{
				$id = $this->archive_change('wp_insert_post', $archive);
			}
		}
		return $id;
	}
	/**
	 * 修改文章
	 * @param type $uri
	 * @param type $ext
	 */
	private function archive_modify($uri, $ext = array())
	{
		if (is_numeric($uri))
		{
			$archive = $ext;
			$id = $uri;
		}
		else
		{
			$archive = $this->archive_load($uri);
			$id = $this->wordpress_slug2id($archive['uri']['slug']);
		}
		if ($id)
		{
			$this->archive_change('wp_update_post', $archive, array('ID' => $id));
		}
		return $id;
	}
	/**
	 * 文章创建/编辑
	 * @param type $func
	 * @param type $ext
	 */
	private function archive_change($func, $archive, $ext = array())
	{
		// get current user-id
		$userID = wp_get_current_user()->ID;
		if ($userID < 1)
		{
			$userID = current(get_users())->ID;
		}
		// data mixed
		$data = array_merge(array(
			'post_name' => $archive['uri']['slug'],
			'post_title' => $archive['title'],
			'post_content' => $this->md2html($archive['content']),
			'post_date' => $archive['meta']['time'],
			'post_status' => 'publish',
			'post_author' => $userID
		), $ext);
		$id = $func($data);
		if ($id)
		{
			// TOPIC
			$topic = $archive['meta']['topic'];
			$topic_id = get_cat_ID($topic);
			if ($topic_id < 1)
			{
				$topic_id = wp_create_category($topic);
			}
			if ($topic_id)
			{
				wp_set_post_categories($id, array($topic_id));
			}
			// TAGS
			$tags = $archive['meta']['tags'];
			if ($tags)
			{
				$tags_mix = explode(',', $tags);
				if ($tags_mix)
				{
					wp_set_post_tags($id, $tags_mix);
				}
			}
		}
		return $id;
	}
	/**
	 * 删除文章
	 * @param type $uri
	 */
	private function archive_delete($uri)
	{
		return 'ERR';
	}
	/**
	 * PERMANENT LINK TO POST-ID
	 */
	private function wordpress_slug2id($slug)
	{
		global $wpdb;
		$pg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_name = '%s' LIMIT 1", $slug ) );
		if ($pg)
		{
			return $pg->ID;
		}
		else
		{
			return false;
		}
	}
	/**
	 * 获取一篇文章（REMOTE）
	 * @param type $uri
	 */
	public function archive_load($uri)
	{
		$markdown = array();
		$stream = $this->media_read($uri);
		$lines = explode("\n", $stream);
		$loops = true;
		$storage = null;
		do {
			$line = array_shift($lines);
			if (substr($line, 0, 1) == '#')
			{
				$key = strtolower(trim(substr($line, 1)));
				$storage = &$markdown[$key];
				if ($key == 'content')
				{
					$loops = false;
				}
			}
			elseif (substr($line, 0, 1) == '*')
			{
				list($k, $v) = explode('=', trim(substr($line, 1)));
				$storage[trim($k)] = trim($v);
			}
			elseif (trim($line))
			{
				$storage = $line;
			}
		} while ($loops && is_string($line));
		$storage = implode("\n", $lines);
		// ok
		return $markdown;
	}
	/**
	 * 获取文章的原始内容
	 * @param type $uri
	 */
	private function media_read($uri)
	{
		$us = explode(';', $uri);
		$from = array_shift($us);
		$position = implode(';', $us);
		if ($from == 'url')
		{
			$http = $this->http_get($position);
			if (is_array($http) && isset($http['response']) && $http['response']['code'] == 200)
			{
				return $http['body'];
			}
			else
			{
				return '';
			}
		}
		else
		{
			return '';
		}
	}
	/**
	 * 获取上次同步之后的更新
	 */
	private function git_commit_news()
	{
		return $this->git_commit_since($this->status_last_since());
	}
	/**
	 * 获取文件变更记录
	 * @param type $sha
	 */
	private function git_commit_files($sha)
	{
		$files = array();
		$commit = $this->git_api_commit_info($sha);
		foreach ($commit['files'] as $i => $file)
		{
			$files[] = array(
				'cmd' => $file['status'],
				'uri' => 'url;'.$file['raw_url']
			);
		}
		return $files;
	}
	/**
	 * 获取最近7天的提交记录
	 */
	private function git_last_7logs()
	{
		$cache = (isset($_GET['cache']) && $_GET['cache'] == 'no') ? false : $this->cache_get('7dlogs');
		if ($cache)
		{
			$logs = $cache;
		}
		else
		{
			$logs = $this->cache_set('7dlogs', $this->git_commit_since(date('c', time() - 86400 * 7)), 3600);
		}
		return $logs;
	}
	/**
	 * 获取指定日期之后的提交记录
	 * @param type $since
	 */
	private function git_commit_since($since)
	{
		$logs = array();
		$commits = $this->git_api_commits(array('since' => $since));
		foreach ($commits as $commit)
		{
			$logs[] = array(
				'sha' => $commit['sha'],
				'message' => $commit['commit']['message'],
				'date' => $commit['commit']['author']['date']
			);
		}
		return $logs;
	}
	/**
	 * API - get commits data
	 * @param type $params
	 */
	private function git_api_commits($params)
	{
		return $this->git_api_url('/repos/:repo:/commits?sha=:branch:&path=:uri:&since=:since:', $this->git_api_params($params));
	}
	/**
	 * API - get commit information
	 * @param type $params
	 */
	private function git_api_commit_info($sha)
	{
		return $this->git_api_url('/repos/:repo:/commits/:sha:', $this->git_api_params(array('sha' => $sha)));
	}
	/**
	 * API - params mixed
	 * @param type $params
	 */
	private function git_api_params($params = array())
	{
		static $base = array();
		if (count($base) == 0)
		{
			$basefs = array('repo', 'branch', 'uri');
			foreach ($basefs as $basek)
			{
				$base[$basek] = get_option('mgi-'.$basek);
			}
		}
		return array_merge($base, $params);
	}
	/**
	 * API - requtest
	 * @param type $url
	 * @param type $data
	 */
	private function git_api_url($url, $data)
	{
		foreach ($data as $k => $v)
		{
			$url = str_replace(':'.$k.':', $v, $url);
		}
		$url = $this->github_api_base.$url;
		$http = $this->http_get($url);
		if (is_array($http) && isset($http['response']) && $http['response']['code'] == 200)
		{
			$body = $http['body'];
			$data = json_decode($body, true);
			if (is_array($data))
			{
				return $data;
			}
			else
			{
				$this->halt('JSON ERROR', $data);
			}
		}
		else
		{
			$this->halt('HTTP ERROR', $http);
		}
	}
	/**
	 * HTTP GET
	 * @param type $url
	 */
	private function http_get($url)
	{
		$func = $this->hgfunc;
		$result = $func($url);
		if ($func != 'wp_remote_get')
		{
			$result = array(
				'response' => array('code' => 200),
				'body' => $result
			);
		}
		else
		{
			$result = array(
				'response' => array('code' => 500)
			);
		}
		return $result;
	}
	/**
	 * 读取缓存
	 * @param type $k
	 */
	private function cache_get($k)
	{
		$c = get_option('mgi-c-'.$k);
		if ($c)
		{
			$d = json_decode($c, true);
			if ($d['i'] > time())
			{
				return $d['v'];
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}
	/**
	 * 写入缓存
	 * @param type $k
	 * @param type $v
	 * @param type $i
	 */
	private function cache_set($k, $v, $i)
	{
		$d = array(
			'v' => $v,
			'i' => time() + $i
		);
		update_option('mgi-c-'.$k, json_encode($d));
		return $v;
	}
	/**
	 * Transfer MarkDown to HTML
	 * @param type $stream
	 */
	private function md2html($stream)
	{
		$api = $this->mdengine();
		if ($api)
		{
			return $api->transfer($stream);
		}
		else
		{
			return $stream;
		}
	}
	/**
	 * MD Engine
	 */
	private function mdengine()
	{
		static $class_loaded = false;
		if (!$class_loaded)
		{
			require plugin_dir_path(__FILE__).'md-engine/api.php';
		}
		return mdEngineAPI::instance();
	}
	/**
	 * END - HALT
	 * @param type $msg
	 */
	private function halt($msg, $expand = array())
	{
		echo $msg;
		echo '<hr/>';
		echo '<pre>';
		echo print_r($expand);
		echo '</pre>';
		exit;
	}
	/**
	 * Message Display
	 * @param type $msg
	 * @param type $url
	 */
	public function message($msg, $url = null)
	{
		if ($url)
		{
			$url = admin_url('plugins.php?page='.$this->mgr_link.'&'.$url);
		}
?>
<style type="text/css">
	.message-box {
		margin: 20px 10px;
	}
	.message-box .title {
		padding: 10px;
		border-bottom: 2px solid #ccc;
		font-size: 20px;
		font-weight: bold;
		clear: both;
	}
	.message {
		padding: 20px;
	}
</style>
<div class="message-box">
	<div>
		<p class="title"><?php echo __('Message'); ?></p>
		<p class="message">
			<?php echo $msg; ?>
		</p>
	</div>
</div>
<?php if ($url) { ?>
<div class="message-box">
	.....<br/>
	<?php echo $url; ?>
</div>
<script type="text/javascript">
	setTimeout(function() { window.location = "<?php echo $url; ?>"; } , 3000);
</script>
<?php }
	}
}

?>