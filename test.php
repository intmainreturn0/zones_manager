<?

require 'ZonesManager.php';

var_dump( glob( dirname( __FILE__ ) . '/tests/*.php' ) );

$test1 = <<<'STR'
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

$test2 = <<<'STR'
$TTL 1h
@       IN      SOA     ns1.my-ns-server.com. hostmaster.example.com. (
    2007022600	; Serial
    3h		; Refresh
    1h		; Retry
    1w		; Expiry
    1d		; TTL
)

;;; NS ;;;
	        NS	ns1.my-ns-server.com.
		NS	ns.my-secondary-ns.com.

;;; MX ;;;
		MX 10	mx.example.com.

;;; A ;;;
	        A	127.0.0.1
www		CNAME	@
mx		A	127.0.0.1
STR;

// ----------------------------------------------------------------------------------------------------------

$str = $test1;

// chaos of testing
echo '<pre>';
try
{
    $zm   = \ZonesManager\ZonesManager::FromString( $str );
    //$aaaa = $zm->FilterDNS( null, 'AAAA' );
    //var_dump( $aaaa );
    // $aaaa = [ [ host=>example.com.  type=>AAAA  priority=>null  value=>2001:db8:10::1 ], [ host=>ns  type=>AAAA  priority=>null  value=>2001:db8:10::2 ] ]
    //$zm->AddDNS( 'mail4', 'A', '192.0.2.6' );
    //$zm->SetDNSValue( 'www', 'CNAME', 'sho.rt.' );
    //$zm->RemoveDNS( 'wwwtest', 'CNAME' );
    //$zm->SetSOAInfo( [ 'expiry' => '1600h' ] );
    //$zm->SaveFile( '/tmp/example.zone' );
    echo $zm->GenerateConfig();
}
catch( Exception $ex )
{
    echo 'Exception:<br>' . $ex->getMessage();
}
echo '</pre>';
//