<?

/*
 *  Testing saving to file (with automatic SOA serial updating)
 *  !!! Attention! To make this test be passed, php should have rights to create file in /tmp/ directory.
 */

require_once '_test_utils.php';

$str = <<<'STR'
$ORIGIN example.com.     ; designates the start of this zone file in the namespace
$TTL 1h                  ; default expiration time of all resource records without their own TTL value
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710 ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              4w         ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
              )
example.com.  NS    ns                    ; ns.example.com is a nameserver for example.com
example.com.  NS    ns.somewhere.example. ; ns.somewhere.example is a backup nameserver for example.com
example.com.  MX    10 mail.example.com.  ; mail.example.com is the mailserver for example.com
@             MX    20 mail2.example.com. ; equivalent to above line, "@" represents zone origin
@             MX    50 mail3              ; equivalent to above line, but using a relative host name
example.com.  A     192.0.2.1             ; IPv4 address for example.com
              AAAA  2001:db8:10::1        ; IPv6 address for example.com
ns            A     192.0.2.2             ; IPv4 address for ns.example.com
              AAAA  2001:db8:10::2        ; IPv6 address for ns.example.com
www           CNAME example.com.          ; www.example.com is an alias for example.com
wwwtest       CNAME www                   ; wwwtest.example.com is another alias for www.example.com
mail          A     192.0.2.3             ; IPv4 address for mail.example.com,
                                          ;  any MX record host must be an address record
                                          ; as explained in RFC 2181 (section 10.3)
mail2         A     192.0.2.4             ; IPv4 address for mail2.example.com
mail3         A     192.0.2.5             ; IPv4 address for mail3.example.com
STR;

$expect = <<<'STR'
$ORIGIN example.com.     ; designates the start of this zone file in the namespace
$TTL 1h                  ; default expiration time of all resource records without their own TTL value
example.com.  IN    SOA   ns.example.com. username.example.com. (
              {date}00   ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              1600h      ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
              )
example.com.  NS    ns                    ; ns.example.com is a nameserver for example.com
example.com.  NS    ns.somewhere.example. ; ns.somewhere.example is a backup nameserver for example.com
example.com.  MX    10 mail.example.com.  ; mail.example.com is the mailserver for example.com
@             MX    20 mail2.example.com. ; equivalent to above line, "@" represents zone origin
@             MX    50 mail3              ; equivalent to above line, but using a relative host name
example.com.  A     192.0.2.1             ; IPv4 address for example.com
              AAAA  2001:db8:10::1        ; IPv6 address for example.com
ns            A     192.0.2.2             ; IPv4 address for ns.example.com
              AAAA  2001:db8:10::2        ; IPv6 address for ns.example.com
www           CNAME sho.rt.               ; www.example.com is an alias for example.com
mail          A     192.0.2.3             ; IPv4 address for mail.example.com,
                                          ;  any MX record host must be an address record
                                          ; as explained in RFC 2181 (section 10.3)
mail2         A     192.0.2.4             ; IPv4 address for mail2.example.com
mail3         A     192.0.2.5             ; IPv4 address for mail3.example.com
mail4         A     192.0.2.6
STR;
$expect = str_replace( '{date}', date( 'Ymd' ), $expect );

CheckTest( $str, $expect, function ( $zm ) use ( $expect )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $aaaa = $zm->FilterDNS( null, 'AAAA' );
    assert( $aaaa === array( 0 => array( 'host' => 'example.com.', 'type' => 'AAAA', 'priority' => NULL, 'value' => '2001:db8:10::1', ),
                             1 => array( 'host' => 'ns', 'type' => 'AAAA', 'priority' => NULL, 'value' => '2001:db8:10::2', ), ) );
    $zm->AddDNS( 'mail4', 'A', '192.0.2.6' );
    $zm->SetDNSValue( 'www', 'CNAME', 'sho.rt.' );
    $zm->RemoveDNS( 'wwwtest', 'CNAME' );
    $zm->SetSOAInfo( [ 'expiry' => '1600h' ] );
    if( is_writable( '/tmp/' ) )
    {
        $zm->SaveFile( '/tmp/example.zone' );
        assert( AreConfigsEqual( file_get_contents( '/tmp/example.zone' ), $expect ) );
    }
    else
        echo "Can't create file in /tmp, test will be assumed wrong";
} );
@unlink( '/tmp/example.zone' );