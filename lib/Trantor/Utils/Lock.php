<?php

/**
 * Lock
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2021-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-lock
 */

namespace Trantor\Utils;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\IO as TrIOException;
use \Trantor\Exceptions\Application as TrAppException;

/**
 * Object for lock management.
 *
 * This object may be used to ensure that only one program has access to the same resource,
 * or a program could be executed only once at a time.
 * <code>
 * // 1. Basic usage: try to lock the execution of the current program
 * if (!\Trantor\Utils\Lock::lock()) {
 *     // the program is already in use
 * }
 *
 * // 2. Lock a file and wait until it's available, and unlock it afterwards
 * \Trantor\Utils\Lock::lock('/path/to/file', true);
 * ... do something
 * \Trantor\Utils\Lock::unlock('/path/to/file');
 *
 * // 3. Lock the access to a given file
 * // if the file is already locked, wait until it is available
 * try {
 *     $lock = new \Trantor\Utils\Lock('/path/to/file');
 * } catch (\Trantor\Exceptions\IO $eio) {
 *     // an error occurred
 * }
 * ... use the resource
 * unset($lock); // release the lock
 *
 * // 4. Lock a given file using a non-blocking method
 * try {
 *     $lock = new \Trantor\Utils\Lock('/path/to/file', false);
 * } catch (\Trantor\Exceptions\Application $ea) {
 *     // the file if already locked
 * } catch (\Trantor\Exceptions\IO $eio) {
 *     // an error occurred
 * }
 * ... use the resource
 * unset($lock); // release the lock
 * </code>
 */
class Lock {
	/** File descriptor opened on the locked file. */
	private /*?resource*/ $_fileDescriptor = null;
	/** List of locked files. */
	static private ?array $_lockedFiles = null;

	/**
	 * Contructor. Must be used when a resource need to be locked but not until the end of the program's execution.
	 * @param	?string	$path		(optional) Path to the file to lock. If not set, locks the running script.
	 * @param	bool	$blocking	(optional) Set to true to block until the resource is available, or to false to get an immediate response. (default: true)
	 * @throws	\Trantor\Exceptions\IO		If an error occurs.
	 * @throws	\Trantor\Exceptions\Application	If the file is already locked, in non-blocking mode.
	 */
	public function __construct(?string $path=null, bool $blocking=true) {
		TrLog::log('Trantor/Base', 'DEBUG', "Lock file '$path'.");
		$path = self::_getPath($path);
		// open file descriptor
		$fp = @fopen($path, 'r');
		if ($fp === false) {
			TrLog::log('Trantor/Base', 'WARN', "Unable to open file '$path' descriptor.");
			throw new TrIOException("Unable to open file '$path' descriptor.", TrIOException::UNREADABLE);
		}
		// lock file
		$operation = $blocking ? LOCK_EX : (LOCK_EX | LOCK_NB);
		if (!flock($fp, $operation)) {
			fclose($fp);
			if (!$blocking) {
				TrLog::log('Trantor/Base', 'DEBUG', "File '$path' is already locked.");
				throw new TrAppException("'File '$path' is already locked.", TrAppException::RETRY);
			}
			TrLog::log('Trantor/Base', 'DEBUG', "Error while locking file '$path'.");
			throw new TrIOException("Unable to lock file '$path'.", TrIOException::UNLOCKABLE);
		}
		$this->_fileDescriptor = $fp;
		TrLog::log('Trantor/Base', 'DEBUG', "File '$path' locked.");
	}
	/** Unlock a locked file. */
	public function __destruct() {
		fclose($this->_fileDescriptor);
	}
	/**
	 * Lock the access to the given file, until the end of the current program's execution or a call to the unlock() method.
	 * @param	?string	$path		(optional) Path to the file to lock. If not set, locks the running script.
	 * @param	bool	$blocking	(optional) Set to true to block until the resource is available. (default: false)
	 * @return	bool	True if the resource has been successfully locked, false if it was already locked (in non-blocking mode).
	 * @throws	\Trantor\Exceptions\IO	If an error occurs.
	 */
	static public function lock(?string $path=null, bool $blocking=false) : bool {
		TrLog::log('Trantor/Base', 'DEBUG', "Lock file '$path'.");
		self::$_lockedFiles ??= [];
		$path = self::_getPath($path);
		// check if the file was already locked
		if ($blocking && (self::$_lockedFiles[$path] ?? false)) {
			return (false);
		}
		// try to lock the file
		try {
			$lock = new self($path, $blocking);
		} catch (TrIOException $ioe) {
			throw $ioe;
		} catch (TrAppException $ae) {
			return (false);
		}
		// store the lock
		self::$_lockedFiles[$path] = $lock;
		return (true);
	}
	/**
	 * Unlock a previously locked file.
	 * @param	?string	$path	(optional) Path to the locked file. If not set, use the running script.
	 */
	static public function unlock(?string $path=null) : void {
		$path = self::_getPath($path);
		// search for the lock object
		if (!self::$_lockedFiles || !isset(self::$_lockedFiles[$path]))
			return;
		unset(self::$_lockedFiles[$path]);
	}

	/* ********** PRIVATE METHODS ********** */
	/**
	 * Returns the path to lock.
	 * @param	?string	$path	Path to lock. If set to null, returns the path of the running script.
	 * @return	string	The path to lock.
	 */
	static private function _getPath(?string $path) {
		if (!$path) {
			$path = $_SERVER['SCRIPT_FILENAME'];
			if ($path[0] != '/')
				$path = realpath($_SERVER['PWD'] . '/' . $path);
		}
		return ($path);
	}
}

