<?php

/**
 * File.
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-file
 */

namespace Trantor\Utils;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\IO as TrIOException;

/**
 * Object used to manage files and folders.send emails.
 */
class File {
	/**
	 * Recursively copy the content of a file or a directory.
	 * @param	string	$from	Source.
	 * @param	string	$to	Destination.
	 * @param	bool	$force	(optional) If true, remove files, symlinks and directories with the same name but a different type
	 *				than a copied element (default to false).
	 * @param	bool	$sync	(optional) If true, remove unkown files from destination (defaults to false).
	 * @param	bool	$hidden	(optional) If true, copy files and directories starting with a dot (defaults to false).
	 * @throws	\Trantor\Exceptions\IO	If a copy failed.
	 */
	static public function recursiveCopy(string $from, string $to, bool $force=false, bool $sync=false, bool $hidden=false) : void {
		// remove unknown files
		if ($sync && is_dir($to)) {
			if (($dir = opendir($to)) === false)
				throw new TrIOException("Unable to open directory '$to'.", TrIOException::UNREADABLE);
			while (($item = readdir($dir))) {
				if ($item == '.' || $item == '..' ||
				    (!$hidden && str_starts_with($item, '.')))
					continue;
				$fromItem = $from . DIRECTORY_SEPARATOR . $item;
				$toItem = $to . DIRECTORY_SEPARATOR . $item;
				if (is_dir($toItem) && !is_dir($fromItem))
					self::recursiveRemove($toItem);
				else if (((is_file($toItem) && !is_file($fromItem)) ||
				          (is_link($toItem) && !is_link($fromItem))) &&
					 !unlink($toItem))
					throw new TrIOException("Unable to remove file '$toItem'.", TrIOException::FUNDAMENTAL);
			}
			closedir($dir);
		}
		// recreate a symlink
		if (is_link($from)) {
			// end of processing if a file or a dir exists and we don't force the copy
			if (file_exists($to) && !is_link($to) && !$force)
				return;
			// remove existing file or link with the destination name
			if ((is_file($to) || is_link($to)) && !unlink($to))
				throw new TrIOException("Unable to remove '$to'.", TrIOException::FUNDAMENTAL);
			// remove existing directory with the destination name
			if (is_dir($to))
				self::recursiveRemove($to);
			// read the source link to get the target
			if (!($target = readlink($from)))
				throw new TrIOException("Unable to read link '$from'.", TrIOException::UNREADABLE);
			// create the destination link
			if (!symlink($target, $to))
				throw new TrIOException("Unable to create link '$to' linking to '$target'.", TrIOException::UNWRITABLE);
			return;
		}
		// copy a file
		if (is_file($from)) {
			// enid of processing if a symlink or a dir exists and we don't force the copy
			if (file_exists($to) && !is_file($to) && !$force)
				return;
			// remove existing link with the destination name)
			if (is_link($to) && !unlink($to))
				throw new TrIOException("Unable to remove '$to'.", TrIOException::FUNDAMENTAL);
			// remove existing directory with the destination name
			if (is_dir($to))
				self::recursiveRemove($to);
			// copy the file
			if (!copy($from, $to))
				throw new TrIOException("Unable to copy '$from' to '$to'.", TrIOException::FUNDAMENTAL);
			return;
		}
		// check source
		if (!is_dir($from))
			throw new TrIOException("Unknown file type '$from'.", TrIOException::BAD_FORMAT);
		// remove and create destination if needed
		if (!is_dir($to)) {
			if (file_exists($to)) {
				if (!$force)
					return;
				if (!unlink($to))
					throw new TrIOException("Unable to remove file '$to'.", TrIOException::FUNDAMENTAL);
			}
			if (!mkdir($to, recursive: true))
				throw new TrIOException("Unable to create directory '$to'.", TrIOException::UNWRITABLE);
		}
		// copy the directory content
		if (($dir = opendir($from)) === false)
			throw new TrIOException("Unable to open directory '$from'.", TrIOException::UNREADABLE);
		while (($item = readdir($dir))) {
			if ($item == '.' || $item == '..' ||
			    ($hidden && str_starts_with($item, '.')))
				continue;
			$fromItem = $from . DIRECTORY_SEPARATOR . $item;
			$toItem = $to . DIRECTORY_SEPARATOR . $item;
			self::recursiveCopy($fromItem, $toItem, $force, $sync, $hidden);
		}
		closedir($dir);
	}
	/**
         * Remove a directory and its content.
         * @param       string  $path   Path to the directory.
	 * @throws	\Trantor\Exceptions\IO	If an error occurred.
         */
        static public function recursiveRemove(string $path) : void {
		// remove file/symlink
                if (!is_dir($path)) {
			if (!unlink($path))
				throw new TrIOException("Unable to remove '$path'.", TrIOException::FUNDAMENTAL);
			return;
		}
		// remove directory content
		if (($dir = opendir($path)) === false)
			throw new TrIOException("Unable to open directory '$path'.", TrIOException::UNREADABLE);
		while (($item = readdir($dir))) {
			if ($item == '.' || $item == '..')
				continue;
			self::recursiveRemove($path . DIRECTORY_SEPARATOR . $item);
                }
		closedir($dir);
		// remove directory
                rmdir($path);
        }
}

