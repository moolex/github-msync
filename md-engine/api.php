<?php

/**
 * Markdown Render Engine Manager
 */

class mdEngineAPI
{
	/**
	 * Engine Instance API
	 * @staticvar null $api
	 * @return self
	 */
	public static function instance()
	{
		static $api = null;
		if (is_null($api))
		{
			$api = new self();
		}
		return $api;
	}
	/**
	 * Markdown Stream Transfer
	 * @param type $stream
	 */
	public function transfer($stream)
	{
		$pool = array('local', 'remote');
		foreach ($pool as $engine)
		{
			$func_support = 'support_'.$engine;
			$func_transfer = 'transfer_'.$engine;
			if ($this->$func_support())
			{
				return $this->$func_transfer($stream);
			}
		}
		return $stream;
	}
	/**
	 * If local engine enabled
	 */
	private function support_local()
	{
		return version_compare('5.3', PHP_VERSION) < 1;
	}
	/**
	 * Local Transfer
	 * @param type $stream
	 */
	private function transfer_local($stream)
	{
		static $class_loaded = false;
		if (!$class_loaded)
		{
			$ROOT = dirname(__FILE__).DIRECTORY_SEPARATOR;
			require $ROOT.'Michelf/Markdown.php';
			require $ROOT.'Michelf/MarkdownExtra.php';
		}
		return \Michelf\MarkdownExtra::defaultTransform($stream);
	}
	/**
	 * If remote engine enabled
	 */
	private function support_remote()
	{
		return false;
	}
	/**
	 * Remote Transfer
	 * @param type $stream
	 */
	private function transfer_remote($stream)
	{
		return $stream;
	}
}

?>