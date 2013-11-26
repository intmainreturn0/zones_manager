<?

/*
 * Testing preserving comments and formatting.
 * The main purpose is that when source is like
              2h         ; slave retry time in case of a problem (2 hours)
              4w         ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
    and we change '4w' to 'longvalue', result DOES NOT become
              2h         ; slave retry time in case of a problem (2 hours)
              longvalue         ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
    but comments are left aligned:
              2h         ; slave retry time in case of a problem (2 hours)
              longvalue  ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
    This is because ZonesManager remembers start comment position for each line and on generation it tries to format comment where is was before modifying.
 */

require_once '_test_utils.php';

$str = <<<'STR'
;;; TTL ;;;
$TTL 1h                             ; comment
;;; SOA ;;;
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710 ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              4w         ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
              )

;;; NS ;;;
@           NS      ns1.com.        ; comment

;END
STR;

$expect = <<<'STR'
;;; TTL ;;;
$TTL 1000h                          ; comment
;;; SOA ;;;
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710 ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              newvalue   ; slave expiration time (4 weeks)
              newvalue   ; maximum caching time in case of failed lookups (1 hour)
              )

;;; NS ;;;
@           NS      ns1.example.com.; comment

;END
@ NS n2.com.
STR;

CheckTest( $str, $expect, function ( $zm ) use ( $expect )
{
    /** @var $zm \ZonesManager\ZonesManager */
    $zm->SetTTL( '1000h' );
    $zm->ReplaceDNS( '@', 'NS', null, null, null, null, 'ns1.example.com.' );
    $zm->AddDNS( '@', 'NS', 'n2.com.' );
    $zm->SetSOAInfo( [ 'expiry' => 'newvalue', 'caching' => 'newvalue' ] );
    // to make sure that comments and formatting are preserved, you can invoke this file directly (not from test_all.php) and uncomment:
    //echo '<pre>' . $zm->GenerateConfig() . '</pre>';
} );