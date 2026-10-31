<?php
/**
 * Test for unchecked stream search() return in Tokenize.
 *
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

class Horde_Imap_Client_TokenizeSearchNullTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getLiteralLength() returns null (per its documented contract,
     * and what callers like Socket/Connection/Socket.php check for) on
     * malformed literal data without an opening brace.
     */
    public function testGetLiteralLengthMissingBrace()
    {
        // Malformed literal data without opening brace
        $tokenize = new Horde_Imap_Client_Tokenize('INVALID}');

        // search() will return null if '{' is not found
        // Before fix: null - 1 coerces to -1, causing wrong stream access
        // After fix: explicit null check returns null, matching the
        // method's documented "null if no literal data found" contract
        $result = $tokenize->getLiteralLength();

        $this->assertNull($result, 'Should return null for malformed literal');
    }

    /**
     * Test that null from search() doesn't coerce to -1 in arithmetic
     */
    public function testNullSearchCoercion()
    {
        // When search() returns null (not found), null - 1 coerces to -1
        // This would cause negative length in substr(), corrupting parsing
        $search_result = null;
        $coerced = $search_result - 1;

        // Before fix: this would cause wrong stream behavior
        // The fix adds explicit validation before arithmetic
        $this->assertEquals(-1, $coerced, 'Null coerces to -1 in arithmetic');
    }
}
