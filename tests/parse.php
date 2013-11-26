<?

/*
 *  Testing parsing and getting config structure.
 *  Note that after saving (no changes) config remains the same (str==expected) - comments are left, omitted hosts are omitted and so on
 * (there are more tests that cover all cases).
 */

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

CheckTest( $str, $str, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->GetSOAInfo()['caching'] === '1h' );
    assert( $zm->GetTTL() === '1h' );
    assert( count( $zm->GetAllDNS() ) === 14 );
    assert( count( $zm->FilterDNS( 'example.com.' ) ) === 5 );
    assert( count( $zm->FilterDNS( null, 'A' ) ) === 5 );
    assert( count( $zm->FilterDNS( null, 'MX' ) ) === 3 );
    assert( count( $zm->FilterDNS( null, 'MX', 50 ) ) === 1 );
    assert( count( $zm->FilterDNS( 'ns', 'A' ) ) === 1 );
    assert( count( $zm->FilterDNS( 'ns', 'CNAME' ) ) === 0 );
} );