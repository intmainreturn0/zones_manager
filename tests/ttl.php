<?

/*
 *  Testing getting and setting $TTL
 */

require_once '_test_utils.php';

$str = <<<'STR'
$TTL 1600h      ; comment

@ NS ns1.example.com.
STR;

$expect = <<<'STR'
$TTL 1h         ; comment

@ NS ns1.example.com.
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->GetTTL() === '1600h' );
    $zm->SetTTL( '1h' );
    assert( $zm->GetTTL() === '1h' );
} );