<?php

declare (ticks=1);

namespace phpload\core\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Console;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use phpload\core\models\{DownloadJob,DownloadItem,PremiumAccount};
use phpload\core\helpers\Dictionary;

/**
 * The supervisor observes a dlc-work-directory. If a new DLC-File is found,
 * a new DownloadJob will be created by the Supervisor.
 *
 * Supervisor is going to check, if is workload present. Workload means, uncompleted Downloadjobs.
 * If workload is present, n Worker will be started (depends on configuration). 
 * The Worker query DownloadItem::search() to check
 * for pending/paused DownloadItems. Paused Item take presedence over pending ones.
 *
 * SIGNALS:
 *
 * - SIGHUP: reload the Supervisor Configuration
 * - SIGTERM: sends a SIGTERM to all running Worker which will pause the current download) and exits Supervisor
 * 
 * Worker stores theire pid in Database as procid. After a Worker is done, it will set procid = null.
 * The Supervisor check the DB for Zombies on every reload/startup.
 * 
 *
 * 1. Start a Webserver (php -S 0.0.0.0:3000 -t web)
 * Socket Server: https://www.php.net/manual/de/function.socket-select.php
 *
 * On Startup, the Server will set all DL-Job with state === processing to state = paused (Zombi care)
 * A Workerprocess select from dl_jobs by sort state (resumed paused has priority)
 * Websockets are so hot right now.
 * resume a Dowload:
 *
 * - curl_setopt(CURLOPT_RESUME_FROM)
 * - 
 */
class PhploadController extends Controller
{
	private $webserverpid;

	private $lockFile;

	private $config;

	private $threads = [];

	public function actions()
	{
		return [
			'account:list' => [
				'class' => actions\AccountIndex::class
			],
			
			'account:create' => [
				'class' => actions\AccountCreate::class
			]

		];
	}

	public function warm()
	{

		$this->lockFile = sys_get_temp_dir() . '/phpload.lock';

		if (file_exists($this->lockFile)) {
			$pid = file_get_contents($this->lockFile);
			exit('phpLoad is allready running with pid ' . $pid);

			return;
		}

		file_put_contents($this->lockFile, posix_getpid());

		$sighandler = function (int $signo,$siginfo) {
			$this->shutdown();
			exit();
		};

		pcntl_signal(SIGTERM, $sighandler);
		pcntl_signal(SIGINT, $sighandler);
		pcntl_signal(SIGHUP, function () {
			echo "reload config\n";
			$this->config = parse_ini_file("phpload.conf", true);
		});

		// load config here
		if (!file_exists('phpload.conf')) {
			echo "phpload.conf is missing, generate a default one to: " . __DIR__ . "/phpload.conf\n";
			copy (Yii::getAlias('@common/config/default.conf'),'phpload.conf');
		}

		$this->config = parse_ini_file("phpload.conf", true);

		$this->startWebserver($this->config['webserver']['binding']);

		echo "Server started and listening to " . $this->config['webserver']['binding'] ."\n";
	}

	public function actionRun()
	{
		$this->warm();

		$finder = new Finder();

		while (true) {

			sleep (3);

			$this->refreshAuthCookies();
			$this->syncThreads();
			$this->startThreads();

			$pending_dlc = $finder->ignoreVCS(true)
				->files()
				->name('*' . $this->config['downloads']['dlc_extension'])
				->in($this->config['downloads']['dlc_path']);

			if (!$pending_dlc->hasResults()) {
				continue;
			}

			foreach ($pending_dlc as $dlc) {

				$this->stdout("Enqueue dlc " . $dlc->getBasename() ."\n", Console::BOLD); 

				$job = new DownloadJob([
					'destination' => $this->config['downloads']['proc'],
					'dlc' => file_get_contents($dlc->getPathname())
				]);

				if ($job->save()) {
					$this->stdout("New Downloadjob " . $job->getPrimaryKey() . "\n", Console::BOLD);
				} else {
					$this->stdout("Could not enqueu DLC (dlc deleted): " . print_r($job->getErrors(),true), Console::BOLD);
				}

				unlink ($dlc->getPathname());
			}

		}

	}

	/**
	 * Find DownloadItem state IN ('pending','paused')
	 * Sort by state (paused first)
	 */
	public function startThreads()
	{
		$max = (int) $this->config['downloads']['threads'];
		if (count($this->threads) >= $max) {
			return;
		}

		$workload = DownloadItem::find()->where([
			'state' => [Dictionary::STATE_PENDING,Dictionary::STATE_PAUSED]
		])->orderBy(['state' => SORT_ASC])->all();

		if (!$workload) {
			return;
		}

		if ($max > count($workload)) {
			$max = count($workload);
		}

		for ($i=0;$i<$max;$i++) {

			if (!isset ($workload[$i])) {
				continue;
			}

			$this->startThread($workload[$i]->getPrimaryKey());
		}

		$this->stdout($i ." Threads running.\n");

	}

