<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
	return false;
}
echo microtime(true)."<br/>";

// Setup autoloading
require 'init_autoloader.php';
//include dirname(__DIR__).'/complie.php';
// Run the application!
Zend\Mvc\Application::init(require 'config/application.config.php')->run();

class ComplieZendClass {

	private $included_files;
	private $class_maps;
	private $include_file_path;
	private $complie_file_path;

	public function __construct($classmap_path) {
		$included_files = array();
		foreach (get_included_files() as $k => $v) {
			$included_files[$k] = str_replace('\\', '/', $v);
		}
		$this->included_files = array_flip($included_files);
		if (is_array($classmap_path)) {
			
		} else {
			$class_maps = include $classmap_path;
			foreach ($class_maps as $k => $v) {
				$class_maps[$k] = str_replace('\\', '/', $v);
			}
			$this->class_maps = array_flip($class_maps);
		}
	}

	/**
	 * 生成需要包含的文件
	 * @param type $path
	 */
	public function generate_include_file($path) {
		$this->include_file_path = $path;
		$load_class_maps = array();
		foreach ($this->included_files as $key => $value) {
			if (array_key_exists($key, $this->class_maps)) {
				$load_class_maps[$key] = $this->class_maps[$key];
			}
		}

		$file_content = "<?php\n return " . var_export($load_class_maps, true) . "\n?>";
		file_put_contents($this->include_file_path, $file_content);
		return $this;
	}

	/**
	 * 设置编译文件路径
	 * @author yuhaya
	 * @param type $path
	 */
	public function setComplieFilePath($path) {
		$this->complie_file_path = $path;
		return $this;
	}
	
	private function changeSortFile(){
		$file_mes_arr = include $this->include_file_path;
		$sort_array = array();
		foreach($file_mes_arr as $path=>$class){
			include_once $path;
			$ref_class = new ReflectionClass($class); 
			$interfaces = $ref_class->getInterfaceNames();
			$parent_class = $ref_class->getParentClass()  != false ? $ref_class->getParentClass()->getName() : false;
			$class_name = $ref_class->getName();
			$trait_names = $ref_class->getTraitNames();
			
			foreach($interfaces as $v){
				if(!in_array($v, $sort_array)){
					$sort_array[] = $v;
				}
			}
			
			if($parent_class){
				if(!in_array($parent_class, $sort_array)){
					$sort_array[] = $parent_class;
				}
			}
			
			if($trait_names){
				foreach($trait_names as $v){
					if(!in_array($v, $sort_array)){
						$sort_array[] = $v;
					}
				}
			}
			
			if(!in_array($class_name, $sort_array)){
					$sort_array[] = $class_name;
			}
		}
		
		$path_class = array_flip(include $this->include_file_path);
		$over_class = array(
			'Zend\Loader\SplAutoloader',
			'Zend\Loader\AutoloaderFactory',
			'Zend\Loader\StandardAutoloader',
			'Zend\Http\AbstractMessage',
			'Zend\Stdlib\Message',
			'Zend\Mvc\ResponseSender\AbstractResponseSender',
			'Zend\Filter\Word\AbstractSeparator',
			'Zend\Filter\AbstractFilter'
		);
		$file_over = array();
		foreach($sort_array as $k=>$v){
			if(preg_match('/\\\/', $v) && !in_array($v, $over_class) && array_key_exists($v, $path_class)){
				$file_over[$v] = $path_class[$v];
			}
		}
//		echo "<pre>";
//		print_r($file_over);exit;
		return $file_over;
	}

	public function complie() {
		$file_mes_arr = $this->changeSortFile();
		foreach ($file_mes_arr as $path) {
			$Content = $this->strip_whitespace(file_get_contents($path));
			$Content = preg_replace('/^\s*/', '', $Content); //匹配开始空格
			$File = str_replace('\\', '/', $path);
			$Dir = str_replace('/' . basename($path), '', $path);
			$Content = preg_replace('/__DIR__/', "'" . $Dir . "'", $Content); //匹配开始空格
			$position = stripos($Content, 'namespace');
			if ($position === 0) {
				file_put_contents($this->complie_file_path, "\n" . $Content, FILE_APPEND);
			}
		}

		$complie = file_get_contents($this->complie_file_path);
		$str = "<?php \n" . $complie . "\n?>";
		file_put_contents($this->complie_file_path, $str);
		return $this;
	}

	private function strip_whitespace($content) {
		$stripStr = '';
		$tokens = token_get_all($content);
		$last_space = false;
		for ($i = 0, $j = count($tokens); $i < $j; $i++) {
			if (is_string($tokens[$i])) {
				$last_space = false;
				$stripStr .= $tokens[$i];
			} else {
				switch ($tokens[$i][0]) {
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_OPEN_TAG;
					case T_CLOSE_TAG;
						break;
					case T_WHITESPACE:
						if (!$last_space) {
							$stripStr .= ' ';
							$last_space = true;
						}
						break;
					default:
						$last_space = false;
						$stripStr .= $tokens[$i][1];
				}
			}
		}
		return $stripStr;
	}

}

$classmap_path = __DIR__ . '/../vendor/ZF2/library/autoload_classmap.php';
$complie = new ComplieZendClass($classmap_path);
$complie->generate_include_file(__DIR__ . '/tmp.php')
	->setComplieFilePath(dirname(__DIR__).'/complie.php')
	->complie();
