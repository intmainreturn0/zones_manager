<?

/*
 *  Testing ReplaceDNS method
 */

require_once '_test_utils.php';

$str = <<<'STR'
@       NS      ns1.example.com.
@       NS      ns2.example.com.
@       NS      ns3.example.com.
sub1    A       sub1.example.com.
        AAAA    0::::::::::0
sub2    CNAME   sub1
        AAAA    0::::::::::1
SUB3    CNAME   another.example.com.
mail    MX 10   mail.example.com.
STR;

$expect = <<<'STR'
@       NS      ns11.example.com.
@       NS      ns22.example.com.
sub1    A       sub1.example.com.
        AAAA    1::::::::::1
sub2    A       sub1
        AAAA    0::::::::::1
sub3    CNAME   another.example.com.
mail    MX 20   mail.example.com.
sub1    NS      newsub1
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->ReplaceDNS( '@', 'NS', 'ns1.example.com.', null, null, null, 'ns11.example.com.' );
    $zm->ReplaceDNS( '@', 'NS', 'ns2.example.com.', null, null, null, 'ns22.example.com.' );
    $zm->RemoveDNS( '@', 'NS', 'ns3.example.com.' );
    $zm->ReplaceDNS( 'sub1', 'AAAA', null, null, null, null, '1::::::::::1' );
    $zm->ReplaceDNS( 'sub2', 'CNAME', null, null, null, 'A' );
    $zm->ReplaceDNS( 'SUB3', 'CNAME', null, null, 'sub3' );
    $zm->ReplaceDNS( 'mail', 'MX', null, null, null, null, null, 20 );
    $zm->AddDNS( 'sub1', 'NS', 'newsub1' );
} );