<?

/*
 *  Testing getting and moditying SOA entries
 */

require_once '_test_utils.php';

$str = <<<'STR'
example.com.  IN  SOA  ns.example.com. username.example.com. (
              2007120710 ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              4w         ; slave expiration time (4 weeks)
              1h         ; maximum caching time in case of failed lookups (1 hour)
              )
STR;

$date   = date( 'Ymd' );
$expect = <<<STR
example.com.  IN  SOA  newns.example.com. newmail.example.com. (
              {$date}01  ; serial number of this zone file
              1d         ; slave refresh (1 day)
              2h         ; slave retry time in case of a problem (2 hours)
              4w         ; slave expiration time (4 weeks)
              1600h      ; maximum caching time in case of failed lookups (1 hour)
              )
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->GetSOAInfo()['email'] === 'username.example.com.' );
    assert( $zm->GetSOAInfo()['caching'] === '1h' );

    $zm->SetSOAInfo( [ 'ns' => 'incorrect', 'email' => 'newmail.example.com.', 'caching' => '1600h' ] );
    $zm->SetSOAInfo( [ 'ns' => 'newns.example.com.' ] );
    $zm->UpdateSOASerial(); // at first in becomes YYYYMMDD00
    $zm->UpdateSOASerial(); // then YYYYMMDD01

    assert( $zm->GetSOAInfo()['caching'] === '1600h' );
    assert( $zm->GetSOAInfo()['expiry'] === '4w' );
} );