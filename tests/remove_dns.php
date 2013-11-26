<?

/*
 *  Testing RemoveDNS method
 */

require_once '_test_utils.php';

$str = <<<'STR'
@       NS      ns1.example.com.
@       NS      ns2.example.com.
sub     NS      nssub.example.com.
a       A       example.com.
        AAAA    2001:db8:10::1
a2      A       example2.com.
        AAAA    2001:db8:10::2
        CNAME   othername.com.
mail1   MX 10   mail1.example.com.
mail2   MX 20   mail2.example.com.
mail3   MX      mail3.example.com.
mail4   MX 10   mail4.example.com.
STR;

$expect = <<<'STR'
@       NS      ns2.example.com.
sub     NS      nssub.example.com.
a       A       example.com.
a2      A       example2.com.
        AAAA    2001:db8:10::2
        CNAME   othername.com.
mail1   MX 10   mail1.example.com.
mail3   MX      mail3.example.com.
mail4   MX 10   mail4.example.com.
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->RemoveDNS( '@', 'NS', 'ns1.example.com.' );
    $zm->RemoveDNS( 'a', 'AAAA' );
    $zm->RemoveDNS( 'mail2', 'MX' );
} );