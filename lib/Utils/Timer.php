<?php

namespace Temma\Utils;

/**
 * Timing object.
 *
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2007-2019, Amaury Bouchard
 * @package	Temma
 * @subpackage	Utils
 */
class Timer {
	/** Date of timing start. */
	private $_begin = null;
	/** Date of timing end. */
	private $_end = null;

	/** Starts a timing. */
	public function start() {
		$this->_begin = microtime();
		$this->_end = null;
	}
	/** Stops a timing. */
	public function stop() {
		$this->_end = microtime();
	}
	/** Resume a timing. */
	public function resume() {
		$this->_end = null;
	}
	/**
	 * Returns the elapsed time during a timing.
	 * @return	int	Elapsed time in microseconds.
	 * @throws	\Exception	If the timer wasn't started correctly.
	 */
	public function getTime() {
		if (is_null($this->_begin))
			return (0);
		list($uSecondeA, $secondeA) = explode(' ', $this->_begin);
		$end = is_null($this->_end) ? microtime() : $this->_end;
		list($uSecondeB, $secondeB) = explode(' ', $end);
		$total = ($secondeA - $secondeB) + ($uSecondeA - $uSecondeB);
		return (number_format(abs($total), 16));
	}
}

