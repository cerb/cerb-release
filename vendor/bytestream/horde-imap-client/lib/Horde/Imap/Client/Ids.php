<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */

/**
 * An object that provides a way to identify a list of IMAP indices.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 *
 * @property-read boolean $all  Does this represent an ALL message set?
 * @property-read array $ids  The list of IDs.
 * @property-read boolean $largest  Does this represent the largest ID in use?
 * @property-read string $max  The largest ID (@since 2.20.0).
 * @property-read string $min  The smallest ID (@since 2.20.0).
 * @property-read string $range_string  Generates a range string consisting of
 *                                      all messages between begin and end of
 *                                      ID list.
 * @property-read boolean $search_res  Does this represent a search result?
 * @property-read boolean $sequence  Are these sequence IDs? If false, these
 *                                   are UIDs.
 * @property-read boolean $special  True if this is a "special" ID
 *                                  representation.
 * @property-read string $tostring  Return the non-sorted string
 *                                  representation.
 * @property-read string $tostring_sort  Return the sorted string
 *                                       representation.
 */
class Horde_Imap_Client_Ids implements Countable, Iterator, Serializable
{
    /**
     * "Special" representation constants.
     */
    const ALL = "\01";
    const SEARCH_RES = "\02";
    const LARGEST = "\03";

    /**
     * Allow duplicate IDs?
     *
     * @var boolean
     */
    public $duplicates = false;

    /**
     * List of IDs (flat integer array).
     *
     * @var mixed
     */
    protected $_ids = array();

    /**
     * Compact interval representation: sorted, non-overlapping [[start, end], ...]
     * When non-null, this is the authoritative data source and $_ids is stale.
     *
     * @var array|null
     */
    protected $_intervals = null;

    /**
     * Iterator state for interval mode.
     *
     * @var array
     */
    protected $_iterState = array();

    /**
     * Are IDs message sequence numbers?
     *
     * @var boolean
     */
    protected $_sequence = false;

    /**
     * Are IDs sorted?
     *
     * @var boolean
     */
    protected $_sorted = false;

