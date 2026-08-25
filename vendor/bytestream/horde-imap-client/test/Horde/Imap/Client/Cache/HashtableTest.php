<?php
/**
 * Copyright 2013-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2013-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

/**
 * Tests for the Horde_HashTable cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class Horde_Imap_Client_Cache_HashtableTest
extends Horde_Imap_Client_Cache_TestBase
{
    protected function _getBackend()
    {
        if (!class_exists('Horde_HashTable_Base')) {
            $this->markTestSkipped('The HashTable package is unavailable!');
        }

        return new Horde_Imap_Client_Cache_Backend_Hashtable(array(
            'hashtable' => new Horde_HashTable_Memory
        ));
    }
}
