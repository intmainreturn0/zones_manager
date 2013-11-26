<?

/*
 *  Testing AddDNS method
 */

require_once '_test_utils.php';

$str = <<<STR
@ A 127.0.0.1
STR;

$expect = <<<STR
@             A     127.0.0.1
@             NS    172.45.19.20
@             NS    172.45.19.21; comment
mail          MX    20 172.45.19.20
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->AddDNS( '@', 'NS', '172.45.19.20' );
    $zm->AddDNS( '@', 'NS', '172.45.19.21', null, 'comment' );
    $zm->AddDNS( 'mail', 'MX', '172.45.19.20', 20 );
} );
