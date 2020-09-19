<?php

new SyntaxCheck;

class SyntaxCheck {

	private $jsErrors = 0;
	private $phpFileCount = 0;
	private $phpFileErrorCount = 0;

	private $rootPath;
	private $directorySeparator = '/';

	private $commit = false;

	public function __construct(){

		if(isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'commit'){
			$this->commit = true;
		}

		$this->rootPath = trim($this->rootPath);

		if(!$this->rootPath){
			$this->rootPath = exec('pwd');
		}

		if(substr($this->rootPath, -1) !== $this->directorySeparator){
			$this->rootPath = $this->rootPath . $this->directorySeparator;
		}

		$result = shell_exec('cd "'.$this->rootPath.'" && git status');

		preg_match_all('/On branch (.*)/', $result, $matches);
		$branchName = $matches[1][0];

		echo " * You are currently on branch: {$branchName}\n";
		$committing = true;
		if(preg_match('/nothing to commit, working directory clean/s', $result)){
			$committing = false;
			echo " * There are no GIT changes\n";
		}else{
			$filesInvolved = [];
			preg_match_all('/\tmodified:   (.*)/u',$result,$matches);
			if(isset($matches[1])){
				foreach($matches[1] as $f){
					if(pathinfo($f,PATHINFO_EXTENSION)=='js'){
						if(!in_array($f,$filesInvolved)){
							$filesInvolved[] = $f;
						}
					}
				}
			}
			if(preg_match('/Untracked files/s', $result)){
				$e = explode('Untracked files', $result);
				$m = explode("\n", $e[1]);
				array_shift($m);
				array_shift($m);
				array_shift($m);
				foreach($m as $f){
					if(empty($f)){
						break;
					}

					if(pathinfo($f,PATHINFO_EXTENSION)=='js'){
						if(!in_array($f,$filesInvolved)){
							$filesInvolved[] = $f;
						}
					}
				}
			}

			if(count($filesInvolved)){
				echo ' * Performing javascript file validation...'."\n";
				foreach($filesInvolved as $jsFile){
					$jsFile = str_replace("\t",'',$jsFile);
					$errs = shell_exec('jshint "'.$this->rootPath.$jsFile.'"');
					if(!$errs){
						echo $jsFile.' - PASSED'."\n";
						continue;
					}
					$err = explode("\n\n",$errs);
					$errs = $err[0]."\n";
					$this->jsErrors++;
					echo "---------------------- ".$jsFile." ----------------------\n";
					echo str_replace($this->rootPath.$jsFile.': ','',$errs);
					echo "----------------------------------------------".str_repeat('-', strlen($jsFile));
					echo "\n";
				}
			}
		}

		if($this->jsErrors){
			echo ' * Aborting process as there have been '.$this->jsErrors.' js files failing validation.';
			echo "\n";
			// return;
		}

		echo " * Verifying PHP files...\n";
		$this->verifyPhpFiles($this->rootPath);
		echo "\r";
		echo "\033[K";
		echo " * Total PHP files checked: {$this->phpFileCount} of which {$this->phpFileErrorCount} failed syntax checks.";
		echo "\n";

		if(!$this->commit){
			return;
		}

		if(!$this->phpFileErrorCount){
			if($committing){
				echo " * Commit message: ";
				$handle = fopen('php://stdin','r');
				$line = fgets($handle);
				$line = str_replace(['"',"\n"],'',$line);

				if(strlen($line) < 1){
					echo " * No commit message provided, aborting!";
					echo "\n";
					return;
				}

				shell_exec('cd "'.$this->rootPath.'" && git add -A . && git commit -am "'.$line.'"');
				echo " * Thanks, commit created you can now either push or pull another branch into {$branchName}.\n";
			}
		}
	}

	private function verifyPhpFiles($path){

		if(strpos($path, 'composer/') !== false) {
			return;
		}

		if(strpos($path, 'vendor/') !== false) {
			return;
		}

		foreach(scandir($path) as $file){
			if(in_array($file,['.','..'])){
				continue;
			}

			if(is_dir($path.$file)){
				$this->verifyPhpFiles($path.$file.$this->directorySeparator);
				continue;
			}

			if(pathinfo($file, PATHINFO_EXTENSION) !== 'php'){
				continue;
			}

			$this->phpFileCount++;

			echo "\r";
			echo "\033[K";
			echo str_replace($this->rootPath,'',$path).$file;
			$result = shell_exec('php -l "'.$path.$file.'"');

			if(preg_match('/^No syntax errors detected/s',$result)){
				continue;
			}

			$this->phpFileErrorCount++;

			echo "\033[1A";
			echo "\r";
			echo "\033[K";
			echo "\n";
			echo "################# ".str_replace($this->rootPath,'',$path).$file." #################";
			echo "#   \n#   ".str_replace("\n","\n#   ",$result)."\n";
			echo "#####################################".str_repeat('#', strlen(str_replace($this->rootPath,'',$path).$file));
			echo "\n";
			echo "\n";
			continue;
		}
	}
}
