<?

/*
 *  Testing FilterDNS method
 */

require_once '_test_utils.php';

$str = <<<'STR'
@       NS      ns1.example.com.
@   100 NS      ns2.example.com.
sub     NS      nssub.example.com.
a       A       example.com.
        AAAA    2001:db8:10::1
a2      A       example2.com.
        AAAA    2001:db8:10::2
mail1   MX 10   mail1.example.com.
mail2   MX 20   mail2.example.com.
mail3 IN  MX    mail3.example.com.
mail4   MX 10   mail4.example.com.
STR;
//todo add IN and TTL per line and rewrite tests

CheckTest( $str, $str, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->FilterDNS( '@', 'NS' ) == array( 0 => array( 'host' => '@', 'type' => 'NS', 'priority' => NULL, 'value' => 'ns1.example.com.', ),
                                                  1 => array( 'host' => '@', 'type' => 'NS', 'priority' => NULL, 'value' => 'ns2.example.com.', 'ttl' => 100 ), ) );
    assert( $zm->FilterDNS( '@', 'NS' ) === $zm->FilterDNS( '@' ) );
    assert( $zm->FilterDNS( 'sub', 'NS' ) === array( 0 => array( 'host' => 'sub', 'type' => 'NS', 'priority' => NULL, 'value' => 'nssub.example.com.', ), ) );

    assert( count( $zm->FilterDNS( null, 'MX' ) ) === 4 );
    assert( count( $zm->FilterDNS( null, 'MX', 10 ) ) === 2 );
    assert( count( $zm->FilterDNS( null, 'MX', 999 ) ) === 0 );
    assert( $zm->FilterDNS( null, 'MX', 20 ) === array( 0 => array( 'host' => 'mail2', 'type' => 'MX', 'priority' => '20', 'value' => 'mail2.example.com.', ), ) );

    assert( $zm->FilterDNS( 'mail1' )[0] === $zm->FilterDNS( null, null, 10 )[0] );

    assert( $zm->FilterDNS( 'a' ) == array( 0 => array( 'host' => 'a', 'type' => 'A', 'priority' => NULL, 'value' => 'example.com.', ),
                                            1 => array( 'host' => 'a', 'type' => 'AAAA', 'priority' => NULL, 'value' => '2001:db8:10::1', ), ) );

    assert( count( $zm->FilterDNS( 'a2' ) ) === 2 );
    assert( $zm->FilterDNS( 'a2', 'AAAA' ) == array( 0 => array( 'host' => 'a2', 'type' => 'AAAA', 'priority' => NULL, 'value' => '2001:db8:10::2', ), ) );
    assert( count( $zm->GetAllDNS() ) === 11 );
} );