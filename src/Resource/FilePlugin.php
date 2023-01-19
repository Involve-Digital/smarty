<?php
/**
 * Smarty Internal Plugin Resource File
 *


 * @author     Uwe Tews
 * @author     Rodney Rehm
 */

namespace Smarty\Resource;

use Smarty\Template;
use Smarty\Template\Source;
use Smarty\Exception;

/**
 * Smarty Internal Plugin Resource File
 * Implements the file system as resource for Smarty templates
 *


 */
class FilePlugin extends BasePlugin {

	/**
	 * populate Source Object with meta data from Resource
	 *
	 * @param Source $source source object
	 * @param Template $_template template object
	 *
	 * @throws \Smarty\Exception
	 */
	public function populate(Source $source, Template $_template = null) {
		$source->filepath = $this->buildFilepath($source, $_template);
		if ($source->filepath !== false) {
			if (isset($source->getSmarty()->security_policy) && is_object($source->getSmarty()->security_policy)) {
				$source->getSmarty()->security_policy->isTrustedResourceDir($source->filepath, $source->isConfig);
			}
			$source->exists = true;
			$source->uid = sha1(
				$source->filepath . ($source->isConfig ? $source->getSmarty()->_joined_config_dir :
					$source->getSmarty()->_joined_template_dir)
			);
			$source->timestamp = filemtime($source->filepath);
		} else {
			$source->timestamp = $source->exists = false;
		}
	}

	/**
	 * populate Source Object with timestamp and exists from Resource
	 *
	 * @param Source $source source object
	 */
	public function populateTimestamp(Source $source) {
		if (!$source->exists) {
			$source->timestamp = $source->exists = is_file($source->filepath);
		}
		if ($source->exists) {
			$source->timestamp = filemtime($source->filepath);
		}
	}

	/**
	 * Load template's source from file into current template object
	 *
	 * @param Source $source source object
	 *
	 * @return string                 template source
	 * @throws Exception        if source cannot be loaded
	 */
	public function getContent(Source $source) {
		if ($source->exists) {
			return file_get_contents($source->filepath);
		}
		throw new Exception(
			'Unable to read ' . ($source->isConfig ? 'config' : 'template') .
			" {$source->type} '{$source->name}'"
		);
	}

	/**
	 * Determine basename for compiled filename
	 *
	 * @param Source $source source object
	 *
	 * @return string                 resource's basename
	 */
	public function getBasename(Source $source) {
		return basename($source->filepath);
	}

	/**
	 * build template filepath by traversing the template_dir array
	 *
	 * @param Source $source source object
	 * @param Template $_template template object
	 *
	 * @return string fully qualified filepath
	 * @throws Exception
	 */
	protected function buildFilepath(Source $source, Template $_template = null) {
		$file = $source->name;
		// absolute file ?
		if ($file[0] === '/' || $file[1] === ':') {
			$file = $source->getSmarty()->_realpath($file, true);
			return is_file($file) ? $file : false;
		}
		// go relative to a given template?
		if ($file[0] === '.' && $_template && $_template->_isSubTpl()
			&& preg_match('#^[.]{1,2}[\\\/]#', $file)
		) {
			if ($_template->parent->getSource()->type !== 'file' && $_template->parent->getSource()->type !== 'extends') {
				throw new Exception("Template '{$file}' cannot be relative to template of resource type '{$_template->parent->getSource()->type}'");
			}
			// normalize path
			$path =
				$source->getSmarty()->_realpath(dirname($_template->parent->getSource()->filepath) . DIRECTORY_SEPARATOR . $file);
			// files relative to a template only get one shot
			return is_file($path) ? $path : false;
		}
		// normalize DIRECTORY_SEPARATOR
		if (strpos($file, DIRECTORY_SEPARATOR === '/' ? '\\' : '/') !== false) {
			$file = str_replace(DIRECTORY_SEPARATOR === '/' ? '\\' : '/', DIRECTORY_SEPARATOR, $file);
		}
		$_directories = $source->getSmarty()->getTemplateDir(null, $source->isConfig);
		// template_dir index?
		if ($file[0] === '[' && preg_match('#^\[([^\]]+)\](.+)$#', $file, $fileMatch)) {
			$file = $fileMatch[2];
			$_indices = explode(',', $fileMatch[1]);
			$_index_dirs = [];
			foreach ($_indices as $index) {
				$index = trim($index);
				// try string indexes
				if (isset($_directories[$index])) {
					$_index_dirs[] = $_directories[$index];
				} elseif (is_numeric($index)) {
					// try numeric index
					$index = (int)$index;
					if (isset($_directories[$index])) {
						$_index_dirs[] = $_directories[$index];
					} else {
						// try at location index
						$keys = array_keys($_directories);
						if (isset($_directories[$keys[$index]])) {
							$_index_dirs[] = $_directories[$keys[$index]];
						}
					}
				}
			}
			if (empty($_index_dirs)) {
				// index not found
				return false;
			} else {
				$_directories = $_index_dirs;
			}
		}
		// relative file name?
		foreach ($_directories as $_directory) {
			$path = $_directory . $file;
			if (is_file($path)) {
				return (strpos($path, '.' . DIRECTORY_SEPARATOR) !== false) ? $source->getSmarty()->_realpath($path) : $path;
			}
		}
		if (!isset($_index_dirs)) {
			// Could be relative to cwd
			$path = $source->getSmarty()->_realpath($file, true);
			if (is_file($path)) {
				return $path;
			}
		}
		return false;
	}
}