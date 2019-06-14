<?php


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PharCommand extends Command {
	public function configure() {
		$this->setName( "archive" )
		     ->setAliases( [ 'A', 'a' ] )
		     ->setDescription( "Create a php archive file." );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$zipPhar = new ZipPhpArchive( $output );
		$zipPhar->start();
	}
}