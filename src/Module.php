<?php

namespace yii\xhprof;

use Yii;
use yii\base\Application;
use yii\base\ErrorException;
use yii\base\BootstrapInterface;
use yii\web\ForbiddenHttpException;
use yii\web\UrlRule;

/**
 * The Xhprof Module provides profiler tool based on XHProf
 *
 */
class Module extends \yii\base\Module implements BootstrapInterface {
	/**
	 * @var array the list of IPs that are allowed to access this module.
	 * Each array element represents a single IP filter which can be either an
	 * IP address or an address with wildcard (e.g. 192.168.0.*) to represent a
	 * network segment. The default value is `['127.0.0.1', '::1']`, which means
	 * the module can only be accessed by localhost.
	 */
	public $allowedIPs = ['127.0.0.1', '::1'];
	/**
	 * @var array the list of hosts that are allowed to access this module.
	 * Each array element is a hostname that will be resolved to an IP address
	 * that is compared with the IP address of the user. A use case is to use a
	 * dynamic DNS (DDNS) to allow access. The default value is `[]`.
	 */
	public $allowedHosts = [];
	/**
	 * {@inheritdoc}
	 */
	public $controllerNamespace = 'yii\xhprof\controllers';
	/**
	 * @var string the directory storing the xhprof data files. This can be
	 * specified using a path alias.
	 */
	public $dataPath = '@runtime/xhprof';
    /**
     * @var int the permission to be set for newly created xhprof data files.
	 * This value will be used by PHP [[chmod()]] function. No umask will be
	 * applied. If not set, the permission will be determined by the current
	 * environment.
	 */
	public $fileMode;
	/**
	 * @var int the permission to be set for newly created directories.
	 * This value will be used by PHP [[chmod()]] function. No umask will be
	 * applied. Defaults to 0775, meaning the directory is read-writable by
	 * owner and group, but read-only for other users.
	 */
	public $dirMode = 0775;
	/**
	 * @var bool whether to disable IP address restriction warning triggered by
	 * checkAccess function.
	 */
	public $disableIpRestrictionWarning = false;

	/**
	 * @var int the xhprof flags.
	 */
	public $flags = 0;

