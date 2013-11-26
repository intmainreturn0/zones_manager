<?

/*
 *  Testing work with MX dns record types
 */

require_once '_test_utils.php';

$str = <<<'STR'
@       A       example.com.
mail1   MX      mail1           ; no priority
mail2   MX 20   mail2           ; priority will be deleted
mail3   MX 30   mail3           ; whole line will be deleted
STR;

$expect = <<<'STR'
@       A       example.com.
mail1   MX 10   mail1new        ; no priority
mail2   MX      mail2           ; priority will be deleted
mail4   MX      mail4
mail5   MX 10   mail5           ; comment also added
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( count( $zm->FilterDNS( 'mail1' ) ) === 1 );
    assert( count( $zm->FilterDNS( null, 'MX' ) ) === 3 );
    assert( count( $zm->FilterDNS( null, 'MX', 20 ) ) === 1 );

    $zm->RemoveDNS( 'mail3', 'MX' );
    $zm->RemoveDNS( 'mail3', 'MX' ); // should not do anything
    $zm->AddDNS( 'mail4', 'MX', 'mail4old' );
    $zm->ReplaceDNS( 'mail4', 'MX', null, null, null, null, 'mail4' );
    $zm->AddDNS( 'mail5', 'MX', 'mail5', 10, 'comment also added' );
    $zm->ReplaceDNS( 'mail1', 'MX', null, null, null, null, 'mail1new', 10 );
    $zm->ReplaceDNS( 'mail2', 'MX', null, null, null, null, null, 0 );

    assert( count( $zm->FilterDNS( null, 'MX', 10 ) ) === 2 );
    assert( count( $zm->FilterDNS( 'mail5', null, 10 ) ) === 1 );
} );