Parse, modify and save bind9 zone files
=====

The purpose of this library is to work with [zone files](http://en.wikipedia.org/wiki/Zone_file) - not just to parse them, but to **modify** and save, without losing comments and formatting. Such files are used by bind9 and nsd.

[test link](tests/filter_dns.php)  [test link 2](ZonesManager.php)

### Usage

Just require ZonesManager.php and use class ZonesManager:
```php
$zm = \ZonesManager\ZonesManager::FromString( $str );   // or FromFile
// get info: $zm->GetAllDNS(), $zm->FilterDNS(...), $zom->GetSOAInfo() and others
// modify: $zm->AddDNS(...), $zm->SetTTL(...), $zm->RemoveDNS(...) and others
echo $zm->GenerateConfig(); // raw correct bind9 config
// echo $zm->DebugOutput(); // see details of parsed data
```

### Example

Assume that you have the config from wikipedia example.

```php
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
```

You want to parse it, get all AAAA entries, add 'mail4 A 192.0.2.6', change 'www CNAME' from 'example.com.' to 'sho.rt.', remove wwwtest, update SOA expiration (set '1600h') and save into a file.

```php
$zm   = \ZonesManager\ZonesManager::FromString( $str );
$aaaa = $zm->FilterDNS( null, 'AAAA' );
// = [ [ host=>example.com.  type=>AAAA  priority=>null  value=>2001:db8:10::1 ], [ ... ] ]
$zm->AddDNS( 'mail4', 'A', '192.0.2.6' );
$zm->SetDNSValue( 'www', 'CNAME', 'sho.rt.' );
$zm->RemoveDNS( 'wwwtest', 'CNAME' );
$zm->SetSOAInfo( [ 'expiry' => '1600h' ] );
$zm->SaveFile( '/tmp/example.zone' );
```

After it, contents of /tmp/example.zone will be:

```
$ORIGIN example.com.     ; designates the start of this zone file in the namespace
$TTL 1h                  ; default expiration time of all resource records without their own TTL value
example.com.  IN    SOA   ns.example.com. username.example.com. (
              2013112300 ; serial number of this zone file
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
```

This saved file can be opened again using ZoneManager.
Note that there are some interesting features:

### Positivities

* Content that can't be recognized is left as is - so, it doesn't corrupt the file if it has unsupported format
* It preserves comments (so, if you manually add comments to a file, then modify it with ZonesManager, they won't be deleted)
* Moreover: it preserves comments formatting; notice: in the example we have changed 'example.com.' to 'sho.rt.' and '4w' to '1600h', but comments at the right are properly aligned
* Automatic updates of SOA serial on saving (format of which is YYYYMMDDRR, RR - revision of current day, starting from 00) (this is especially needed when using master/slave replication, for example via nsd-control reload)
* Works correctly with MX priorities
* Works correctly with omitted hosts in dns entries (and remains them omitted on saving, nevertheless they are available in getting/filtering)

### Limitations

* No support for unique TTL for every dns entry (it's a very rare situation) (global $TTL is of course parsed correctly)
* No support for 'IN' for dns entries ('IN' meaned 'internet', could be used in some old configs, now deprecated)
* SOA properties (serial, refresh, retry, expiry, caching) should be one per line (it's a common case)