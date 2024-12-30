<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Session;

use SessionHandlerInterface;

/**
 * Session with database class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class ApcuHandler implements SessionHandlerInterface
{
    private $session_name;

    public function open($save_path, $session_name): bool
    {
        $this->session_name = $session_name;

        return true;
    }

    public function read($id): string
    {
        return apcu_fetch($id);
    }

    public function write($id, $session_data): bool
    {
        return apcu_store($id, $session_data, (int)ini_get('apc.ttl'));
    }

    public function destroy($id): bool
    {
        if (apcu_exists($id)) {
            return apcu_delete($id);
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime): bool
    {
        $list = apcu_cache_info();
        $ttl = (int)ini_get('apc.gc_ttl');
        $count = 0;
        foreach ($list['cache_list'] as $v) {
            if (($v['access_time'] + $ttl) < $_SERVER['REQUEST_TIME']) {
                if (false === apcu_delete($v['info'])) {
                    return false;
                }
                ++$count;
            }
        }

        return true;
    }
}
