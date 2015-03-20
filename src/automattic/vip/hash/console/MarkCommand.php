<?php

namespace automattic\vip\hash\console;

use automattic\vip\hash\DataModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MarkCommand extends Command {
	protected function configure() {
		$this->setName( 'mark' )
			->setDescription( 'take a file and mark it as <info>good</info> or <error>bad</error>' )
			->addArgument(
				'file',
				InputArgument::REQUIRED,
				'The file to be hashed'
			)->addArgument(
				'username',
				InputArgument::REQUIRED,
				'A wordpress.com username'
			)->addArgument(
				'status',
				InputArgument::REQUIRED,
				'"good" or "bad"'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$file = $input->getArgument( 'file' );
		$username = $input->getArgument( 'username' );
		$status = $input->getArgument( 'status' );
		$data = new DataModel();
		$hash = $data->hashFile( $file );
		$data->markHash( $hash, $username, $status );
		//$output->writeln( $hash );
	}
}