	/**
	 * @var int the divisor of the gc probability.
	 */
	public $gcProbability = 10000;
	/*
	 * @var array the xhprof data file gc policy for gc.
	 * For each key value, when gc is processing, it will try to unlink the
	 * oldest files ``value" ago to keep the total number of files not more
	 * than ``key". For example, the default value will remove the oldest files
	 * 1 day ago to keep the total number of the files not more than 1000.
	 * [500 => '1h', 1000 => '2d'] will try to unlink the oldest files 1 hour
	 * ago to keep the total number of files not more 500 and try to unlink the
	 * oldest files 2 days ago to keep the total number of files not more 1000.
	 * These words can be used for time unit:
	 * y: year(s) (a year is always considered as 12months (360 days))
	 * M: month(s) (a month is always considered as 30 days)
	 * w: week(s)
	 * d: day(s)
	 * h: hour(s)
	 * m: minute(s)
	 * s: second(s) (default unit)
	 */
	public $gcLimit = [1000 => '1d'];

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		parent::init();
		$this->dataPath = Yii::getAlias($this->dataPath);
	}

	/**
	 * {@inheritdoc}
	 */
	public function bootstrap($app) {
		if ($app instanceof \yii\web\Application) {
			$app->urlManager->addRules([
				[
					'class' => UrlRule::class,
					'pattern' => $this->id,
					'route' => "{$this->id}/default/index",
				],
				[
					'class' => UrlRule::class,
					'pattern' => "{$this->id}/<id:\w+>",
					'route' => "{$this->id}/default/view",
				],
				[
					'class' => UrlRule::class,
					'pattern' => "{$this->id}/<controller:[\w\-]+>/<action:[\w\-]+>",
					'route' => "{$this->id}/<controller>/<action>",
				],
			], false);
		}
		if (!function_exists('xhprof_enable')) {
			Yii::warning("Xhprof extension required for Xhprof module is not installed.", __METHOD__);
			return;
		}
		try {
			if (file_exists($this->dataPath)) {
				if (!is_dir($this->dataPath)) {
					Yii::warning("{$this->dataPath} exists but not a directory. The xhprof data can not be saved.", __METHOD__);
					return;
				}
			} else if (mkdir($this->dataPath, $this->dirMode, true)) {
				Yii::warning("{$this->dataPath} does not exist but can not be created. The xhprof data can not be saved.", __METHOD__);
				return;
			}
		} catch (ErrorException $e) {
			Yii::warning($e, __METHOD__);
			return;
		}
		xhprof_enable($this->flags);
		register_shutdown_function(function() {
			$data = xhprof_disable();
			if (empty($data)) {
				return;
			}
			$n = 0;
			do {
				if ($n++ > 10) {
					Yii::warning("Can not create file in directory {$this->dataPath} to save the data.", __METHOD__);
					return;
				}
				list($u, $now) = explode(' ', microtime());
				$fname = sprintf("%s/%016X.xhprof", $this->dataPath, $now*1000000 + (int)($u*1000000));
				$fp = fopen($fname, 'x');
			} while ($fp === false);
			$req = Yii::$app->request;
			$data = [
				'uri' => $req->isConsoleRequest ? implode(' ', array_map('escapeshellarg', $_SERVER['argv'])) : $req->absoluteUrl,
				'time' => date('Y-m-d H:i:s.', $_SERVER['REQUEST_TIME']) . sprintf('%03d', $_SERVER['REQUEST_TIME_FLOAT']*1000%1000),
				'xhprof' => $data,
			];
			fwrite($fp, serialize($data));
			fclose($fp);
			if (!empty($this->fileMode)) {
				chmod($fname, $this->fileMode);
			}
			if ($this->gcProbability > 0 && rand(0, $this->gcProbability) != 0) {
				return;
			}
			$this->gc();
		});
	}

	public function gc() {
		Yii::info("Start GC", __METHOD__);
		$dir = dir($this->dataPath);
		if (empty($dir)) {
			Yii::error("Can not open directory {$this->dataPath}.", __METHOD__);
			return false;
		}
		$entries = [];
		while (($entry = $dir->read()) !== false) {
			if ($entry != '.' && $entry != '..') {
				$entries[] = $entry;
			}
		}
		rsort($entries);
		ksort($this->gcLimit);
		$total = count($entries);
		Yii::debug("total: $total.", __METHOD__);
		$i = $total - 1;
		foreach ($this->gcLimit as $num => $time) {
			if ($i <= $num) {
				break;
			}
			if (preg_match('/^([0-9]+)([yMwdhms]?)$/', $time, $matches) == 0) {
				Yii::error("The time string $time is invalid.", __METHOD__);
				continue;
			}
			$unit = [
				'y' => 360*86400,
				'M' => 30*86400,
				'w' => 7*86400,
				'd' => 86400,
				'h' => 3600,
				'm' => 60,
				's' => 1,
				'' => 1,
			];
			$threshold = sprintf("%016X", ($now - $matches[1]*$unit[$matches[2]])*1000000);
			while ($i > $num && strcmp($entries[$i], $threshold) < 0) {
				unlink($this->dataPath . "/" . $entries[$i--]);
			}
		}
		$removed = $total - $i - 1;
		Yii::info("$removed xhprof files removed.", __METHOD__);
		array_splice($entries, $i + 1);
		return $entries;
	}

	/**
	 * {@inheritdoc}
	 * @throws ForbiddenHttpException
	 */
	public function beforeAction($action) {
		xhprof_disable();
		if (!parent::beforeAction($action)) {
			return false;
		}
		if ($this->checkAccess($action)) {
			return true;
		}
		throw ForbiddenHttpException('You are not allowed to access this page.');
	}

	/**
	 * Checks if current user is allowed to access the module
	 * @param \yii\base\Action $action the action to be executed.
	 * @return bool if access is granted
	 */
	protected function checkAccess($action) {
		$allowed = false;
		$ip = Yii::$app->getRequest()->getUserIP();
		foreach ($this->allowedIPs as $filter) {
			if ($filter === '*' || $filter === $ip || (($pos = strpos($filter, '*')) !== false && !strncmp($ip, $filter, $pos))) {
				$allowed = true;
				break;
			}
		}
		if ($allowed === false) {
			foreach ($this->allowedHosts as $hostname) {
				$filter = gethostbyname($hostname);
				if ($filter === $ip) {
					$allowed = true;
					break;
				}
			}
		}
		if ($allowed === false) {
			if (!$this->disableIpRestrictionWarning) {
				Yii::warning('Access to xhprof is denied due to IP address restriction. The requesting IP address is ' . $ip, __METHOD__);
			}
			return false;
		}
		return true;
	}
}
/**
 * vim: ts=4 sw=4
 */