    /**
     * Constructor.
     *
     * @param mixed $ids         See self::add().
     * @param boolean $sequence  Are $ids message sequence numbers?
     */
    public function __construct($ids = null, $sequence = false)
    {
        $this->add($ids);
        $this->_sequence = $sequence;
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'all':
            return ($this->_ids === self::ALL);

        case 'ids':
            if ($this->_intervals !== null) {
                $this->_expandIntervals();
            }
            return is_array($this->_ids)
                ? $this->_ids
                : array();

        case 'largest':
            return ($this->_ids === self::LARGEST);

        case 'max':
            if ($this->_intervals !== null) {
                $last = end($this->_intervals);
                return $last[1];
            }
            $this->sort();
            return end($this->_ids);

        case 'min':
            if ($this->_intervals !== null) {
                return $this->_intervals[0][0];
            }
            $this->sort();
            return reset($this->_ids);

        case 'range_string':
            if (!count($this)) {
                return '';
            }

            $min = $this->min;
            $max = $this->max;

            return ($min == $max)
                ? $min
                : $min . ':' . $max;

        case 'search_res':
            return ($this->_ids === self::SEARCH_RES);

        case 'sequence':
            return (bool)$this->_sequence;

        case 'special':
            return is_string($this->_ids);

        case 'tostring':
        case 'tostring_sort':
            if ($this->all) {
                return '1:*';
            } elseif ($this->largest) {
                return '*';
            } elseif ($this->search_res) {
                return '$';
            }
            if ($this->_intervals !== null) {
                return $this->_intervalsToSequenceString();
            }
            return strval($this->_toSequenceString($name == 'tostring_sort'));
        }
    }

    /**
     */
    public function __toString()
    {
        return $this->tostring;
    }

    /**
     * Add IDs to the current object.
     *
     * @param mixed $ids  Either self::ALL, self::SEARCH_RES, self::LARGEST,
     *                    Horde_Imap_Client_Ids object, array, or sequence
     *                    string.
     */
    public function add($ids)
    {
        if (is_null($ids)) {
            return;
        }

        if (is_string($ids) &&
            in_array($ids, array(self::ALL, self::SEARCH_RES, self::LARGEST))) {
            $this->_ids = $ids;
            $this->_intervals = null;
            return;
        }

        $add = $this->_resolveIds($ids);
        if (empty($add)) {
            return;
        }

        // Detect whether _resolveIds returned intervals [[start,end],...]
        // or a flat array [id,...]. Base class _fromSequenceString returns
        // intervals for range strings; Pop3 subclass returns flat strings.
        $addIsIntervals = isset($add[0]) && is_array($add[0]);

        // Intervals cannot represent duplicates. Expand to flat when needed.
        if ($addIsIntervals && $this->duplicates) {
            $flat = array();
            foreach ($add as $iv) {
                for ($i = $iv[0]; $i <= $iv[1]; $i++) {
                    $flat[] = $i;
                }
            }
            $add = $flat;
            $addIsIntervals = false;
        }

        if ($addIsIntervals) {
            // Merge into interval storage
            $existing = $this->_intervals ?? array();
            if (is_array($this->_ids) && !empty($this->_ids)) {
                $existing = array_merge($existing, $this->_flatToIntervals($this->_ids));
                $this->_ids = array();
            }
            $this->_intervals = $this->_mergeIntervals(array_merge($existing, $add));
            $this->_sorted = true;
        } elseif ($this->_intervals !== null) {
            // Flat input but we're in interval mode: convert and merge
            $this->_intervals = $this->_mergeIntervals(
                array_merge($this->_intervals, $this->_flatToIntervals($add))
            );
            $this->_sorted = true;
        } else {
            // Pure flat path (original behavior)
            if (is_array($this->_ids) && !empty($this->_ids)) {
                foreach ($add as $val) {
                    $this->_ids[] = (int)$val;
                }
            } else {
                $this->_ids = $add;
            }
            if (!$this->duplicates) {
                $this->_ids = (count($this->_ids) > 25000)
                    ? array_unique($this->_ids)
                    : array_keys(array_flip($this->_ids));
            }
            $this->_sorted = is_array($this->_ids) && (count($this->_ids) === 1);
        }
    }

    /**
     * Removed IDs from the current object.
     *
     * @since 2.17.0
     *
     * @param mixed $ids  Either Horde_Imap_Client_Ids object, array, or
     *                    sequence string.
     */
    public function remove($ids)
    {
        if ($this->isEmpty()) {
            return;
        }

        $remove = $this->_resolveIds($ids);
        if (empty($remove)) {
            return;
        }

        $removeIsIntervals = isset($remove[0]) && is_array($remove[0]);

        if ($this->_intervals !== null) {
            $removeIntervals = $removeIsIntervals
                ? $this->_mergeIntervals($remove)
                : $this->_mergeIntervals($this->_flatToIntervals($remove));
            $this->_intervals = $this->_subtractIntervals($this->_intervals, $removeIntervals);
        } else {
            if ($removeIsIntervals) {
                $flat = array();
                foreach ($remove as $iv) {
                    for ($i = $iv[0]; $i <= $iv[1]; $i++) {
                        $flat[] = $i;
                    }
                }
                $remove = $flat;
            }
            $this->_ids = array_diff($this->_ids, array_unique($remove));
        }
    }

    /**
     * Is this object empty (i.e. does not contain IDs)?
     *
     * @return boolean  True if object is empty.
     */
    public function isEmpty()
    {
        if ($this->_intervals !== null) {
            return empty($this->_intervals);
        }
        return (is_array($this->_ids) && !count($this->_ids));
    }

    /**
     * Reverses the order of the IDs.
     */
    public function reverse()
    {
        if ($this->_intervals !== null) {
            $this->_expandIntervals();
        }
        if (is_array($this->_ids)) {
            $this->_ids = array_reverse($this->_ids);
        }
    }

    /**
     * Sorts the IDs.
     */
    public function sort()
    {
        if ($this->_intervals !== null) {
            // Intervals are always sorted.
            return;
        }
        if (!$this->_sorted && is_array($this->_ids)) {
            $this->_sort($this->_ids);
            $this->_sorted = true;
        }
    }

    /**
     * Sorts the IDs numerically.
     *
     * @param array $ids  The array list.
     */
    protected function _sort(&$ids)
    {
        sort($ids, SORT_NUMERIC);
    }

    /**
     * Split the sequence string at an approximate length.
     *
     * @since 2.7.0
     *
     * @param integer $length  Length to split.
     *
     * @return array  A list containing individual sequence strings.
     */
    public function split($length)
    {
        $id = new Horde_Stream_Temp();
        $id->add($this->tostring_sort, true);

        $out = array();

        do {
            $out[] = $id->substring(0, $length) . $id->getToChar(',');
        } while (!$id->eof());

        return $out;
    }

    /**
     * Resolve the $ids input to add() and remove().
     *
     * @param mixed $ids  Either Horde_Imap_Client_Ids object, array, or
     *                    sequence string.
     *
     * @return array  An array of IDs or intervals.
     */
    protected function _resolveIds($ids)
    {
        if ($ids instanceof Horde_Imap_Client_Ids) {
            // Prefer intervals to avoid expanding large sets.
            if ($ids->_intervals !== null) {
                return $ids->_intervals;
            }
            return $ids->ids;
        } elseif (is_array($ids)) {
            return $ids;
        } elseif (is_string($ids) || is_integer($ids)) {
            return is_numeric($ids)
                ? array($ids)
                : $this->_fromSequenceString($ids);
        }

        return array();
    }

    /**
     * Create an IMAP message sequence string from a list of indices.
     *
     * Index Format: range_start:range_end,uid,uid2,...
     *
     * @param boolean $sort  Numerically sort the IDs before creating the
     *                       range?
     *
     * @return string  The IMAP message sequence string.
     */
    protected function _toSequenceString($sort = true)
    {
        if (empty($this->_ids)) {
            return '';
        }

        $in = $this->_ids;

        if ($sort && !$this->_sorted) {
            $this->_sort($in);
        }

        $first = $last = array_shift($in);
        $i = count($in) - 1;
        $out = array();

        foreach ($in as $key => $val) {
            if (($last + 1) == $val) {
                $last = $val;
            }

            if (($i == $key) || ($last != $val)) {
                if ($last == $first) {
                    $out[] = $first;
                    if ($i == $key) {
                        $out[] = $val;
                    }
                } else {
                    $out[] = $first . ':' . $last;
                    if (($i == $key) && ($last != $val)) {
                        $out[] = $val;
                    }
                }
                $first = $last = $val;
            }
        }

        return empty($out)
            ? $first
            : implode(',', $out);
    }

    /**
     * Parse an IMAP message sequence string into a list of indices.
     *
     * Returns compact [start, end] interval pairs when the string contains
     * range syntax (colon), or a flat array of integers otherwise. This
     * prevents OOM when parsing large ranges like "1:17000000".
     *
     * @see _toSequenceString()
     *
     * @param string $str  The IMAP message sequence string.
     *
     * @return array  An array of [start, end] interval pairs when ranges are
     *                present, or a flat array of integer IDs otherwise.
     */
    protected function _fromSequenceString($str)
    {
        $str = trim($str);

        if (!strlen($str)) {
            return array();
        }

        $idarray = explode(',', $str);

        // Only use compact interval representation when ranges are present.
        // Plain comma-separated IDs stay flat to preserve insertion order.
        if (strpos($str, ':') !== false) {
            $intervals = array();
            foreach ($idarray as $val) {
                $range = explode(':', $val);
                $start = (int)$range[0];
                if (isset($range[1])) {
                    $end = (int)$range[1];
                    if ($start > $end) {
                        $intervals[] = array($end, $start);
                    } else {
                        $intervals[] = array($start, $end);
                    }
                } else {
                    $intervals[] = array($start, $start);
                }
            }
            return $intervals;
        }

        $ids = array();
        foreach ($idarray as $val) {
            $ids[] = (int)$val;
        }
        return $ids;
    }

    /**
     * Expand intervals into a flat _ids array.
     */
    protected function _expandIntervals()
    {
        if ($this->_intervals === null) {
            return;
        }
        $this->_ids = array();
        foreach ($this->_intervals as $iv) {
            for ($i = $iv[0]; $i <= $iv[1]; $i++) {
                $this->_ids[] = $i;
            }
        }
        $this->_intervals = null;
        $this->_sorted = true;
    }

    /**
     * Convert a flat array of IDs into single-element intervals.
     *
     * @param array $ids  Flat array of integer IDs.
     *
     * @return array  Array of [start, end] interval pairs.
     */
    protected function _flatToIntervals(array $ids)
    {
        $intervals = array();
        foreach ($ids as $id) {
            $intervals[] = array((int)$id, (int)$id);
        }
        return $intervals;
    }

    /**
     * Merge overlapping or adjacent intervals into a minimal sorted set.
     *
     * @param array $intervals  Array of [start, end] pairs.
     *
     * @return array  Sorted, non-overlapping array of [start, end] pairs.
     */
    protected function _mergeIntervals($intervals)
    {
        if (empty($intervals)) {
            return array();
        }

        usort($intervals, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        $merged = array($intervals[0]);
        for ($i = 1, $len = count($intervals); $i < $len; $i++) {
            $last = count($merged) - 1;
            if ($intervals[$i][0] <= $merged[$last][1] + 1) {
                if ($intervals[$i][1] > $merged[$last][1]) {
                    $merged[$last][1] = $intervals[$i][1];
                }
            } else {
                $merged[] = $intervals[$i];
            }
        }

        return $merged;
    }

    /**
     * Subtract removal intervals from source intervals.
     *
     * Both inputs must be sorted and non-overlapping.
     *
     * @param array $intervals  Source intervals.
     * @param array $remove     Intervals to remove.
     *
     * @return array  Resulting intervals after subtraction.
     */
    protected function _subtractIntervals($intervals, $remove)
    {
        if (empty($remove)) {
            return $intervals;
        }

        $result = array();
        $ri = 0;
        $rlen = count($remove);

        foreach ($intervals as $iv) {
            $start = $iv[0];
            $end = $iv[1];

            // Skip removal intervals that end before this interval starts.
            while ($ri < $rlen && $remove[$ri][1] < $start) {
                $ri++;
            }

            $rj = $ri;
            while ($rj < $rlen && $remove[$rj][0] <= $end) {
                if ($start < $remove[$rj][0]) {
                    $result[] = array($start, $remove[$rj][0] - 1);
                }
                $start = max($start, $remove[$rj][1] + 1);
                $rj++;
            }

            if ($start <= $end) {
                $result[] = array($start, $end);
            }
        }

        return $result;
    }

    /**
     * Format intervals as an IMAP sequence string.
     *
     * @return string  The IMAP message sequence string.
     */
    protected function _intervalsToSequenceString()
    {
        if (empty($this->_intervals)) {
            return '';
        }

        $parts = array();
        foreach ($this->_intervals as $iv) {
            $parts[] = ($iv[0] == $iv[1])
                ? (string)$iv[0]
                : $iv[0] . ':' . $iv[1];
        }

        return implode(',', $parts);
    }

    /* Countable methods. */

    /**
     */
    #[ReturnTypeWillChange]
    public function count()
    {
        if ($this->_intervals !== null) {
            $count = 0;
            foreach ($this->_intervals as $iv) {
                $count += $iv[1] - $iv[0] + 1;
            }
            return $count;
        }
        return is_array($this->_ids)
            ? count($this->_ids)
            : 0;
    }

    /* Iterator methods. */

    /**
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if ($this->_intervals !== null) {
            if (!isset($this->_iterState['idx'])
                || $this->_iterState['idx'] >= count($this->_intervals)) {
                return null;
            }
            return $this->_iterState['pos'];
        }
        return is_array($this->_ids)
            ? current($this->_ids)
            : null;
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        if ($this->_intervals !== null) {
            if (!isset($this->_iterState['idx'])
                || $this->_iterState['idx'] >= count($this->_intervals)) {
                return null;
            }
            return $this->_iterState['key'];
        }
        return is_array($this->_ids)
            ? key($this->_ids)
            : null;
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        if ($this->_intervals !== null) {
            if (!isset($this->_iterState['idx'])) {
                return;
            }
            $this->_iterState['key']++;
            $this->_iterState['pos']++;
            if ($this->_iterState['pos'] > $this->_intervals[$this->_iterState['idx']][1]) {
                $this->_iterState['idx']++;
                if ($this->_iterState['idx'] < count($this->_intervals)) {
                    $this->_iterState['pos'] = $this->_intervals[$this->_iterState['idx']][0];
                }
            }
        } elseif (is_array($this->_ids)) {
            next($this->_ids);
        }
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function rewind()
    {
        if ($this->_intervals !== null) {
            $this->_iterState = array();
            if (!empty($this->_intervals)) {
                $this->_iterState['idx'] = 0;
                $this->_iterState['pos'] = $this->_intervals[0][0];
                $this->_iterState['key'] = 0;
            }
        } elseif (is_array($this->_ids)) {
            reset($this->_ids);
        }
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function valid()
    {
        return !is_null($this->key());
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return serialize($this->__serialize());
    }

    /**
     */
    public function unserialize($data)
    {
        $this->__unserialize(@unserialize($data));
    }

    /**
     * @return array
     */
    public function __serialize()
    {
        $save = array();

        if ($this->duplicates) {
            $save['d'] = 1;
        }

        if ($this->_sequence) {
            $save['s'] = 1;
        }

        if ($this->_sorted) {
            $save['is'] = 1;
        }

        switch ($this->_ids) {
            case self::ALL:
                $save['a'] = true;
                break;

            case self::LARGEST:
                $save['l'] = true;
                break;

            case self::SEARCH_RES:
                $save['sr'] = true;
                break;

            default:
                $save['i'] = strval($this);
                break;
        }

        return $save;
    }

    public function __unserialize(array $data)
    {
        $this->duplicates = !empty($data['d']);
        $this->_sequence = !empty($data['s']);
        $this->_sorted = !empty($data['is']);

        if (isset($data['a'])) {
            $this->_ids = self::ALL;
        } elseif (isset($data['l'])) {
            $this->_ids = self::LARGEST;
        } elseif (isset($data['sr'])) {
            $this->_ids = self::SEARCH_RES;
        } elseif (isset($data['i'])) {
            $this->add($data['i']);
        }
    }

}
