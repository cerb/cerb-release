<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/bsd New BSD License
 * @package    Mail
 * @subpackage UnitTests
 */

/**
 * Test the mbox parsing objecct.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/bsd New BSD License
 * @package    Mail
 * @subpackage UnitTests
 */
class Horde_Mail_MboxParseTest extends \PHPUnit\Framework\TestCase
{
    public function testMboxParse()
    {
        $parse = new Horde_Mail_Mbox_Parse(__DIR__ . '/fixtures/test.mbox');

        $this->assertEquals(
            2,
            count($parse)
        );

        $i = 0;
        foreach ($parse as $key => $val) {
            $this->assertEquals(
                $i++,
                $key
            );

            $this->assertIsArray(
                $val
            );

            $this->assertEquals(
                "Return-Path: <bugs@horde.org>\r\n",
                fgets($val['data'])
            );
        }
    }

    /**
     * @dataProvider emlParseProvider
     */
    public function testEmlParse($data, $first_line)
    {
        $parse = new Horde_Mail_Mbox_Parse($data);

        $this->assertEquals(
            1,
            count($parse)
        );

        $val = $parse[0];

        $this->assertIsArray(
            $val
        );

        $this->assertEquals(
            $first_line . "\r\n",
            fgets($val['data'])
        );
    }

    public function emlParseProvider()
    {
        return array(
            array(
                __DIR__ . '/fixtures/test.eml',
                'Return-Path: <bugs@horde.org>'
            ),
            array(
                __DIR__ . '/fixtures/test2.eml',
                'Return-Path: <test@example.com>'
            )
        );
    }

    public function testBadData()
    {
        $this->expectException(Horde_Mail_Exception::class);
        new Horde_Mail_Mbox_Parse(__DIR__ . '/noexist');
    }

    public function testMessageLimitEnforced()
    {
        $mboxData = "From alice@example.com  Wed Oct 16 10:00:00 2024\r\n"
            . "Subject: Message 1\r\n"
            . "\r\n"
            . "Body 1\r\n"
            . "\r\n"
            . "From bob@example.com  Wed Oct 16 11:00:00 2024\r\n"
            . "Subject: Message 2\r\n"
            . "\r\n"
            . "Body 2\r\n"
            . "\r\n"
            . "From charlie@example.com  Wed Oct 16 12:00:00 2024\r\n"
            . "Subject: Message 3\r\n"
            . "\r\n"
            . "Body 3\r\n";

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $mboxData);
        rewind($resource);

        $this->expectException(Horde_Mail_Exception::class);
        $this->expectExceptionMessage('more than enforced limit');
        new Horde_Mail_Mbox_Parse($resource, 2);
    }

    public function testMessageLimitAllowsExactCount()
    {
        $mboxData = "From alice@example.com  Wed Oct 16 10:00:00 2024\r\n"
            . "Subject: Message 1\r\n"
            . "\r\n"
            . "Body 1\r\n"
            . "\r\n"
            . "From bob@example.com  Wed Oct 16 11:00:00 2024\r\n"
            . "Subject: Message 2\r\n"
            . "\r\n"
            . "Body 2\r\n";

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $mboxData);
        rewind($resource);

        $parse = new Horde_Mail_Mbox_Parse($resource, 2);
        $this->assertEquals(2, count($parse));
    }

    public function testLeadingWhitespaceFromLineParsedAsMarker()
    {
        $mboxData = " From alice@example.com  Wed Oct 16 10:00:00 2024\r\n"
            . "Subject: Message 1\r\n"
            . "\r\n"
            . "Body 1\r\n"
            . "\r\n"
            . "From bob@example.com  Wed Oct 16 11:00:00 2024\r\n"
            . "Subject: Message 2\r\n"
            . "\r\n"
            . "Body 2\r\n";

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $mboxData);
        rewind($resource);

        $parse = new Horde_Mail_Mbox_Parse($resource);
        $this->assertEquals(2, count($parse), 'Leading whitespace on first From line should not prevent MBOX detection');
    }

}
