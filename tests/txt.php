<?

require_once '_test_utils.php';

// check that read-save doesn't corrupt TXT entries and normally detects escaped semicolons

$str = <<<STR
@ TXT "some\;arbitrary" " tex\;t" ; comment here
STR;

CheckTest( $str, $str, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->FilterDNS( '@', 'TXT' )[0]['value'] === '"some;arbitrary" " tex;t"' );
} );

// check adding and removing

$expect = <<<STR
@   TXT "some\;arbitrary" " tex\;t" ; comment here
t1  TXT "another value"
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->AddDNS( 't2', 'TXT', 'another value 2' );
    $zm->AddDNS( 't1', 'TXT', 'another value' );
    $zm->RemoveDNS( 't2', 'TXT' );
} );

// check adding with and without around quotes

$expect = <<<STR
@   TXT "some\;arbitrary" " tex\;t" ; comment here
t1  TXT "with quotes"
t2  TXT "without quotes"
t3  TXT "double" " value"
t4  TXT "console.log('123')\;"
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->AddDNS( 't1', 'TXT', '"with quotes"' );
    $zm->AddDNS( 't2', 'TXT', 'without quotes' );
    $zm->AddDNS( 't3', 'TXT', '   "double" " value"   ' );
    $zm->AddDNS( 't4', 'TXT', "console.log('123');" );
} );

// check getting value

CheckTest( $expect, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->FilterDNS( 't1' )[0]['value'] === '"with quotes"' );
    assert( $zm->FilterDNS( 't2' )[0]['value'] === '"without quotes"' );
    assert( $zm->FilterDNS( 't3' )[0]['value'] === '"double" " value"' );
    assert( $zm->FilterDNS( 't4' )[0]['value'] === '"console.log(\'123\');"' );
} );