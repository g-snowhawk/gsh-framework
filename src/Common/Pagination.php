<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common;

/**
 * Multi language translator.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Pagination
{
    private $current_page;
    private $max_per_page;
    private $total_pages;
    private $total_records;
    private $link_count;
    private $link_format;
    private $link_start;
    private $link_end;
    private $link_prev;
    private $link_next;
    private $suffix_separator = '';
    private $inited = false;

    /**
     * Object constructor.
     */
    public function __construct($total = 0, $rows = 0, $link_count = null)
    {
        $this->total_records = $total;
        $this->total_pages = ($rows > 0) ? intval(ceil($total / $rows)) : 0;
        $this->max_per_pages = $rows;
        $this->link_count = $link_count ?? $this->total_pages;
        $this->current_page = 1;
        $this->link_start = 1;
        $this->link_end = $this->link_start + $this->link_count;
    }

    /**
     * Clone this class.
     */
    public function __clone()
    {
        $this->current_page = null;
        $this->max_per_page = null;
        $this->total_records = null;
        $this->total_pages = null;
        $this->link_count = null;
        $this->link_format = null;
        $this->link_start = null;
        $this->link_end = null;
        $this->suffix_separator = '';
        $this->inited = false;
    }

    /**
     * Initialize.
     *
     * @param int $total
     * @param int $rows
     * @param int $linkcount
     */
    public function init($total, $rows, $link_count = 0)
    {
        if ($this->inited === true) {
            return;
        }
        $this->total_records = $total;
        $this->total_pages = intval(ceil($total / $rows));
        $this->max_per_pages = $rows;
        $this->link_count = $link_count ?? $this->total_pages;
        $this->current_page = 1;
        $this->link_start = $this->current_page;
        $this->link_end = $this->current_page + $this->link_count;
        $this->inited = true;
    }

    /**
     * Update initialized flag.
     *
     * @param bool $flag
     */
    public function setInited($boolean)
    {
        $this->inited = (bool)$boolean;
    }

    /**
     * Reference already initialized.
     *
     * @return bool
     */
    public function isInited()
    {
        return $this->inited;
    }

    /**
     * Modify current page number.
     *
     * @param int $page_number
     *
     * @return int
     */
    public function setCurrentPage($page_number)
    {
        $this->current_page = $page_number;
        $this->link_start = $this->start();
        $this->link_end = $this->end();

        return $this->current_page;
    }

    /**
     * Modify link format.
     *
     * @param string $format
     *
     * @return int
     */
    public function setLinkFormat($format)
    {
        return $this->link_format = $format;
    }

    public function setLinkPrev($page)
    {
        return $this->link_prev = $page;
    }

    public function setLinkNext($page)
    {
        return $this->link_next = $page;
    }

    public function setLinkCount($count)
    {
        return $this->link_count = $count;
    }

    /**
     * Reference to current page number.
     *
     * @return int
     */
    public function current()
    {
        return $this->current_page;
    }

    /**
     * Reference to previous page number.
     *
     * @return int
     */
    public function prev()
    {
        return $this->current_page - 1;
    }

    /**
     * Reference to next page number.
     *
     * @return int
     */
    public function next()
    {
        return $this->current_page + 1;
    }

    /**
     * Reference to pages total count.
     *
     * @return int
     */
    public function total()
    {
        return $this->total_pages;
    }

    /**
     * Reference to records total count.
     *
     * @return int
     */
    public function records()
    {
        return $this->total_records;
    }

    /**
     * Reference to link format.
     *
     * @return int
     */
    public function format()
    {
        return $this->link_format;
    }

    /**
     * Reference to navigation start number.
     *
     * @return int
     */
    public function start()
    {
        $start = 1;
        if (!empty($this->link_prev)) {
            $start = $this->link_prev - 1;
        }
        if (!empty($this->link_next)) {
            $start = $this->link_next - $this->link_count + 2;
        }
        if ($start + $this->link_count > $this->total_pages) {
            $start = $this->total_pages - $this->link_count + 1;
        }
        if ($start < 1) {
            $start = 1;
        }

        return $start;
    }

    /**
     * Reference to navigation end number.
     *
     * @return int
     */
    public function end()
    {
        $end = $this->start() + $this->link_count;
        if ($end > $this->total_pages) {
            $end = $this->total_pages + 1;
        }

        return $end;
    }

    /**
     * link range.
     *
     * @return array
     */
    public function range()
    {
        return range($this->start(), $this->end());
    }

    public function reset($total)
    {
        $this->total_pages = ceil($total / $this->max_per_pages);
    }

    /**
     * modify suffix.
     *
     * @param string $suffix
     *
     * @return string
     */
    public function setSuffix($suffix)
    {
        return $this->suffix_separator = $suffix;
    }

    /**
     * Page suffix for filename.
     *
     * @param int $page_number  Page number
     * @param int $min          suffix filter
     *
     * @return string
     */
    public function suffix($page_number, $min = 1)
    {
        return ($page_number > $min) ? $this->suffix_separator.$page_number : '';
    }
}
