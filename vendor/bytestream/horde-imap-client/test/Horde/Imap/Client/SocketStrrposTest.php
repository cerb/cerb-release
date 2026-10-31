<?php
/**
 * Test for unchecked strrpos() calls in Socket FETCH response handling.
 *
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_SocketStrrposTest extends \PHPUnit\Framework\TestCase
{
    public $test_ob;

    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/Stub/Socket.php';

        $this->test_ob = new Horde_Imap_Client_Stub_Socket(array(
            'password' => 'foo',
            'username' => 'bar'
        ));
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->test_ob);
    }

    /**
     * A malformed FETCH response with a BODY[...] tag missing its closing
     * bracket must not be silently misparsed. Before the fix, this caused
     * strrpos() to return false, which substr() type-coerces to 0, causing
     * the tag to be treated as an empty BODY[] (whole message) request
     * instead of being rejected -- silently attributing literal data to
     * the wrong part (or the full message).
     */
    public function testMalformedBodyTagMissingClosingBracket()
    {
        $this->expectException('Horde_Imap_Client_Exception');

        // Note: missing closing "]" after "BODY[1"
        $test = '* 1 FETCH (BODY[1 {5}HELLO)';
        $this->test_ob->parseFetch($test);
    }

    /**
     * Sanity check: a well-formed BODY[n] response still parses correctly
     * to the right MIME part after the fix.
     */
    public function testWellFormedBodyTagStillParses()
    {
        $test = '* 1 FETCH (BODY[1] {5}HELLO)';
        $result = $this->test_ob->parseFetch($test);

        $this->assertEquals(
            'HELLO',
            $result->fetch->first()->getBodyPart('1')
        );
    }
}
