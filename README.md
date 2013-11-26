Parse, modify and save bind9 zone files
=====

The purpose of this library is to work with [zone files](http://en.wikipedia.org/wiki/Zone_file) - not just to parse them, but to **modify** and save, without losing comments and formatting. Such files are used by bind9 and nsd.

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

### Features and positivities

* Parse ([test](tests/parse.php)), get scructure ([test](tests/filter_dns.php)), modify ([add](tests/add_dns.php), [replace](tests/replace_dns.php), [remove](tests/remove_dns.php), [soa](tests/soa.php)) and save ([test](tests/files.php)) bind9 format
* You can open a file with ZonesManager and save it, and it does not corrupt file scructure: unknown content is left as is ([test](tests/nocorrupt.php)), comments are left and even alignment is preserved ([test](tests/comments.php)), omitted hosts are left omitted ([test](tests/omitted.php))
* Working with SOA: get and update info ([test](tests/soa.php)) and autoupdate serial on saving ([test](tests/files.php)) (format of which is YYYYMMDDRR, RR - revision of current day, starting from 00) (this is especially needed when using master/slave replication, for example via nsd-control reload)
* Works correctly with MX priorities ([test](tests/mx.php))
* Works correctly with TXT entries, quotes values and backslashed semicolons ([test](tests/txt.php))

### Limitations

* No support for unique TTL for every dns entry (it's a very rare situation) (global $TTL is of course parsed correctly)
* No support for 'IN' for dns entries ('IN' meaned 'internet', could be used in some old configs, now deprecated)
* SOA properties (serial, refresh, retry, expiry, caching) should be one per line (it's a common case)
* Parsed types: A,AAAA,NS,TXT,CNAME,MX. If any other type (e.g., SRV) appears in a file, it won't be recognized (and won't be available in GetAllDNS and others), but won't be corrupted (will be left as is, because it is an unknown content) ([test](tests/nocorrupt.php))