	/**
	 * refresh the AuthCookies of all configured PremiumAccounts
	 * every 120 Minutes
	 */
	public function refreshAuthCookies()
	{
		$dt = new \DateTime();
		$format = Yii::$app->dateFormat;
		$refresh = PremiumAccount::find()->where([
			'<',
			'authCookiesValidTill',
			$dt->format($format)
		])->all();

		if ($refresh) {
			$this->stdout("Refreshing AuthCookies ...\n");
			foreach ($refresh as $acc) {
				// afterSave() refreshs the authCookie
				try {
					$acc->save();
				} catch (\yii\httpclient\Exception $e) {
					$this->stdout("Transport problem: " . $e->getMessage());
					break;
				}
			}
			$this->stdout("Done.\n");
		}
	}

	/**
	 * Check if all threaded pids exists.
	 * If not, remove them.
	 * Shutdown Threads, not allowed by config 
	 */
	public function syncThreads()
	{
		/** @var $dlItemId DownloadItem PK **/
		foreach ($this->threads as $pid => $dlItemId) {
			if (!posix_getpgid($pid)) {
				unset ($this->threads[$pid]);
				$this->stdout("Set state to pending\n");
				#$this->updateDlItemState($dlItemId,Dictionary::STATE_PENDING);
				$this->updateDlItemState($dlItemId,'FOO1');
			}
		}

		$max = (int) $this->config['downloads']['threads'];
		if (count($this->threads) > $max) {

  			$threads = $this->threads;
			$offset  = count($threads)-$max;

			foreach (array_slice($threads, -($offset)) as $pid) {
				$this->stopThread($pid);
			}
		}

		/**
		 * Sync DB: pid not in DB
		 * update Runners on DB with STATE_PROC and 
		 * no pid on OS present
		 */	
		$zombis = DownloadItem::find()
			->andWhere(['state' => Dictionary::STATE_PROC])
			->all()
		;

		foreach ($zombis as $item) {

			if (posix_getpgid($item->procid)) {
				continue;
			}

			$item->updateAttributes([
				'state' => Dictionary::STATE_PAUSED,
				'procid' => null
			]);
		}
	}

	/** 
	 * Change the state of a DownloadItem
	 *
	 * @param int $pk Primary Key of DL-Item
	 * @param string $state the new state
	 *
	 * @return bool
	 */
	private function updateDlItemState(int $pk, string $state): bool
	{
		$model = DownloadItem::findOne($pk);

		if (!$model) {
			return false;
		}

		$model->updateAttributes([
			'state' => $state
		]);

		return true;
	}

	public function startWebserver($binding)
	{
		$webroot = Yii::getAlias('@frontend/web');
		$this->webserverpid = exec ("php -S {$binding} -t {$webroot} > /dev/null 2>&1 & echo $!;", $output);
	}

	/**
	 * start a Downloadthread for a given DownloadItem PK
	 *
	 * @param int $downloadItemId Primary Key of DownloadItem
	 */
	public function startThread($downloadItemId)
	{
		$path = Yii::getAlias('@console/phpLoad');
		$bandwidth = 0;

		if (
			isset($this->config['downloads']['bandwith'])
			&& $this->config['downloads']['bandwith'] > 0
		) {
			$threads = count($this->threads)+1;
			$bandwidth = round(abs($this->config['downloads']['bandwith'])/$threads);
		}
		
		$pid = exec ("php {$path} worker {$downloadItemId} {$bandwidth} > /dev/null 2>&1 & echo $!;", $output);
		$this->threads[$pid] = $downloadItemId;
	}

	public function stopThread($pid)
	{
		$this->stdout("stopping thread {$pid}\n");
		posix_kill((int) $pid,SIGTERM);
	}

	private function stopWebserver()
	{
		if ($this->webserverpid) {
			posix_kill((int) $this->webserverpid,SIGTERM);
		}
	}

	private function releaseLock()
	{
		if (file_exists($this->lockFile)) {
			unlink ($this->lockFile);
		}
	}

	public function shutdownThreads()
	{
		if (!$this->threads) {
			return;
		}

		foreach (array_keys($this->threads) as $pid) {
			$this->stopThread($pid);
		}
	}

	public function __destruct()
	{
		$this->shutdown();
	}

	public function shutdown()
	{
		$this->stopWebserver();
		$this->shutdownThreads();
		$this->releaseLock();
	}

}