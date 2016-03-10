<?php

namespace automattic\vip\hash\console;

use automattic\vip\hash\DataModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ScanCommand
 * @package automattic\vip\hash\console
 */
class StatusCommand extends Command {

	private $allowed_file_types;

	protected function configure() {
		$this->allowed_file_types = array(
			'php',
			'php5',
			'js',
			'html',
			'htm',
			'twig'
		);
		$this->setName( 'status' )
			->setDescription( 'take a folder and generates a status report of good bad and unknown file hashes' )
			->addArgument(
				'folder',
				InputArgument::OPTIONAL,
				'A file hash to find, or a file to be hashed. Assumes hash if the given value is not a locatable file'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$folder = $input->getArgument( 'folder' );
		if ( empty( $folder ) ) {
			$folder = '.';
		}
		$data = $this->ProcessFile( $folder );
		$tree = $this->displayTree( $data );
		$good = 0;
		$bad = 0;
		$unknown = 0;
		foreach ($tree as $key => $line ) {
			if (strpos($line, 'false') !== FALSE ) {
				$output->writeln( '<error>'.$line.'</error>' );
				$bad++;
			} else if (strpos($line, 'true') !== FALSE ) {
				$output->writeln( '<info>'.$line.'</info>' );
				$good++;
			} else if (strpos($line, 'unknown') !== FALSE ) {
				$output->writeln( '<comment>'.$line.'</comment>' );
				$unknown++;
			} else {
				$output->writeln( $line );
			}
		}
		$total = $good + $bad + $unknown;
		$percentage = ( ( $good + $bad )/ $total ) * 100;
		$final = "<info>".$good." good</info>, <error>".$bad." bad</error>, <comment>".$unknown." unknown</comment>, ".number_format( $percentage, 2 )."% seen";
		$output->writeln( $final );
	}

	private function displayTree( array $node, $depth=-1, $last=false ) {
		$lines = [];
		$status = '  ';
		$md = '';
		if ( $depth > 0 ) {
			$md .= '|   ';
		} else {
			if ( $last ) {
				$md .= '└───';
			} else {
				$md .= '├───';
			}
		}
		$branch = '├';
		if ( $last ) {
			$branch = '└';
		}
		if ( $depth -1 > 0 ) {
			$md .= str_repeat('|   ', $depth -1 ).$branch.'───';
		}
		if ( !empty( $node['folder'] ) ) {
			$folderlines = array();
			if ( !empty( $node['contents'] ) ) {
				$i = 1;
				foreach ( $node['contents'] as $subnode ) {
					$newlines  = $this->displayTree( $subnode, $depth + 1, $i++ == count( $node['contents'] ) );
					$folderlines = array_merge( $folderlines, $newlines );
				}
			}
			if ( !empty( $folderlines ) ) {
				$lines[] = $status.$md . '├ '.$node['folder'];
				$lines = array_merge( $lines, $folderlines );
			}
		} else if ( !empty( $node['file'] ) ) {
			$statuses = [];
			$status = '? ';
			$status_set = false;
			if ( !empty( $node['hashes'] ) ) {
				foreach ( $node['hashes'] as $hash ) {
					$statuses[] = $hash['status'];
					if ( !$status_set ) {
						if ( $hash['status'] == 'true' ) {
							$status = '✓ ';
						} else if ( $hash['status'] == 'false' ) {
							$status = 'x ';
						}
						$status_set = true;
					} else {
						if ( $status == '✓ ' ) {
							if ( $hash['status'] == 'false' ) {
								$status = '~ ';
							}
						}
					}
				}
			} else {
				$statuses[] = 'unknown';
			}
			if ( !empty( $statuses ) ) {
				$lines[] = $status.$md . ''. basename( $node['file'] ) .' - '.implode(', ', $this->count_statuses( $statuses ) );
			}
		} else {
			$lines[] = '? unknown entry in data structure'.PHP_EOL;
		}
		return $lines;
	}

	public function count_statuses( array $statuses ) {
		$result = array();
		$counts = array_count_values( $statuses );
		foreach ( $counts as $key => $count ) {
			$result[] = $count.'x '.$key;
		}
		return $result;
	}

	/**
	 * @param $file
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function ProcessFile( $file ) {
		// don't process the vendor folder
		if ( substr( $file, -6) === 'vendor' ){
			return null;
		}

		// don't process the .git folder
		if ( substr( $file, -4) === '.git' ){
			return null;
		}

		// don't process the .svn folder
		if ( substr( $file, -4) === '.svn' ){
			return null;
		}
		// don't process the .svn folder
		if ( substr( $file, -5) === '.idea' ){
			return null;
		}

		$data = array();
		if ( is_dir( $file ) ) {
			$unfiltered_folders = scandir( $file );
			$folders = array_diff( $unfiltered_folders, array( '..', '.' ) );
			if ( empty( $folders ) ) {
				return null;
			}
			$contents = array();
			foreach ( $folders as $found_file ) {
				$result =  $this->ProcessFile( $file . DIRECTORY_SEPARATOR . $found_file );
				if ( !empty( $result ) && ( $result != null ) ) {
					if ( is_dir( $file . DIRECTORY_SEPARATOR . $found_file ) ) {
						$contents[] = $result;
					} else {
						$f = array(
							'file' => $file . DIRECTORY_SEPARATOR . $found_file,
							'hashes' => $result
						);
						$contents[] = $f;
					}
				}
			}
			$data = array(
				'folder'   => $file,
				'contents' => $contents
			);

		} else {
			// only process the file types we're interested in
			$info = pathinfo( $file );
			if ( isset( $info['extension'] ) ) {
				if ( !in_array( $info['extension'], $this->allowed_file_types ) ) {
					return null;
				}
			}
			$data_model = new DataModel();
			try {
				$hash = $data_model->hashFile( $file );
			} catch ( \Exception $e ) {
				$data = array(
					array(
						'hash' => 'empty',
						'status' => 'unknown',
						'file' => $file
					)
				);
				return $data;
			}
			try {
				$data = $data_model->getHashStatusAllUsers( $hash );
			} catch ( \Exception $e ) {
				//
			}
			if ( empty( $data ) ) {
				$data = array(
					array(
						'hash' => $hash,
						'status' => 'unknown',
						'file' => $file
					)
				);
			}

		}
		return $data;
	}
}