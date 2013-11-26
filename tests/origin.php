<?

/*
 *  Testing getting and setting $ORIGIN
 */

require_once '_test_utils.php';

$str = <<<'STR'
something unknown
$ORIGIN @           ; comment
$TTL 1h
STR;

$expect = <<<'STR'
something unknown
$ORIGIN neworigin   ; comment
$TTL 1h
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->GetOrigin() === '@' );
    $zm->SetOrigin( 'neworigin' );
    assert( $zm->GetOrigin() === 'neworigin' );
} );