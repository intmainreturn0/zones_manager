<?

/*
 * Test modifying configs with omitted hosts.
 * Made for issue: https://github.com/intmainreturn0/zones_manager/issues/1 - Entry with omitted zone name doesn't preserve zone name after modification of related entry.
 */

require_once '_test_utils.php';

//////////////////////////////////////////////////////////
// 1

$str = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            A     192.0.2.2
              AAAA  2001:db8:10::2
STR;

$expect = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            AAAA  2001:db8:10::2
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->RemoveDNS( 'ns', 'A', '192.0.2.2' );
} );

//////////////////////////////////////////////////////////
// 2

$str = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            A     192.0.2.2
              AAAA  2001:db8:10::2
STR;

$expect = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
nsnew         A     192.0.2.2
ns            AAAA  2001:db8:10::2
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->ReplaceDNS( 'ns', 'A', null, null, 'nsnew' );
} );

//////////////////////////////////////////////////////////
// 3

$str = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            A     192.0.2.2
              AAAA  2001:db8:10::2
STR;

$expect = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            A     192.0.2.2
nsnew         AAAA  2001:db8:10::2
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->ReplaceDNS( 'ns', 'AAAA', null, null, 'nsnew' );
} );

//////////////////////////////////////////////////////////
// 4

$str = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            A     192.0.2.2
              AAAA  2001:db8:10::2
              MX 10 mxval
STR;

$expect = <<<'STR'
example.com.  A     192.0.2.1
              AAAA  2001:db8:10::1
ns            AAAA  2001:db8:10::2
              MX 10 mxval
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->RemoveDNS( 'ns', 'A' );
} );