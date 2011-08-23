<?php
namespace rmtools;
include __DIR__ . '/MakeLogParserVC.php';

class BuildVC {
	public $branch;
	public $build_name;
	public $env;
	private $obj_dir;
	private $build_dir;
	protected $old_cwd;
	public $logs = array();

	public $archive_path = false;
	public $debug_path = false;
	public $devel_path = false;
	
	public $compiler_log_parser;
	public $stats;

	function __construct(Branch $branch, $build_name)
	{
		$this->branch = $branch;
		$this->build_name = $build_name;
		$this->obj_dir = $this->branch->config->getBuildDir() . '/' . $this->build_name;
		$this->compiler = $this->branch->config->getCompiler();
		$this->architecture = $this->branch->config->getArchitecture();

		$vc_env_prefix = strtoupper($this->compiler);
		if ($this->architecture == 'x64') {
			$vc_env_prefix .= '_X64_';
		} else {
			$vc_env_prefix .= '_';
		}

		$env = array();
		$env['PATH'] = getenv($vc_env_prefix . 'PATH') . ';' . getenv('PATH') ;
		$env['INCLUDE'] = getenv($vc_env_prefix . 'INCLUDE');
		$env['LIB'] = getenv($vc_env_prefix . 'LIB');

		/* We don't support anymore VC6, so calling these scripts from the
		   SDK are just fine */
		if (!$env['INCLUDE'] || !$env['LIB']) {
			$env['INCLUDE'] = getenv('INCLUDE');
			$env['LIB'] = getenv('LIB');
		}

		$env['TMP'] = $env['TEMP'] = getenv('TEMP');
		$env['SystemDrive'] = getenv('SystemDrive');
		$env['SystemRoot'] = getenv('SystemRoot');
		$env['BISON_SIMPLE'] = getenv('BISON_SIMPLE');
		$this->env = $env;
	}

	function setSourceDir($src_dir)
	{
		$this->build_dir = $src_dir;
	}

	private function addLogsToArchive()
	{
			$zip = new \ZipArchive();
			if ($zip->open($this->archive_path) === FALSE) {
				throw new \Exception('cannot open archive');
			}
			$zip->addFromString('logs\buildconf.txt', $this->log_buildconf);
			$zip->addFromString('logs\configure.txt', $this->log_configure);
			$zip->addFromString('logs\make.txt', $this->log_make);
			$zip->addFromString('logs\archive.txt', $this->log_archive);
			$zip->close();
	}

	function buildconf()
	{
		$cmd = 'buildconf';
		$ret = exec_single_log($cmd, $this->build_dir, $this->env);
		if (!$ret) {
			throw new \Exception('buildconf failed');
		}
		$this->log_buildconf = $ret['log'];
	}

	function configure($extra = false)
	{
		$args = $this->branch->config->getConfigureOptions($this->build_name) . ($extra ?: $extra);
		$cmd = 'configure ' . $args . ' --enable-object-out-dir=' . $this->obj_dir;
		/* old build may have been stoped */
		if (is_dir($this->obj_dir)) {
			rmdir_rf($this->obj_dir);
		}
		mkdir($this->obj_dir, 0655, true);
		$ret = exec_single_log($cmd, $this->build_dir, $this->env);
		if (!$ret) {
			throw new \Exception('Configure failed');
		}
		$this->log_configure = $ret['log'];
	}

	function make()
	{
		$cmd = 'nmake';
		$ret = exec_single_log($cmd, $this->build_dir, $this->env);
		if (!$ret) {
			throw new \Exception('Make failed');
		}
		$this->log_make = $ret['log'];
	}

	function makeArchive()
	{
		$cmd = 'nmake snap';
		$ret = exec_single_log($cmd, $this->build_dir, $this->env);
		if (!$ret) {
			throw new \Exception('Make snap failed');
		}

		$this->log_archive = $ret['log'];

		if (!preg_match('/Build dir: (.*)/', $this->log_configure, $matches)) {
			throw new \Exception('Make archive failed, cannot find build dir');
		}
		$zip_dir = trim($matches[1]);

		if (!preg_match('/.*(php-\d\.\d\.\d.*\.zip)/', $this->log_archive, $matches)) {
			throw new \Exception('Make archive failed, cannot find php archive');
		}
		$zip_filename = trim($matches[1]);

		if (!preg_match('/.*(php-devel-pack-\d\.\d\.\d.*\.zip)/', $this->log_archive, $matches)) {
			throw new \Exception('Make archive failed, cannot find php-devel archive');
		}
		$zip_devel_filename = trim($matches[1]);
		$this->zip_devel_filename = $zip_devel_filename;

		if (!preg_match('/.*(php-debug-pack-\d\.\d\.\d.*\.zip)/', $this->log_archive, $matches)) {
			throw new \Exception('Make archive failed, cannot find php-debug archive');
		}
		$zip_debug_filename = trim($matches[1]);
		$this->zip_debug_filename = $zip_debug_filename;

		$this->archive_path = realpath($zip_dir . '/' . $zip_filename);
		$this->debug_path = realpath($zip_dir . '/' . $zip_debug_filename);
		$this->devel_path = realpath($zip_dir . '/' . $zip_devel_filename);

		$this->addLogsToArchive();
	}

	function getMakeLogParsed()
	{
		$parser = new MakeLogParserVc;
		$tmpfile = $this->obj_dir . '/' . 'make.txt';
		file_put_contents($tmpfile, $this->log_make);
		$parser->parse($tmpfile, $this->build_dir);
		unlink($tmpfile);
		$this->stats = $parser->stats;
		$this->compiler_log_parser = $parser;
		return $parser->toHtml($this->build_name);
	}

	function getStats()
	{
		return $this->stats;
	}

	function clean()
	{
		rmdir_rf($this->obj_dir);
	}

	function getLogs()
	{

	}
}
