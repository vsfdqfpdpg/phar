<?php

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ZipPhpArchive {
	const NAME_KEY = 'name';
	const STUB_KEY = 'stub';
	const PHAR_JSON_FILE = 'phar.json';
	protected $bin;
	protected $stub;
	protected $name;
	protected $output;

	public function __construct( OutputInterface $output ) {
		$this->output = $output;
	}

	private function init() {
		$this->checkPharJsonFileIfExist();
		$this->checkPharFileIfExist();
		$this->createPharFile();
		if ( $this->isWin() ) {
			$this->createBatFile();
			$this->createBashFile();
		}
		$this->moveFileToBin();

	}

	private function getComposerPath() {
		if ( $this->isWin() ) {
			return $this->filterComposerPath( ';' );
		} else {
			return $this->filterComposerPath( ':' );
		}
	}

	private function checkPharJsonFileIfExist() {
		$working_path = getcwd() . DIRECTORY_SEPARATOR;
		$phar_json    = $working_path . self::PHAR_JSON_FILE;
		if ( ! file_exists( $phar_json ) || ! is_file( $phar_json ) ) {
			$this->output->writeln( '<comment>phar.json is not exist. Do you want to create phar.json (yes|no)</comment>' );
			$var = trim( fgets( STDIN ) );
			if ( stripos( $var, 'no' ) !== false ) {
				exit( 0 );
			}

			$project = basename( $working_path );
			$stub    = "index.php";
			$data    = "{\n  \"" . self::NAME_KEY . "\": \"{$project}\",\n  \"" . self::STUB_KEY . "\": \"{$stub}\"\n}";
			file_put_contents( $phar_json, $data );
		} else {
			$json    = json_decode( file_get_contents( $phar_json ), true );
			$project = $json[ self::NAME_KEY ];
			$stub    = $json[ self::STUB_KEY ];
		}

		$table = new Table( $this->output );
		$table->setHeaders( [ self::NAME_KEY, self::STUB_KEY ] );
		$table->addRow( [ $project, $stub ] );
		$table->render();

		list( $this->name, $this->stub ) = [ $project, $stub ];

	}

	private function checkPharFileIfExist() {
		$bash_file = $this->bin . $this->name;
		if ( $this->isWin() ) {
			$phar_file = $this->bin . $this->name . '.phar';
			$bat_file  = $this->bin . $this->name . '.bat';

			$this->unlink( $phar_file );
			$this->unlink( $bat_file );
		}
		$this->unlink( $bash_file );
	}

	private function createPharFile() {
		$this->output->writeln( '<info>Begin to create ' . $this->name . '.phar file.</info>' );
		$temp_phar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->name . '.phar';
		$phar      = new Phar( $temp_phar );
		$phar->startBuffering();
		$default_stub = $phar->createDefaultStub( $this->stub );
		$phar->buildFromDirectory( getcwd() . DIRECTORY_SEPARATOR );
		$stub = "#!/usr/bin/env php \n" . $default_stub;
		$phar->setStub( $stub );
		$phar->stopBuffering();
		try {
			$phar->compressFiles( Phar::GZ );
		} catch ( Exception $exception ) {
			if ( ! file_exists( $temp_phar ) ) {
				$this->output->writeln( '<error>' . $exception->getMessage() . ' ' . $temp_phar . '</error>' );
				exit( 1 );
			}
		}
	}

	private function createBatFile() {
		$str = <<<STR
@ECHO off \r
:: in case DelayedExpansion is on and a path contains ! \r
setlocal DISABLEDELAYEDEXPANSION \r
php "%~dp0$this->name.phar" %* 
STR;
		file_put_contents( $this->bin . $this->name . '.bat', $str );
	}

	private function createBashFile() {
		$str = <<<STR
#!/bin/sh

dir=$(cd "\${0%/*}" && pwd)

if [[ \$dir == /cygdrive/* && $(which php) == /cygdrive/* ]]; then    
    # cygwin paths for windows PHP must be translated
    dir=$(cygpath -m "\$dir");    
fi

php "\${dir}/$this->name.phar" "$@"
STR;
		file_put_contents( $this->bin . $this->name, $str );
	}

	/**
	 * @param string $filename
	 */
	private function unlink( $filename ) {
		if ( file_exists( $filename ) && is_file( $filename ) ) {
			if ( ! @unlink( $filename ) ) {
				$this->output->writeln( '<error>Unable to delete ' . $filename . '. Please run as administrator</error>' );
				exit( 1 );
			}
		}
	}

	/**
	 * @return bool
	 */
	private function isWin() {
		return strcasecmp( substr( PHP_OS, 0, 3 ), 'WIN' ) == 0;
	}

	/**
	 *Create a php archive file
	 */
	public function start() {
		$this->bin = $this->getComposerPath() . DIRECTORY_SEPARATOR;
		$this->init();
		$this->output->writeln( '<info>' . $this->bin . $this->name . '.phar installed successfully.</info>' );
	}

	/**
	 * @return bool
	 */
	private function isLinux() {
		return ! ! stristr( PHP_OS, 'LINUX' );
	}

	private function moveFileToBin() {
		$dest = $this->isWin() ? $this->bin . $this->name . '.phar' : $this->bin . $this->name;
		if ( @rename( sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->name . '.phar', $dest ) ) {
			chmod( $this->bin . $this->name, 0755 );
		} else {
			$this->output->writeln( '<error>Unable to install ' . $this->bin . $this->name . '.phar. Please run as administrator</error>' );
			exit( 1 );
		}
	}

	/**
	 * @param $delimiter
	 *
	 * @return string
	 */
	private function filterComposerPath( $delimiter ) {
		$environment_path = explode( $delimiter, getenv( 'PATH' ) );
		$filter           = array_filter( $environment_path, function ( $path ) {
			$composer_filename = $path . DIRECTORY_SEPARATOR . 'composer';

			return file_exists( $composer_filename ) && is_file( $composer_filename );
		} );
		if ( count( $filter ) ) {
			$this->output->writeln( '<info>Composer found.</info>' );

			return array_shift( $filter );
		} else {
			$this->output->writeln( '<error>Do not found composer in environment path.</error>' );
			exit( 1 );
		}

	}

}
