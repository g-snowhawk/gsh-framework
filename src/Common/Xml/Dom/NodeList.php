<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2019 PlusFive (http://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Xml\Dom;

/**
 * XML DOM custom  Nodelist.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class NodeList implements \Iterator
{
    private $index = 0;
    private $items = [];
    private $nofilter = false;

    public function __construct(array $items, $nofilter = false)
    {
        $this->items = $items;
        $this->nofilter = $nofilter;
        $this->rewind();
    }

    public function __get($key)
    {
        switch ($key) {
            case 'length':
                if ($this->nofilter === false) {
                    $this->items = array_values(array_filter($this->items, [$this, 'itemFilter']));
                }

                return count($this->items);
                break;
        }
    }

    public function item($index)
    {
        if ($this->nofilter === false) {
            $this->items = array_values(array_filter($this->items, [$this, 'itemFilter']));
        }

        return $this->items[$index] ?? null;
    }

    private function itemFilter($value)
    {
        return !is_null($value->parentNode);
    }

    public function current(): ?object
    {
        return $this->items[$this->index];
    }
    public function key(): int
    {
        return $this->index;
    }
    public function next(): void
    {
        if ($this->nofilter === false) {
            $this->items = array_values(array_filter($this->items, [$this, 'itemFilter']));
        }
        ++$this->index;
    }
    public function rewind(): void
    {
        $this->index = 0;
    }
    public function valid(): bool
    {
        return isset($this->items[$this->index]);
    }
}
