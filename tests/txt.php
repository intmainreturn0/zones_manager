<?

require_once '_test_utils.php';

$str = <<<STR
@ TXT "some\;arbitrary" " tex\;t" ; comment here
STR;

$expect = <<<STR
@ TXT "some\;arbitrary" " tex\;t" ; comment here
STR;

CheckTest( $str, $str, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->FilterDNS( '@', 'TXT' )[0]['value'] === '"some;arbitrary" " tex;t"' );
} );