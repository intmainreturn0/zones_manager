<?

/*
 * Test parsing and saving configs with omitted hosts.
 * Example of omitted host:
        @   NS  ns1.example.com.
            NS  ns2.example.com.    ; here
   The second line gets the same host (@), while it is not written directly.
 */

require_once '_test_utils.php';

$str = <<<'STR'
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710
              1d
              2h
              4w
              1h
              )
            NS      ns1.com.        ; omitted and got from SOA
main        A       main.a.com.
            AAAA    0::::::0        ; omitted and got from A
            CNAME   another
home        A       home.a.com.
            MX 10   mail.a.com.
STR;

$expect = <<<'STR'
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710
              1d
              2h
              4w
              1h
              )
            NS      nsnew.com.      ; omitted and got from SOA
main        A       main.a.com.
            AAAA    1::::::1        ; omitted and got from A
home        A       home.a.com.
            MX 70   mailnew.a.com.
home        TXT     "some new text"
new         CNAME   old
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( count( $zm->FilterDNS( 'example.com.' ) ) === 1 );
    assert( count( $zm->FilterDNS( 'home' ) ) === 2 );
    assert( count( $zm->FilterDNS( 'main', 'AAAA' ) ) === 1 );

    $zm->SetDNSValue( 'example.com.', 'NS', 'nsnew.com.' );
    $zm->ReplaceDNS( 'home', 'MX', null, null, null, null, 'mailnew.a.com.', 70 );
    $zm->RemoveDNS( 'main', 'CNAME' );
    $zm->SetDNSValue( 'main', 'AAAA', '1::::::1' );
    $zm->AddDNS( 'home', 'TXT', 'some new text' );
    $zm->AddDNS( 'new', 'CNAME', 'old' );

    assert( count( $zm->FilterDNS( 'main' ) ) === 2 );
    assert( count( $zm->FilterDNS( 'example.com.' ) ) === 1 );
} );